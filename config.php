<?php

// TODO 这部分可以入库或者redis
$task_def = [
	// 任务名称
	'task1' => [
		// 获取数据的服务
		'data_svc' => 'task1/start',
		// 分批处理的服务
		'batch_svc' => 'task1/batch',
		'concurrncy' => 10,
		'max_batch_num' => 5,
		'min_batch_num' => 2
	]
];

$gateway_url = 'http://localhost/curl-task-demo/gateway.php';

function getRedis() {
	$redis = new Redis();
 // 此处不要指定太短的超时时间, 会影响blPop指定的超时时间, 这里是全局的超时时间
	$redis->pconnect('127.0.0.1', 6379);
	$redis->select(7);

	return $redis;
}
