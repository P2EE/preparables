<?php
namespace p2ee\Preparables;

interface Preparable {

    public function collect();

    public function inject($key, $value);

    public function isPrefilled($key);
} 
