<?php


if(!defined('TASK_LOG_PREFIX')) {
	define('TASK_LOG_PREFIX', 'task-consumer');
}

require __DIR__ . '/../autoload.php';

$lock = new FileLock();

if(! $lock->lock()) {
	// outlog('do not get lock');
	return ;
}


// function getRedis() {
// 	$redis = new Redis();
//  // 此处不要指定太短的超时时间, 会影响blPop指定的超时时间, 这里是全局的超时时间
// 	$redis->pconnect('vm0', 6379);
// 	$redis->select(7);

// 	return $redis;
// }

if(! function_exists('getRedis')) {
	outlog('function getRedis not exist');
	exit(1);
}

outlog('start');

// 本机最大线程数
if(! isset($maxProcessNum)) {
	$maxProcessNum = 30;
}
// 最大循环次数
if(! isset($maxLoopNum)) {
	$maxLoopNum = 100;
}
// 已经循环次数
if(! isset($alreadyLoopNum)) {
	$alreadyLoopNum = 0;
}

$isExit = false;

SignalHandler::addExitHanlder(function($signo) use(&$isExit) {
	if(! $isExit) {
		outlog('prepare to exit...' . $signo);
		$isExit = true;
	}
});

$proc = new MultiProcess();

while(! $isExit) {

	// 退出条件:
	// 1:超过最大循环次数
	// 2:执行到一分钟的最后阶段(50秒以后), 原因:现有情况下是每分钟去启动此任务, 等换成supervisord管理进程后此问题会解决
	// if($alreadyLoopNum >= $maxLoopNum && (date('s')>50)) {
	if($alreadyLoopNum >= $maxLoopNum) {
		break;
	}

	$alreadyLoopNum++;

	// 单机进程数限制
	$proc->wait($maxProcessNum-1);

	// 每循环10次清理一遍僵尸进程
	if($alreadyLoopNum % 10 == 0) {
		$proc->clean();
	}

	$redis = getRedis();

	// 阻塞取任务
	// 此处如果没有获取到数据, 这条命令不会提现在elk日志系统中
	$taskPreKey = $redis->blPop('task:queue:ctrl', 10);

	if(! $taskPreKey || count($taskPreKey) < 2) {
		$redis->close();
		// 50ms
		usleep(50000);
		continue;
	}

	// 数据任务
	$data_task = $taskPreKey[1];

	$pid = $proc->fork(function($mypid) use($data_task) {
		global $task_def, $gateway_url;

		$data_task = json_decode($data_task, true);

		// TODO 从数据库中取或者redis
		$task_name = $data_task['task'];
		$task = $task_def[$task_name];

		$redis = getRedis();


		// TODO task status 1=>2

		// TODO 生成任务id
		$task_id = 1;

		$taskPreKey = 'task:queue:'.$task_name.':' . $task_id . ':';

		// 放入执行池子
		// $redis->

		$start_ts = microtime(true);

		// 开始执行任务


		outlog('post gateway:' . json_encode($data_task));

		$data_task['params'] = json_encode($data_task['params']);

		$ret = curl_post($gateway_url, $data_task, 60 * 60);

		outlog('gateway ret:' . $ret);

		$ret_arr = json_decode($ret, true);

		if($ret_arr !== false) {
			// 总任务数目
			$total = count($ret_arr);

			// 计算最佳每批个数，或者可以动态规划
			$batch_num = suitBatchNum($total, $task['concurrncy'], $task['max_batch_num'], $task['min_batch_num']);

			$batch_task = [
				'task' => $task_name,
				'svc'  => $task['batch_svc'],
				'params' => []
			];

			outlog('batch_task' . json_encode($batch_task));

			// 放入队列，控制并发
			addToPool($taskPreKey, $batch_task, $ret_arr, $task['concurrncy'], $batch_num);

		}


		// TODO task status 2=>3

		outlog('finish');

		// $redis->close();

	});

	// fork fail TODO 处理失败情况
	if($pid == -1) {

	}
	// 子进程fork成功
	if($pid > 0) {

	}

	// 20ms
	usleep(20000);

}

// 先解锁, 再等待子进程退出, 这样不影响启动新的守护进程
$lock->unlock();

$proc->wait(0);

outlog("exit");

// 标记为非正常退出,为使用supervisord管理进程做准备
exit(2);


