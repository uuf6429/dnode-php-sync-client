<?php

namespace uuf6429\DnodeSyncClient;

class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        stream_wrapper_register('testwrapper', TestStreamWrapper::class);
    }

    public function tearDown()
    {
        stream_wrapper_unregister('testwrapper');
    }

    public function testMethodsAreReadFromRemote()
    {
        $stream = fopen('testwrapper://', 'rw');

        $this->expectException(Exception\IOException::class);
        $this->expectExceptionMessage("Can't read method description from remote");
        new Connection($stream);
    }

    public function testRemoteMethodsAreParsed()
    {
        $stream = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead("invalid json\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('First line is not valid json: invalid json');
        new Connection($stream);
    }

    public function testRemoteMethodsMethodFieldIsChecked()
    {
        $stream = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead("{}\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('First line does not have method field: {}');
        new Connection($stream);
    }

    public function testFirstRemoteMethodMustBeMethods()
    {
        $stream = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead('{"method": "not-methods"}'."\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('First line method must be "methods": {"method": "not-methods"}');
        new Connection($stream);
    }

    public function testRemoteMethodsArgumentsMustNotBeMissing()
    {
        $stream = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead('{"method": "methods"}'."\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('Methods arguments missing: {"method": "methods"}');
        new Connection($stream);
    }

    public function testRemoteMethodsArgumentsMustNotBeEmpty()
    {
        $stream = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead('{"method": "methods", "arguments": []}'."\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('Methods must have single argument: {"method": "methods", "arguments": []}');
        new Connection($stream);
    }

    public function testRemoteMustHaveSomeMethods()
    {
        $stream = fopen('testwrapper://', 'rw');
        $response = '{"method": "methods", "arguments": [{}]}';
        TestStreamWrapper::instance()->addRead("$response\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage("Remote is expected to have some methods: $response");
        new Connection($stream);
    }

    public function testMethodsAreSentToRemote()
    {
        $stream = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead('{"method": "methods", '
            .'"arguments": [{"method1": ""}]}'."\n");

        new Connection($stream);

        $this->assertEquals(
            ['{"method":"methods"}'."\n"],
            TestStreamWrapper::instance()->getWrites()
        );
    }

    public function testRemoteMethods()
    {
        $connection = $this->initConnection();

        $this->assertEquals(
            ['method1', 'method2'],
            $connection->getAvailableMethods()
        );
    }

    private function initConnection()
    {
        $stream = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead('{"method": "methods", '
            .'"arguments": [{"method1": "", "method2": ""}]}'."\n");

        $connection = new Connection($stream);
        TestStreamWrapper::instance()->getWrites(); // clear stuff written to wrapper

        return $connection;
    }

    public function testCallMethod()
    {
        $connection = $this->initConnection();

        TestStreamWrapper::instance()->addRead('{"method": 42}'."\n");
        $connection->call('method1', ['arg1', 2]);

        $this->assertEquals(
            ['{"method":"method1","arguments":["arg1",2],"callbacks":{"42":[2]}}'."\n"],
            TestStreamWrapper::instance()->getWrites()
        );
    }

    public function testCallbackNumberIncreasedMethod()
    {
        $connection = $this->initConnection();

        TestStreamWrapper::instance()->addRead('{"method": 42}'."\n");
        $connection->call('method1');

        TestStreamWrapper::instance()->getWrites(); // clear stuff written to wrapper

        TestStreamWrapper::instance()->addRead('{"method": 43}'."\n");
        $connection->call('method1');

        $this->assertEquals(
            ['{"method":"method1","arguments":[],"callbacks":{"43":[0]}}'."\n"],
            TestStreamWrapper::instance()->getWrites()
        );
    }

    public function testCallMethodResponseMustUseRequestCallback()
    {
        $connection = $this->initConnection();

        TestStreamWrapper::instance()->addRead('{"method": 41}'."\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('Response does not call expected callback, expected 42, got {"method": 41}');

        $connection->call('method1');
    }

    public function testCallMethodInvalidJsonResponse()
    {
        $connection = $this->initConnection();

        TestStreamWrapper::instance()->addRead("invalid json\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectException('Response is not valid json: invalid json');

        $connection->call('method1');
    }

    public function testCallMethodResponseWithoutMethod()
    {
        $connection = $this->initConnection();

        TestStreamWrapper::instance()->addRead("{}\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('Response does not have method field: {}');

        $connection->call('method1');
    }

    public function testCallMethodNotDeclaredByRemote()
    {
        $connection = $this->initConnection();

        $this->expectException(Exception\MethodNotExistsException::class);
        $this->expectExceptionMessage('Method invalidMethod does not exists on remote.');

        $connection->call('invalidMethod');
    }

    public function testCallMethodLinksNotPresent()
    {
        $connection = $this->initConnection();

        TestStreamWrapper::instance()->addRead('{"method": 42, "links": [1]}'."\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('Response contains links, we do not support that: {"method": 42, "links": [1]}');

        $connection->call('method1');
    }

    public function testCallMethodCallbacksNotPresent()
    {
        $connection = $this->initConnection();

        $response = '{"method": 42, "callbacks": {"1":[0]}}';
        TestStreamWrapper::instance()->addRead($response."\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('Response contains callbacks, we do not support that: '.$response);

        $connection->call('method1');
    }

    public function testCallMethodArgumentsMustBeArray()
    {
        $connection = $this->initConnection();

        $response = '{"method": 42, "arguments": null}';
        TestStreamWrapper::instance()->addRead($response."\n");

        $this->expectException(Exception\ProtocolException::class);
        $this->expectExceptionMessage('Response arguments must be array: '.$response);

        $connection->call('method1');
    }
}
