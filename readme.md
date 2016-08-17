基于pcntl多进程分布式任务demo

扩展要求

redis

pcntl（不支持windows）


MultiProcess.php 已经做了兼容windows的处理，在windows下，每个consumer守护进程同时只能“fork”启动一个子进程


mysql部分未实现已加TODO


关于gateway_url

分布式部署的时候，每台服务器上面将gateway_url的host绑定到127.0.0.1本机ip即可实现任务分发

或者依赖F5，nginx等负载均衡设备（策略可以是  轮训，最小连接数优先）

目录结构

```
├── autoload.php                 自动加载脚本
├── bin                          后台守护进程目录
│   ├── task-consumer.php        生产者任务守护进程
│   └── task-sub-consumer.php    消费者任务守护进程
├── config.php                   配置文件
├── lib
│   ├── FileLock.php             文件锁，保证任务单实例
│   ├── MultiProcess.php         pcntl封装，未安装pcntl扩展的或windows系统也可以运行
│   ├── SignalHandler.php        信号处理，防止执行中的任务被中断
│   └── console-func.php         公用方法
├── logs
├── gateway.php                  代理网关，任务调用
├── client_start_task1.php       启动task1
└── task1                        task1实现
    ├── batch.php
    └── start.php
```

启动的时候，以下2个任务在后台启动，可以借助supervisord等工具来实现，或者使用crontab 没隔一段时间启动一次，代码内做了循环够一定次数后退出的设置，并且限制单例启动，supervisord可以自动重启
php -f bin/task-consumer.php
php -f bin/task-sub-consumer.php


启动task1执行
php -f client_start_task1.php


任务调度规则

此处的调用规则可以自己实现，

执行流程：
1、client_start_task1.php 发布task1任务到task:queue:ctrl控制队列（生产者队列）
2、task-consumer.php 监听task:queue:ctrl到数据后
2.1、引入task1/start.php文件    执行task1_start方法
2.2、将返回的任务放入子任务队列task:queue:data，并且限制并发
3、task-sub-consumer.php 监听task:queue:data，取到子任务队列前缀后去相应的list取数据，执行完毕后将pool对应的index取出
4、循环执行2.2直到数据执行完毕






