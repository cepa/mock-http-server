<?php

require_once 'Mock_Http_Server.php';

class HttpTest extends PHPUnit_Framework_TestCase
{
    
    protected $_server;
    
    public function setUp()
    {
        $rootPath = dirname(dirname(__FILE__));
        
        $this->_server = new Mock_Http_Server();
        $this->_server
            ->setBinDir($rootPath.'/bin')
            ->setWebDir($rootPath.'/web')
            ->start();
    }
    
    public function tearDown()
    {
        $this->_server->stop();
    }
    
    public function testGet()
    {
        $url = $this->_server->getBaseUrl().'/mock/get.php';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        $this->assertEquals('ok', $response);
        $this->assertEquals(200, $httpCode);
        $this->assertEquals(2, $contentLength);
        $this->assertEquals('text/html', $contentType);
    }

}
