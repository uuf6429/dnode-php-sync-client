<?php

namespace uuf6429\DnodeSyncClient;

class TestStreamWrapper
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * @var string[]
     */
    private $writeQueue = [];

    /**
     * @var string[]
     */
    private $readQueue = [];

    public function stream_open()
    {
        self::$instance = $this;

        return true;
    }

    public function stream_close()
    {
        self::$instance = null;
    }

    public function stream_read()
    {
        return count($this->readQueue) ? array_shift($this->readQueue) : false;
    }

    public function stream_eof()
    {
        // Note: ideally we should have !count($this->readQueue) however PHP will terminate connection as soon as
        // we return true, so instead we lie and let stream_read do the job.
        return false;
    }

    public function stream_write($data)
    {
        $this->writeQueue[] = $data;

        return strlen($data);
    }

    public function addRead($data)
    {
        $this->readQueue[] = $data;
    }

    public function getWrites()
    {
        $writes = $this->writeQueue;
        $this->writeQueue = [];

        return $writes;
    }

    public function resetIO()
    {
        $this->readQueue = [];
        $this->writeQueue = [];
    }

    public static function instance()
    {
        if (self::$instance === null) {
            throw new \RuntimeException('StreamWrapper instance has not been set.');
        }

        return self::$instance;
    }
}
