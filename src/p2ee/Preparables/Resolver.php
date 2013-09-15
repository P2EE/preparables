<?php
namespace p2ee\Preparables;

interface Resolver {

    /**
     * @param Requirement $requirement
     * @param Preparer $preparer
     * @return mixed
     */
    public function resolve(Requirement $requirement, Preparer $preparer);

    /**
     * @return string
     */
    public function getSupportedType();
} 
