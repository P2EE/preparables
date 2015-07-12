<?php
namespace p2ee\Preparables;

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

    abstract public function isCacheable();

    abstract public function getCacheKey();

    abstract public function getPrefills();

    /**
     * @return string
     */
    public function getKey(){
        return $this->key;
    }

    /**
     * @return bool
     */
    public function isRequired(){
        return $this->required == self::MODE_REQUIRED;
    }

    /**
     * @param \Exception $exception
     */
    public function fail(\Exception $exception = null){
        $this->isFailed = true;

        if ($exception) {
            $this->failInformation[] = $exception;
        }
    }
} 
