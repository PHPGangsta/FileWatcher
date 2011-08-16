<?php

require_once 'FileWatcher.php';

$fileWatcher = new FileWatcher();
$fileWatcher->readConfig('FileWatcher.config.php')
            ->checkNow();