<?php

abstract class StaticGenerator
{
    protected $_engine;

    public function __construct(LightningEngine $engine)
    {
        $this->_engine = $engine;
    }
    
    abstract public function getHeaders();
    
    abstract public function generate();
}
