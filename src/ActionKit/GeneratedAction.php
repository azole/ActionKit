<?php
namespace ActionKit;
use ActionKit\Exception\UnableToWriteCacheException;

class GeneratedAction
{
    public $className; 
    public $code; 
    public $object;

    public function __construct($className, $code, $object = null)
    {
        $this->className = $className;
        $this->code = $code;
        $this->object = $object;
    }

    public function requireAt($cacheFile)
    {
        if (!class_exists($this->className ,true)) {
            $this->writeTo($cacheFile);
            require $cacheFile;
        }
    }

    public function writeTo($cacheFile)
    {
        if (false === file_put_contents($cacheFile, $this->code)) {
            throw new UnableToWriteCacheException("Can not write action class cache file: $cacheFile");
        }
    }

    public function load()
    {
        $tmpname = tempnam('/tmp', md5($this->className));
        $this->requireAt($tmpname);
    }

    public function getPsrClassPath()
    {
        return str_replace('\\', DIRECTORY_SEPARATOR, ltrim($this->className,'\\'));
    }
}
