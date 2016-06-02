<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function Freebox_OS_install() {
	Freebox_OS::CreateArchi();
	$cron = cron::byClassAndFunction('Freebox_OS', 'RefreshInformation');
	if (!is_object($cron)) {
		$cron = new cron();
		$cron->setClass('Freebox_OS');
		$cron->setFunction('RefreshInformation');
		$cron->setEnable(1);
		$cron->setSchedule('* * * * *');
		$cron->save();
	}
}

function Freebox_OS_update() {
	Freebox_OS::CreateArchi();    
	$cron = cron::byClassAndFunction('Freebox_OS', 'RefreshInformation');
	if (!is_object($cron)) {
		$cron = new cron();
		$cron->setClass('Freebox_OS');
		$cron->setFunction('RefreshInformation');
		$cron->setEnable(1);
		$cron->setSchedule('* * * * *');
		$cron->save();
	}
}

function Freebox_OS_remove() {
	$cron = cron::byClassAndFunction('Freebox_OS', 'RefreshInformation');
    if (is_object($cron)) {
        $cron->remove();
    }
}

?>