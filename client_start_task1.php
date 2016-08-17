<?php

/*

客户端发起启动task1的任务

发布启动任务task1的消息

*/
require 'autoload.php';

$redis = getRedis();

$data = [
	'task'   => 'task1',
	'svc'	 => $task_def['task1']['data_svc'],
	'params' => ['1', '100'],
];

// TODO mysql 状态设置为1

// 插入控制队列
$redis->rPush('task:queue:ctrl', json_encode($data));

$redis->close();