<?php

namespace Jackalope\Transport\Jackrabbit;

use Doctrine\Common\Cache\ArrayCache;
use Jackalope\Factory;
use Jackalope\Test\JackrabbitTestCase;

class CachedClientTest extends JackrabbitTestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $cacheMock;

    public function setUp()
    {
        $this->cacheMock = $this->getMock('\Doctrine\Common\Cache\ArrayCache');

        parent::setUp();
    }

    public function getTransportMock($args = 'testuri', $changeMethods = array())
    {
        $factory = new Factory;
        //Array XOR
        $defaultMockMethods = array('getRequest', '__destruct', '__construct');
        $mockMethods = array_merge(array_diff($defaultMockMethods, $changeMethods), array_diff($changeMethods, $defaultMockMethods));

        return $this->getMock(
            __NAMESPACE__.'\CachedClientMock',
            $mockMethods,
            array($factory, $args, array ('meta' => $this->cacheMock))
        );
    }

    public function getRequestMock($response = '', $changeMethods = array())
    {
        $defaultMockMethods = array('execute', 'executeDom', 'executeJson');
        $mockMethods = array_merge(array_diff($defaultMockMethods, $changeMethods), array_diff($changeMethods, $defaultMockMethods));
        $request = $this->getMockBuilder('Jackalope\Transport\Jackrabbit\Request')
            ->disableOriginalConstructor()
            ->getMock($mockMethods);

        $request
            ->expects($this->any())
            ->method('execute')
            ->will($this->returnValue($response));

        $request
            ->expects($this->any())
            ->method('executeDom')
            ->will($this->returnValue($response));

        $request
            ->expects($this->any())
            ->method('executeJson')
            ->will($this->returnValue($response));

        return $request;
    }

    /** START TESTING NODE TYPES **/
    protected function setUpNodeTypeMock($params, $fixture)
    {
        $dom = new \DOMDocument();
        $dom->load($fixture);

        $requestStr = $this->getTransportMock()->buildNodeTypesRequestMock($params);

        $t = $this->getTransportMock();
        $request = $this->getRequestMock($dom, array('setBody'));
        $t->expects($this->once())
            ->method('getRequest')
            ->with(Request::REPORT, 'testWorkspaceUriRoot')
            ->will($this->returnValue($request));
        $request->expects($this->once())
            ->method('setBody')
            ->with($requestStr);

        return $t;
    }

    /**
     * The default key sanitizer replaces spaces with underscores
     */
    public function testDefaultKeySanitizer()
    {
        $this->cacheMock
            ->expects($this->at(0))
            ->method('fetch')
            ->with(
                $this->equalTo('nodetypes:_a:0:{}')
            );

        /** @var CachedClient $cachedClient */
        $cachedClient = $this->setUpNodeTypeMock(array(), __DIR__.'/../../../fixtures/nodetypes.xml');
        $cachedClient->getNodeTypes();
    }

    public function testCustomkeySanitizer()
    {
        /** @var CachedClient $cachedClient */
        $cachedClient = $this->setUpNodeTypeMock(array(), __DIR__.'/../../../fixtures/nodetypes.xml');
        //set a custom sanitizer that reveres the cachekey
        $cachedClient->setKeySanitizer(function ($cacheKey) {
            return strrev($cacheKey);
        });

        $this->cacheMock
            ->expects($this->at(0))
            ->method('fetch')
            ->with(
                $this->equalTo('}{:0:a :sepytedon')
            );

        /** @var CachedClient $cachedClient */
        $cachedClient->getNodeTypes();
    }
}

class CachedClientMock extends CachedClient
{
    public $curl;
    public $server = 'testserver';
    public $workspace = 'testWorkspace';
    public $workspaceUri = 'testWorkspaceUri';
    public $workspaceUriRoot = 'testWorkspaceUriRoot';

    /**
     * overwrite client constructor which checks backend version
     */
    public function __construct($factory, $serverUri, array $caches = array())
    {
        $this->factory = $factory;
        // append a slash if not there
        if ('/' !== substr($serverUri, -1)) {
            $serverUri .= '/';
        }
        $this->server = $serverUri;

        $caches['meta'] = isset($caches['meta']) ? $caches['meta'] : new ArrayCache();
        $this->caches = $caches;
        $this->keySanitizer = function ($cacheKey) { return str_replace(' ', '_', $cacheKey);};
    }
    public function buildNodeTypesRequestMock(array $params)
    {
        return $this->buildNodeTypesRequest($params);
    }

    public function buildReportRequestMock($name = '')
    {
        return $this->buildReportRequest($name);
    }

    public function buildPropfindRequestMock($args = array())
    {
        return $this->buildPropfindRequest($args);
    }

    public function buildLocateRequestMock($arg = '')
    {
        return $this->buildLocateRequest($arg);
    }

    public function setCredentials($credentials)
    {
        $this->credentials = $credentials;
    }

    public function getRequestMock($method, $uri)
    {
        return $this->getRequest($method, $uri);
    }

    public function addWorkspacePathToUriMock($uri)
    {
        return $this->addWorkspacePathToUri($uri);
    }

    public function getJsopBody()
    {
        return $this->jsopBody;
    }
}
