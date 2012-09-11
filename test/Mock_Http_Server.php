<?php

/**
 * @author         cepa
 * @description    https://github.com/cepa/php-http-server
 */

class Mock_Http_Server
{

    protected $_port;
    protected $_binDir;
    protected $_webDir;
    protected $_pidFile;
    
    /**
     * Init mock server.
     * Choose random port and pid file.
     * @throws Exception
     */
    public function __construct()
    {
        if (!function_exists('pcntl_signal')) {
            throw new Exception("Missing pcntl module, please install it!");
        }
        
        if (!function_exists('socket_create')) {
            throw new Exception("Missing socket module, please install it!");
        }
        
        $port = 40000 + getmypid() % 20000;
        $pidFile = '/tmp/php-http-server-'.$port.'.pid';
        
        $this
            ->setPort($port)
            ->setPidFile($pidFile);
    }
    
    /**
     * Set port number.
     * @param int $port
     * @return Mock_Http_Server
     */
    public function setPort($port)
    {
        $this->_port = $port;
        return $this;
    }
    
    /**
     * Get port number.
     * @return int
     */
    public function getPort()
    {
        return $this->_port;
    }
    
    /**
     * Set directory with httpd script.
     * @param string $binDir
     * @return Mock_Http_Server
     */
    public function setBinDir($binDir)
    {
        $this->_binDir = $binDir;
        return $this;
    }
    
    /**
     * Get directory with httpd script.
     * @return string
     */
    public function getBinDir()
    {
        return $this->_binDir;
    }
    
    /**
     * Set public html directory.
     * @param string $webDir
     * @return Mock_Http_Server
     */
    public function setWebDir($webDir)
    {
        $this->_webDir = $webDir;
        return $this;
    }
    
    /**
     * Get public html directory.
     * @return string
     */
    public function getWebDir()
    {
        return $this->_webDir;
    }
    
    public function getBaseUrl()
    {
        return 'http://localhost:'.$this->getPort();
    }
    
    /**
     * Set PID file.
     * @param string $pidFile
     * @return Mock_Http_Server
     */
    public function setPidFile($pidFile)
    {
        $this->_pidFile = $pidFile;
        return $this;
    }
    
    /**
     * Get PID file.
     * @return string
     */
    public function getPidFile()
    {
        return $this->_pidFile;
    }
    
    /**
     * Start http server instance in background.
     * @return Mock_Http_Server
     */
    public function start()
    {
        shell_exec('cd '.$this->getBinDir().';nohup ./httpd -p '.$this->getPort().' -w '.$this->getWebDir().' -P '.$this->getPidFile().' > /dev/null 2>&1 < /dev/null &');
        usleep(300000);
        return $this;
    }
    
    /**
     * Stop http server instance.
     * @throws Exception
     * @return Mock_Http_Server
     */
    public function stop()
    {
        $pid = (int) @file_get_contents($this->getPidFile());
        if (!$pid) {
            throw new Exception("Cannot kill http server, invalid PID!");
        }
        shell_exec("kill ".$pid);
        return $this;
    }
    
}
