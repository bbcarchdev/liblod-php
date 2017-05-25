<?php
namespace res\liblod;

abstract class LODTerm
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public abstract function isResource();

    public function __toString()
    {
        return $this->value;
    }
}
