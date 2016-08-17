<?php


class SignalHandler {

	static $isDeclare = false;
	static $handlers = array();

	public static function handle($signo) {
		if(isset(self::$handlers[$signo])) {
			foreach(self::$handlers[$signo] as $callback) {
				if(is_callable($callback) || is_string($callback)) {
					$callback($signo);
				} else {
					call_user_func_array($callback, array($signo));
				}
			}
		} else {
			echo "unknow:" . $signo;
		}
	}

	public static function addHandler($signo, $callback) {

		if(!self::$isDeclare) {
			self::$isDeclare = true;
			//信号处理需要注册ticks才能生效，这里务必注意
			//PHP5.4以上版本就不再依赖ticks了
			declare(ticks = 1);
		}

		if(!isset($handlers[$signo])) {
			pcntl_signal($signo, 'SignalHandler::handle');
			self::$handlers[$signo] = array();
		}
		self::$handlers[$signo][] = $callback;

	}

	public static function addExitHanlder($callback) {
		$signos = array(
			SIGUSR1,
			SIGHUP,
			SIGQUIT,
			// SIGINT mac 下导致 segmentation fault
			// SIGINT,
			// SIGKILL,
			SIGTERM,
			SIGSYS,
		);
		foreach($signos as $signo) {
			SignalHandler::addHandler($signo, $callback);
		}
	}


}