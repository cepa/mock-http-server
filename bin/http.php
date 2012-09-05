<?php

class Debug
{
    
    protected function __construct()
    {
        openlog('php-http-server', null, LOG_PERROR | LOG_SYSLOG);
    }
    
    public static function getInstance()
    {
        static $instance;
        if (!isset($instance)) {
            $instance = new self();
        }
        return $instance;
    }
    
    public static function log($message)
    {
        return self::getInstance()->write($message);
    }
    
    public function write($message)
    {
        if (is_array($message) || (is_object($message) && !method_exists($message, '__toString'))) {
            $message = var_export($message, true);
        }
        syslog(LOG_DEBUG, $message);
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}]: {$message}\n";
    }
    
}

abstract class Socket
{
    
    protected $_sock;
    
    public function __construct($sock = null)
    {
        $this->setSock($sock);
    }
    
    public function setSock($sock)
    {
        $this->_sock = $sock;
        return $this;
    }
    
    public function getSock()
    {
        return $this->_sock;
    }
    
    public function close()
    {
        if (socket_close($this->getSock()) === false) {
            throw new Exception("socket_close failed: ".$this->getError());
        }
        return $this;
    }
    
    public function write($buf, $length = null)
    {
        if (!isset($length)) {
            $length = strlen($buf);
        }
        if (socket_write($this->getSock(), $buf, $length) === false) {
            throw new Exception("socket_close failed: ".$this->getError());
        }    
        return $this;
    }
    
    public function read($length, $type = PHP_BINARY_READ)
    {
        $buf = socket_read($this->getSock(), $length, $type);
        if ($buf === false) {
            throw new Exception("socket_read failed: ".$this->getError());
        }
        return $buf;
    }
    
    public function setNonBlock()
    {
        socket_set_nonblock($this->getSock());
        return $this;
    }
    
    public function setOption($level, $optname, $optval)
    {
        if (socket_set_option($this->getSock(), $level, $optname, $optval) === false) {
            throw new Exception("socket_set_option failed: ".$this->getError());
        }
        return $this;
    }
    
    public function getError()
    {
        return socket_strerror(socket_last_error($this->getSock()));
    }
    
}

class ServerSocket extends Socket
{
    
    public function create($domain, $type, $protocol)
    {
        $sock = socket_create($domain, $type, $protocol);
        if ($sock === false) {
            throw new Exception("socket_create failed: ".$this->getError());
        }
        return $this->setSock($sock);
    }
    
    public function bind($address, $port)
    {
        if (socket_bind($this->getSock(), $address, $port) === false) {
            throw new Exception("socket_bind failed: ".$this->getError());
        }
        return $this;
    }
    
    public function listen($backlog = 0)
    {
        if (socket_listen($this->getSock(), $backlog) === false) {
            throw new Exception("socket_listen failed: ".$this->getError());
        }
        return $this;
    }
    
    public function accept()
    {
        $sock = @socket_accept($this->getSock());
        if ($sock === false) {
            return false;
        }
        return new ClientSocket($sock);
    }
    
}

class ClientSocket extends Socket
{
    
    public function getRemoteAddress()
    {
        $address = 0;
        if (socket_getpeername($this->getSock(), $address) === false) {
            throw new Exception("socket_accept failed: ".$this->getError());
        }
        return $address;
    }
    
}

class HttpRequest
{
    
    protected $_socket;
    protected $_method;
    protected $_uri;
    protected $_query;
    protected $_headers = array();
    protected $_body;
    
    public function __construct($input, $skipFirstLine = false)
    {
        $headerEnd = strpos($input, "\r\n\r\n");
        $headerLines = explode("\r\n", substr($input, 0, $headerEnd));
        
        $i = 0;
        if (!$skipFirstLine) {
            list($this->_method, $uri) = sscanf($headerLines[$i++], "%s %s");
            $queryPos = strpos($uri, '?');
            if ($queryPos !== false) {
                $this->_uri = substr($uri, 0, $queryPos);
                $this->_query = substr($uri, $queryPos + 1);
            } else {
                $this->_uri = $uri;
            }
        }
        
        for ($n = count($headerLines); $i < $n; $i++) {
            $p = strpos($headerLines[$i], ': ');
            $name = substr($headerLines[$i], 0, $p);
            $value = substr($headerLines[$i], $p + 2);
            $this->_headers[$name] = $value;
        }
        
        $this->_body = substr($input, $headerEnd + 4);
    }
    
    public function getMethod()
    {
        return $this->_method;
    }
    
    public function getUri()
    {
        return $this->_uri;
    }
    
    public function getQuery()
    {
        return $this->_query;
    }
    
    public function getHeaders()
    {
        return $this->_headers;
    }
    
    public function getHeader($name)
    {
        return (isset($this->_headers[$name]) ? $this->_headers[$name] : null);
    }
    
    public function getBody()
    {
        return $this->_body;
    }
    
}

class HttpResponse
{
    
    protected $_httpProto = 'HTTP/1.1';
    protected $_statusCode = 200;
    protected $_statusMessage = 'OK';
    protected $_headers = array();
    protected $_body;
    
    public function __construct()
    {
        
    }
    
    public function setStatusCode($code)
    {
        $this->_statusCode = $code;
        return $this;
    }
    
    public function getStatusCode()
    {
        return $this->_statusCode;
    }
    
    public function setStatusMessage($message)
    {
        $this->_statusMessage = $message;
        return $this;
    }
    
    public function getStatusMessage()
    {
        return $this->_statusMessage;
    }
    
    public function setHeader($name, $value)
    {
        $this->_headers[$name] = $value;
        return $this;
    }
    
    public function getHeader($name)
    {
        return (isset($this->_headers[$name]) ? $this->_headers[$name] : null);
    }
    
    public function setBody($body)
    {
        $this->_body = $body;
        return $this;
    }
    
    public function getBody()
    {
        return $this->_body;
    }
    
    public function render()
    {
        $output = $this->_httpProto.' '.$this->getStatusCode().' '.$this->getStatusMessage()."\r\n";
        foreach ($this->_headers as $name => $value) {
            $output .= "{$name}: {$value}\r\n";
        }
        $output .= "\r\n".$this->getBody();
        return $output;
    }
    
}

class HtmlPage extends HttpResponse
{
    
    public function __construct($body = null)
    {
        parent::__construct();
        $this
            ->setHeader('Server', 'PHP Http Server')
            ->setHeader('Connection', 'Close')
            ->setHeader('Content-Type', 'text/html');
        $this->setBody($body);
    }
    
    public function render()
    {
        $this->setHeader('Content-Length', strlen($this->getBody()));
        return parent::render();
    }
    
}

class DirectoryPage extends HtmlPage
{
    
    public function __construct($uri, $path)
    {
        $files = $this->listDirectory($path);
        $body = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';
        $body .= "<html><head></head><body>\n";
        
        if (trim($uri, '/') != '') {
            $fileUri = '/'.trim(dirname($uri), '/');
            $body .= '<a href="'.$fileUri.'">..</a><br />'."\n";
        }
        
        foreach ($files as $file) {
            $fileUri = rtrim($uri, '/').'/'.$file;
            $body .= '<a href="'.$fileUri.'">'.$file.'</a><br />'."\n";
        }
        
        $body .= '</body></html>';
        parent::__construct($body);
    }
    
    public function listDirectory($path)
    {
        $files = array();
        $handle = opendir($path);
        while (($file = readdir($handle)) !== false) {
            if ($file != '.' && $file != '..') {
                $files[] = $file;
            }
        } 
        closedir($handle);
        return $files;
    }
    
}

class CgiPage extends HtmlPage
{
    
    public function __construct(array $env = array())
    {
        $envStr = '';
        foreach ($env as $name => $value) {
            $envStr .= $name.'="'.$value.'" ';
        }
        
        $stdout = shell_exec($envStr.' php-cgi -d cgi.force_redirect=0 ');
        
        $cgiResponse = new HttpRequest($stdout);
        foreach ($cgiResponse->getHeaders() as $name => $value) {
            $this->setHeader($name, $value);
        }
        parent::__construct($cgiResponse->getBody());
    }
    
}

class HttpServer
{
    
    private $_isKilled = false;
    protected $_address = '0.0.0.0';
    protected $_port = 10080;
    protected $_webDir;
    
    public function setAddress($address)
    {
        return $this->_address = $address;
    }
    
    public function getAddress()
    {
        return $this->_address;
    }

    public function setPort($port)
    {
        $this->_port = $port;
        return $this;
    }

    public function getPort()
    {
        return $this->_port;
    }
    
    public function setWebDir($dir)
    {
        $this->_webDir = rtrim($dir, '/');
        return $this;
    }
    
    public function getWebDir()
    {
        return $this->_webDir;
    }
    
    public function run()
    {
        Debug::log("PHP HTTP Server start...");
        
        $this->_socket = new ServerSocket();
        $this->_socket
            ->create(AF_INET, SOCK_STREAM, SOL_TCP)
            ->setOption(SOL_SOCKET, SO_REUSEADDR, 1)
            ->bind($this->getAddress(), $this->getPort())
            ->listen(10)
            ->setNonBlock();
        
        Debug::log("Waiting for incoming connections...");
        
        do {
            $clientSocket = $this->_socket->accept();
            if ($clientSocket) {
                $request = new HttpRequest($clientSocket->read(8192));
                
                $path = $this->getWebDir().'/'.$request->getUri();
                if (file_exists($path)) {
                    if (is_file($path)) {
                        if ($this->getFilenameExtension($path) == 'php') {
                            $env = array(
                                'SCRIPT_FILENAME' => $path,
                                'REQUEST_METHOD' => $request->getMethod(),
                                'REQUEST_URI' => $request->getUri(),
                                'QUERY_STRING' => $request->getQuery(),
                            );
                            $response = new CgiPage($env);
                        } else {
                            $contents = @file_get_contents($path);
                            $response = new HtmlPage($contents);
                            $response->setHeader('Content-Type', $this->getMimeType($path));
                        }
                    } else {
                        $response = new DirectoryPage($request->getUri(), $path);
                    }
                } else {
                    $response = new HtmlPage('Error 404');
                    $response
                        ->setStatusCode('404')
                        ->setStatusMessage('Not Found');
                }

                $render = $response->render();
                Debug::log(
                    $clientSocket->getRemoteAddress().
                    ': "'.$request->getMethod().
                    ' '.$request->getUri().
                    '" '.$response->getStatusCode().
                    ' '.$response->getHeader('Content-Length').
                    ' "'.$request->getHeader('User-Agent').'"');
                
                $clientSocket->write($render);
                
                $clientSocket->close();
            }
            $this->wait();
        } while (!$this->_isKilled);
    }
    
    public function kill()
    {
        Debug::log("Server stopped!");
        $this->_isKilled = true;
        $this->_socket->close();
        usleep(500);
    }
    
    public function wait()
    {
        usleep(1);
        return $this;
    }
    
    public function getMimeType($filename)
    {
        $ext = $this->getFilenameExtension($filename);
        $mimeTypes = @include 'mimetype.php';
        if (!is_array($mimeTypes) || !isset($mimeTypes[$ext])) {
            return 'text/html';
        }  
        return $mimeTypes[$ext];
    }
    
    public function getFilenameExtension($filename)
    {
        $pos = strrpos($filename, '.');
        if ($pos === false)
            return 'text/html';
        return strtolower(trim(substr($filename, $pos), '.'));
    }
    
}

