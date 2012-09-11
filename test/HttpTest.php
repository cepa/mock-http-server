<?php

require_once 'Mock_Http_Server.php';

class HttpTest extends PHPUnit_Framework_TestCase
{

    public function testSimpleGet()
    {
        $rootPath = dirname(dirname(__FILE__));
        
        $server = new Mock_Http_Server();
        $server
            ->setBinDir($rootPath.'/bin')
            ->setWebDir($rootPath.'/web')
            ->start();
        
        $response = file_get_contents($server->getBaseUrl().'/mock/get.php');
        $this->assertEquals('ok', $response);
                
        $server->stop();
    }

}
