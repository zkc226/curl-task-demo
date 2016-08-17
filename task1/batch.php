<?php


function task1_batch($data) {
	$ret = [];

	foreach($data as $k=>$d) {
		$ret[$k] = 1;
	}
	sleep(1);
	return $ret;
}