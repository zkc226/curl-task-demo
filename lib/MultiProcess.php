<?php

/**
 * 多进程控制
 */
class MultiProcess {

	private $parentPid;
	private $childrenPid;
	private $supportPcntl=false;

	public function __construct() {
		$this->supportPcntl = extension_loaded('pcntl');

		// if(! $this->supportPcntl) {
		// 	echo 'need pcntl extension' . PHP_EOL;
		// 	exit;
		// }

		$this->parentPid = getmypid();
	}

	public function getChildrenPid() {
		return $this->childrenPid;
	}

	public function getParentPid() {
		return $this->parentPid;
	}

	/**
	 * fork 子进程
	 * @param  callback $childCallback 回调方法
	 * @return int                     -1: 失败, >0 子进程编号
	 */
	public function fork($childCallback) {
		if($this->supportPcntl) {
			$pid = pcntl_fork();
		} else {
			$pid = 0;
		}

		if($pid == -1) {
			// fork fail

		} elseif($pid == 0) {
			// echo $pid . PHP_EOL;
			// child process
			$childPid = getmypid();

			$childCallback($childPid);
			exit;
		} else {
			$this->childrenPid[$pid] = time();
		}
		return $pid;
	}

	public function clean() {
		if(! empty($this->childrenPid)) {
			foreach ($this->childrenPid as $cpid => $cval) {
				$status = 0;
				$tpid = pcntl_waitpid($cpid, $status, WNOHANG);
				if($tpid>0) {
					unset($this->childrenPid[$tpid]);
				}
			}
		}
	}

	public function wait($num=0) {
		if(! $this->supportPcntl) {
			return true;
		}
		// wait
		// while(true && count($this->childrenPid) > 0) {
		// 	if(count($this->childrenPid) <= $num) {
		// 		break;
		// 	}
		// 	$status = 0;
		// 	$tpid = pcntl_wait($status, WNOHANG);
		// 	if($tpid>0) {
		// 		unset($this->childrenPid[$tpid]);
		// 		// echo $tpid, ':', $status . PHP_EOL;
		// 		$remain = count($this->childrenPid);
		// 		if($remain <= $num) {
		// 			// echo 'children process num:' .$remain. PHP_EOL;

		// 			break;
		// 		}
		// 	} else {
		// 		// sleep(1);
		// 		usleep(50000);
		// 	}
		// }
		while(true && count($this->childrenPid) > 0) {
			if(count($this->childrenPid) <= $num) {
				break;
			}

			foreach ($this->childrenPid as $cpid => $cval) {

				$status = 0;
				$tpid = pcntl_waitpid($cpid, $status, WNOHANG);
				if($tpid>0) {
					unset($this->childrenPid[$tpid]);
					// echo $tpid, ':', $status . PHP_EOL;
					$remain = count($this->childrenPid);
					if($remain <= $num) {
						// echo 'children process num:' .$remain. PHP_EOL;

						break;
					}
				} else {
					// sleep(1);
				}
			}

			if(count($this->childrenPid) <= $num) {
				break;
			}

			usleep(50000);
		}
	}

}