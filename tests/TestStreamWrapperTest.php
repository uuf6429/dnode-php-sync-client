<?php

namespace uuf6429\DnodeSyncClient;

class TestStreamWrapperTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        stream_wrapper_register('testwrapper', TestStreamWrapper::class);
    }

    public function tearDown()
    {
        stream_wrapper_unregister('testwrapper');
    }

    public function testRead()
    {
        $ch = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead("data\n");

        $line = fgets($ch);

        $this->assertEquals("data\n", $line);
    }

    public function testWrite()
    {
        $ch = fopen('testwrapper://', 'rw');

        fwrite($ch, 'test line');

        $this->assertEquals(['test line'], TestStreamWrapper::instance()->getWrites());
    }

    public function testReadWrite()
    {
        $ch = fopen('testwrapper://', 'rw');
        TestStreamWrapper::instance()->addRead("read line\n");

        fwrite($ch, "written line\n");
        $line = fgets($ch);

        $this->assertEquals("read line\n", $line);
        $this->assertEquals(["written line\n"], TestStreamWrapper::instance()->getWrites());
    }
}
