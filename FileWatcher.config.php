<?php

$config = array(
    'password'              => 'secretPassword',
    'includePaths'          => array('/tmp'),    // put a list of absolute or relative paths here
    'excludeFolderList'     => array('/tmp/exclude'),
    'excludeExtensionList'  => array('jpg', 'png', 'pdf'),

    'hashMasterFilename'    => 'FileWatcher.MasterHashes.txt',
    'logFilename'           => 'FileWatcher.log',
    'overwriteMasterFile'   => true,

    'alertEmailAddress'     => 'alerts@domain.de',
    'alertEmailSubject'     => 'ALERT: File hashes on your server have changed',
    'alertEmailMethod'      => 'mail',  // mail or smtp
    'alertEmailSmtpServer'  => '1.2.3.4',
    'alertEmailSmtpUser'    => 'XXXX',
    'alertEmailSmtpPass'    => 'XXXX',
);