<?php

require_once 'Mock_Http_Server.php';

class HttpTest extends PHPUnit_Framework_TestCase
{

    public function testConnect()
    {
        $rootPath = dirname(dirname(__FILE__));
        
        $server = new Mock_Http_Server();
        $server
            ->setBinDir($rootPath.'/bin')
            ->setWebDir($rootPath.'/web')
            ->start();
        
        $url = $server->getBaseUrl().'/mock/json.php';
        $json = json_decode(file_get_contents($url));
        $this->assertEquals(123, $json->a);
        $this->assertEquals(456, $json->b);
                
        $server->stop();
    }

}
