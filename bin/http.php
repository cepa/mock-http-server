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
    protected $_headers = array();
    protected $_body;
    
    public function __construct($input)
    {
        $headerEnd = strpos($input, "\r\n\r\n");
        $headerLines = explode("\r\n", substr($input, 0, $headerEnd));
        list($this->_method, $this->_uri) = sscanf($headerLines[0], "%s %s");
        for ($n = count($headerLines), $i = 1; $i < $n; $i++) {
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
    
    public function getHeaders()
    {
        return $this->_headers;
    }
    
    public function getHeader($name)
    {
        return (isset($this->_headers[$name]) ? $this->_headers[$name] : null);
    }
    
}

class HttpResponse
{
    
    protected $_statusLine = 'HTTP/1.1 200 OK';
    protected $_headers = array();
    protected $_body;
    
    public function __construct()
    {
        
    }
    
    public function setStatusLine($statusLine)
    {
        $this->_statusLine = $statusLine;
        return $this;
    }
    
    public function getStatusLine()
    {
        return $this->_statusLine;
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
        $output = $this->getStatusLine()."\r\n";
        foreach ($this->_headers as $name => $value) {
            $output .= "{$name}: {$value}\r\n";
        }
        $output .= "\r\n".$this->getBody();
        return $output;
    }
    
}

class HttpPage extends HttpResponse
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
        $this->_webDir = $dir;
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
            ->bind($this->getAddress(), $this->getPort())
            ->listen(10)
            ->setNonBlock();
        
        Debug::log("Waiting for incoming connections...");
        
        do {
            $clientSocket = $this->_socket->accept();
            if ($clientSocket) {
                $request = new HttpRequest($clientSocket->read(8192));
                
                Debug::log($clientSocket->getRemoteAddress().': "'.$request->getMethod().' '.$request->getUri().'"');
                
                $response = new HttpPage("index");
                $clientSocket->write($response->render());
                
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
    
}

