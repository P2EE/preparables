<?php
namespace p2ee\Preparables;

interface Resolver {

    /**
     * @param Requirement $requirement
     * @return mixed
     */
    public function resolve(Requirement $requirement);

    /**
     * @return string
     */
    public function getSupportedType();
} 
