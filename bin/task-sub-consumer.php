<?php


if(!defined('TASK_LOG_PREFIX')) {
	define('TASK_LOG_PREFIX', 'task-sub-consumer');
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
// 	$redis->pconnect('localhost', 6379);
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
	$taskPreKey = $redis->blPop('task:queue:data', 10);

	if(! $taskPreKey || count($taskPreKey) < 2) {
		$redis->close();
		// 50ms
		usleep(50000);
		continue;
	}

	$taskPreKey = $taskPreKey[1];

	// 取任务
	$batch_task = $redis->lPop($taskPreKey . 'list');

	$redis->close();

	if(empty($batch_task)) {
		// 50ms
		usleep(50000);
		continue;
	}

	$pid = $proc->fork(function($mypid) use($batch_task, $taskPreKey) {
		global $task_def, $gateway_url;

		outlog('data ' . $batch_task);

		$batch_task = json_decode($batch_task, true);

		// TODO 从数据库中取或者redis
		$task_name = $batch_task['task'];
		$task = $task_def[$task_name];

		$redis = getRedis();


		// 开始执行任务
		try {
			outlog('post gateway:' . json_encode($batch_task));

			$batch_task['params'] = json_encode($batch_task['params']);

			$ret = curl_post($gateway_url, $batch_task, 60 * 60);

			outlog('gateway ret:' . $ret);

			$ret_arr = json_decode($ret, true);
		} catch(Exception $e) {
			echo $e;
		}

		// 执行完后, 从执行池中去掉
		$redis->sRem($taskPreKey . 'pool', $batch_task['index']);

		outlog('finish ' . $batch_task['task'] . ':' . $batch_task['index']);

		$redis->close();

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


