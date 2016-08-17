<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');

function date_str() {
	$mtime_str = explode(' ', microtime());
	list($s, $ms) = each($mtime_str);
	$ms = $ms * 10000;
	$str = date('Y-m-d H:i:s') . '.' . sprintf('%04d', $ms);

	return $str;
}

function outlog($log, $out=false) {
	static $hostname = '';
	if(!$hostname) {
		$hostname = gethostname();
	}

	$pid = getmypid();

	$ll = date_str() ."|[server=".$hostname. ',pid=' .$pid. "]|". $log . PHP_EOL;

	if($out) {
		echo $ll;
	}

	// 日志路径
	$log_path = realpath(__DIR__ . '/../') . '/logs/' . date('Y/m/d/');

	if(!file_exists($log_path)) {
		if(! mkdir($log_path, 0777, true)) {
			echo 'can not mkdir ' . $log_path . PHP_EOL;
			die;
		}
	}

	$log_prefix = '';
	if(defined('TASK_LOG_PREFIX')) {
		$log_prefix = TASK_LOG_PREFIX;
	}

	if($log_prefix && ! preg_match('/-$/is', $log_prefix)) {
		$log_prefix .= '-';
	}

	file_put_contents($log_path . $log_prefix . date('Ymd'). '.log', $ll, FILE_APPEND);
}



/**
 * 接口请求
 * @param $arr 请求array
 * @param $reqtype    请求类型
 * @param $method  请求方式默认post
 * @param $timeout 超时
 * @return 返回请求数据
 */
function curl_post($curl, $arr, $method='POST',$timeout=30){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$curl);
	curl_setopt($ch , CURLOPT_RETURNTRANSFER , TRUE);
	curl_setopt($ch , CURLOPT_SSL_VERIFYPEER , FALSE); // 对认证证书来源的检查
	curl_setopt($ch , CURLOPT_SSL_VERIFYHOST , FALSE); // 从证书中检查SSL加密算法是否存在
	curl_setopt($ch , CURLOPT_CONNECTTIMEOUT , $timeout);
	// curl_setopt($ch, CURLOPT_HTTPHEADER, array ("Content-Type: application/xml; charset=utf-8",));
	switch ($method){
		case "POST":
			curl_setopt($ch, CURLOPT_POST,true);//设置POST方式
			break;
		default:
			break;        //默认是GET
	}
	curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}

/**
 * 计算最佳每批任务个数
 * @param  int $total         总任务数
 * @param  int $concurrncy    同时执行任务数（批）
 * @param  int $max_batch_num 最大每批任务数
 * @param  int $min_batch_num 最小每批任务数
 * @return int
 */
function suitBatchNum($total, $concurrncy, $max_batch_num, $min_batch_num) {
	$batch_num = ceil($total / $concurrncy);
	if($batch_num > $max_batch_num) {
		$batch_num = $max_batch_num;
	}
	if($batch_num < $min_batch_num) {
		$batch_num = $min_batch_num;
	}

	return $batch_num;
}

/**
 * 等待队列空闲线程
 * @param  string $taskPreKey 任务前缀
 * @param  int $valid_num  可用线程数
 * @return
 */
function waitValid($taskPreKey, $valid_num, $pool_size) {
	$redis = getRedis();

	while(true) {
		// 当前执行中子任务数目
		$running = $redis->sCard($taskPreKey . 'pool');
		// 可用线程数：总并发数-正在执行的数目
		if($pool_size - $running >= $valid_num) {
			break;
		}
		// sleep 100ms
		usleep(1000*100);
	}

	$redis->close();
}

/**
 * 加入到任务队列（此处控制并发）
 * @param string  $taskPreKey      子任务队列名称前缀
 * @param array  $batch_task_temp 子任务数据模板
 * @param array  $data            数据
 * @param integer $concurrncy      并发数限制
 * @param integer $batch_num       每批次任务数
 */
function addToPool($taskPreKey, $batch_task_temp, $data, $concurrncy=10, $batch_num=10) {
	// 拆分，保留键名
	$chunks = array_chunk($data, $batch_num, true);

	$redis = getRedis();

	// 子任务编号
	$index = 0;

	foreach($chunks as $c) {
		waitValid($taskPreKey, 1, $concurrncy);

		$index++;

		$batch_task = $batch_task_temp;
		$batch_task['index'] = $index;
		// 注意这里，要把这批数据当做第一个参数传递过去
		$batch_task['params'] = array($c);

		// 将子任务编号加入到执行池（控制并发）
		$redis->sAdd($taskPreKey . 'pool', $index);
		// 将子任务数据加入任务队列
		$redis->rPush($taskPreKey . 'list', json_encode($batch_task));
		// 全局子任务队列，此处只放子任务队列前缀名称，子任务消费者统一监听此队列
		$redis->rPush('task:queue:data', $taskPreKey);

	}

	$redis->close();

	// 一直到任务结束（执行池中午任务）
	waitValid($taskPreKey, $concurrncy, $concurrncy);

}












