<?php

// http://localhost/local/learn/task/dtask/curl-task-demo/gateway.php
// var_dump($_POST);
// die;
$svc = $_POST['svc'];
$params = json_decode($_POST['params'], true);
$params = $params===false?[]:$params;

$svc_func = str_replace('/', '_', $svc);
$svc_file = $svc . '.php';

include $svc_file;

// 规定，生产者返回数据 数组
// 消费者。。。随意

$data = call_user_func_array($svc_func, $params);

// var_dump($data);

echo json_encode($data);