<?php


class FileLock {

	private $lockFile;
	private $lockFileHandle;

	public function __construct($file='') {
		if(empty($file)) {
			$pathinfo = pathinfo($_SERVER['SCRIPT_NAME']);

			$file = '/tmp/.' . $pathinfo['filename'] . '.lock';
		}
		$this->lockFile = $file;
	}

	public function lock($block=false) {
		// 锁定
		$this->lockFileHandle = fopen($this->lockFile, 'w+');
		if($this->lockFileHandle === false) {
			// out("open lock file fail");
			// exit(1);
			return false;
		}
		$lockType = $block?LOCK_EX:LOCK_EX|LOCK_NB;
		if(! flock($this->lockFileHandle, $lockType)) {
			return false;
		}

		fwrite($this->lockFileHandle, microtime(true));

		return true;
	}

	public function unlock() {
		// 解锁
		flock($this->lockFileHandle, LOCK_UN);
		fclose($this->lockFileHandle);
	}

}