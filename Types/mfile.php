<?php

namespace Orkester\Types;

use Orkester\Manager;

class MFile extends MType
{

    private $name;
    private $type;
    private $tmpName;
    private $error;
    private $size;
    private $value;
    private $path;
    private $url;

    public function __construct(array|string $file, $inline = true)
    {
        parent::__construct();
        if (is_array($file)) {
            $this->name = $file['name'];
            $this->type = $file['type'];
            $this->tmpName = $file['tmp_name'];
            $this->error = $file['error'];
            $this->size = $file['size'];
            $this->getValue();
            $this->setPath($this->tmpName, $inline);
        } else {
            $this->setValue($file);
        }
    }

    public static function file($value, $inline = true, $name = '')
    {
        $size = strlen($value);
        $instance = new MFile(array('size' => $size));
        $instance->setValue($value);
        $instance->saveToCache($inline, $name);
        return $instance;
    }

    public static function path($path, $name = '', $inline = true)
    {
        $file['name'] = ($name) ?: basename($path);
        $file['type'] = mime_content_type($path);
        $file['tmp_name'] = $path;
        $file['size'] = filesize($path);
        return new MFile($file);
    }

    public function copyTo($file)
    {
        if ($f = $this->tmpName) {
            copy($f, $file);
            $this->setPath($file);
            return true;
        } else {
            return false;
        }
    }

    public function saveToCache($inline = true, $name = '')
    {
        $this->name = $name ?: md5($this->value);
        $file = Manager::getOptions('tmpPath') . '/files/' . $this->name;
        unlink($file);
        if (!file_exists($file)) {
            $this->saveTo($file);
        }
        $this->setPath($file, $inline);
    }

    public function saveTo($file)
    {
        file_put_contents($file, $this->value);
    }

    public function setPath($file, $inline = true)
    {
        $this->path = $file;
        $this->url = Manager::getDownloadURL('cache', basename($file), $inline);
    }

    public function getTmpName()
    {
        return $this->tmpName;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getURL()
    {
        return $this->url;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        if ($this->tmpName) {
            $this->value = file_get_contents($this->tmpName);
        }
        return $this->value;
    }

    public function getPlainValue()
    {
        return $this->getURL();
    }

    public function __toString()
    {
        return $this->url;
    }

}
