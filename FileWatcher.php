<?php
/**
 * @author Michael Kliewe
 * @copyright 2011 Michael Kliewe
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link http://www.phpgangsta.de/
 */
class FileWatcher
{
    /**
     * @var array
     */
    protected $_config;
    /**
     * @var string
     */
    protected $_providedPassword;
    /**
     * @var string
     */
    protected $_providedOverallHash;

    /**
     * @param array $options
     */
    public function __construct($options = array())
    {
        if (isset($options['configFilename'])) {
            $this->readConfig($options['configFilename']);
        }
    }

    /**
     * @param $configFilename
     * @return FileWatcher
     */
    public function readConfig($configFilename)
    {
        include $configFilename;

        $this->_config = $config;
        $this->_log('Config file loaded');

        if (php_sapi_name() == "cli") {
            $longopts = array(
                'password::',    // Optional value
                'overallHash::', // Optional value
            );
            $options = getopt('', $longopts);

            if (isset($options['password'])) {
                $this->_providedPassword = $options['password'];
            }
            if (isset($options['overallHash'])) {
                $this->_providedOverallHash = $options['overallHash'];
            }
        } else { // not in cli-mode, try to get password from $_GET or $_POST
            if (isset($_POST['password'])) {
                $this->_providedPassword = $_POST['password'];
            } elseif($_GET['password']) {
                $this->_providedPassword = $_GET['password'];
            }

            if (isset($_POST['overallHash'])) {
                $this->_providedOverallHash = $_POST['overallHash'];
            } elseif($_GET['overallHash']) {
                $this->_providedOverallHash = $_GET['overallHash'];
            }
        }

        return $this;
    }

    /**
     * @return FileWatcher
     */
    public function checkNow()
    {
        $this->_log('Checking password...');
        $this->_checkPassword();

        $this->_log('Checking files...');
        $currentHashes = $this->_getCurrentHashes();

        $masterHashes = $this->_loadMasterHashes();

        // compare both hash arrays
        $newFiles     = array_diff_key($currentHashes, $masterHashes);
        $deletedFiles = array_diff_key($masterHashes, $currentHashes);

        $changedFiles = array();
        $intersectKeys = array_keys(array_intersect_key($masterHashes, $currentHashes));
        foreach ($intersectKeys as $intersectKey) {
            if ($masterHashes[$intersectKey] != $currentHashes[$intersectKey]) {
                $changedFiles[$intersectKey] = $masterHashes[$intersectKey];
            }
        }

        $overallHash = $this->_calcOverallHash($newFiles, $deletedFiles, $changedFiles);
        $this->_log('Overall Hash is: '.$overallHash);

        if (count($newFiles) > 0 || count($deletedFiles) > 0 || count($changedFiles) > 0 || (!empty($this->_providedOverallHash) && $overallHash != $this->_providedOverallHash)) {
            $this->_log('Sending alerts now...');
            $this->_logAlert($newFiles, $deletedFiles, $changedFiles);
            $this->_outputAlert($newFiles, $deletedFiles, $changedFiles);
            $this->_emailAlert($newFiles, $deletedFiles, $changedFiles);

            // save new master hash file if needed
            if ($this->_config['overwriteMasterFile']) {
                $this->_saveMasterHashes($currentHashes);
            }
        } else {
            $this->_log('Everything OK, exiting...');
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function _getCurrentHashes() {
        $currentHashes = array();

        foreach ($this->_config['includePaths'] as $includePath) {

            $iterator = new RecursiveDirectoryIterator($includePath);
            foreach(new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                /** @var $file SplFileInfo */

                // check against excludeFolderList
                if (!$this->_isPathExcluded($file->getPathname(), $this->_config['excludeFolderList'])) {
                    if (!$file->isDir()) {
                        // check against excludeExtensionList
                        $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
                        if (!in_array(strtolower($extension), $this->_config['excludeExtensionList'])) {
                            $hash = sha1_file($file->getPathname());
                            $currentHashes[$file->getPathname()] = $hash;
                            $this->_log('Found file '.$file->getPathname().' with sha1: '.$hash);
                        } else {
                            $this->_log('File skipped: '.$file->getPathname());
                        }
                    }
                } else {
                    $this->_log('Directory skipped: '.$file->getPathname());
                }
            }
        }

        return $currentHashes;
    }

    /**
     * @return FileWatcher
     */
    protected  function _checkPassword()
    {
        if (!empty($this->_config['password']) && $this->_config['password'] != $this->_providedPassword) {
            die('wrong password, exiting now...');
        }

        return $this;
    }

    /**
     * @param array $files
     * @return array
     */
    protected function _createAllHashesOfFilelist(array $files) {
        $list = array();
        foreach ($files as $file) {
            $list[$file] = sha1_file($file);
        }

        return $list;
    }

    /**
     * @param array $files
     */
    protected function _saveMasterHashes(array $files) {
        $fileContent = '';
        foreach ($files as $key => $filepath) {
            $fileContent .= $key.'='.$filepath."\n";
        }

        $fh = fopen($this->_config['hashMasterFilename'], 'w');
        fwrite($fh, $fileContent);
        fclose($fh);
    }

    /**
     * @return array
     */
    protected function _loadMasterHashes() {
        $masterFilePath = $this->_config['hashMasterFilename'];
        if (!file_exists($masterFilePath)) {
            return array();
        }

        $hashes = array();
        $masterFileLines = file($masterFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($masterFileLines as $masterFileLine) {
            list($filepath, $hash) = explode('=', $masterFileLine);
            $hashes[$filepath] = $hash;
        }

        return $hashes;
    }

    /**
     * Build the overallHash over all single file hashes
     *
     * @param array $newFiles
     * @param array $deletedFiles
     * @param array $changedFiles
     * @return string
     */
    protected function _calcOverallHash(array $newFiles, array $deletedFiles, array $changedFiles)
    {
        $merged = array_merge($newFiles, $deletedFiles, $changedFiles);

        return sha1(join(',',$merged));
    }

    /**
     * @param array $newFiles
     * @param array $deletedFiles
     * @param array $changedFiles
     */
    protected function _emailAlert(array $newFiles, array $deletedFiles, array $changedFiles)
    {
        $body = 'new: '.var_export($newFiles, true)."\n".
                'deleted: '.var_export($deletedFiles, true)."\n".
                'changed: '.var_export($changedFiles, true)."\n";
        if ($this->_config['alertEmailMethod'] == 'mail') {
            mail(
                $this->_config['alertEmailAddress'],
                $this->_config['alertEmailSubject'],
                $body
            );
        } elseif ($this->_config['alertEmailMethod'] == 'smtp') {
            require_once('PHPMailer_5.2.0/class.phpmailer.php');

            // oh man, using PHPMailer is really ugly, but it seems to work

            $mail             = new PHPMailer();
            $mail->IsSMTP(); // telling the class to use SMTP
            $mail->SMTPDebug  = false;                     // enables SMTP debug information (for testing)
                                                       // 1 = errors and messages
                                                       // 2 = messages only
            $mail->SMTPAuth   = true;                  // enable SMTP authentication
            $mail->Host       = $this->_config['alertEmailSmtpServer']; // sets the SMTP server
            $mail->Port       = 25;                    // set the SMTP port for the GMAIL server
            $mail->Username   = $this->_config['alertEmailSmtpUser']; // SMTP account username
            $mail->Password   = $this->_config['alertEmailSmtpPass'];        // SMTP account password
            $mail->SetFrom($this->_config['alertEmailSmtpUser']);
            $mail->AddAddress($this->_config['alertEmailAddress']);
            $mail->Subject    = $this->_config['alertEmailSubject'];
            $mail->Body       = $body;
            $mail->Send();
        } else {
            throw new Exception('Invalid value for alertEmailMethod');
        }
    }

    /**
     * @param array $newFiles
     * @param array $deletedFiles
     * @param array $changedFiles
     */
    protected function _logAlert(array $newFiles, array $deletedFiles, array $changedFiles)
    {
        $body = 'new: '.var_export($newFiles, true)."\n".
                'deleted: '.var_export($deletedFiles, true)."\n".
                'changed: '.var_export($changedFiles, true)."\n";
        $this->_log($body);
    }

    /**
     * @param array $newFiles
     * @param array $deletedFiles
     * @param array $changedFiles
     * @return void
     */
    protected function _outputAlert(array $newFiles, array $deletedFiles, array $changedFiles)
    {
        $body = 'new: '.var_export($newFiles, true)."\n".
                'deleted: '.var_export($deletedFiles, true)."\n".
                'changed: '.var_export($changedFiles, true)."\n";
        echo $body;
    }

    /**
     * @param $fullFilename
     * @param array $pathArray
     * @return bool
     */
    protected function _isPathExcluded($fullFilename, array $pathArray) {
        foreach ($pathArray as $path) {
            $path = rtrim($path, '/\\');

            if (strpos($fullFilename, $path) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $message
     */
    protected function _log($message)
    {
        $fh = fopen($this->_config['logFilename'], 'a');
        fwrite($fh, date('Y-m-d H:i:s').' '.$message."\n");
        fclose($fh);
    }
}