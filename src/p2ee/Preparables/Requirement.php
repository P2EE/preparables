<?php
namespace p2ee\Preparables;

/**
 * Class Requirement
 * @package p2ee\Preparables
 */
abstract class Requirement {

    const MODE_OPTIONAL = 'opt';
    const MODE_REQUIRED = 'req';

    /**
     * @var bool
     */
    public $isFailed = false;

    /**
     * @var mixed
     */
    public $failInformation;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $required = self::MODE_REQUIRED;

    public function getKey(){
        return $this->key;
    }

    abstract public function isCacheable();

    abstract public function getCacheKey();

    public function isRequired(){
        return $this->required == self::MODE_REQUIRED;
    }

    public function fail($e = null){
        $this->isFailed = true;
        $this->failInformation[] = $e;
    }
} 
