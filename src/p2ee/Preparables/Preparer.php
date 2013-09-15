<?php
namespace p2ee\Preparables;

/**
 * @service
 */
class Preparer {

    /**
     * @var array|Resolver[]
     */
    protected $resolverMap = [];

    /**
     * @var array
     */
    protected $resolverCache = [];

    /**
     * @param array|Resolver[] $resolverMap
     */
    public function __construct($resolverMap) {
        $this->resolverMap = $resolverMap;
    }

    /**
     * @param Preparable $preparable
     * @param array $prefills
     */
    public function prepare(Preparable $preparable, array $prefills = []) {
        $gen = $preparable->collect();
        if (!($gen instanceof \Generator)) {
            return;
        }

        foreach ($gen as $requirementList) {
            foreach ($requirementList as $requirement) {
                /** @var Requirement $requirement */
                if (isset($prefills[$requirement->getKey()])) {
                    $preparable->inject($requirement->getKey(), $prefills[$requirement->getKey()]);
                } else {
                    $resolvedData = $this->resolve($requirement);
                    $preparable->inject($requirement->getKey(), $resolvedData);
                }
            }
        }
    }

    /**
     * @param Requirement $requirement
     * @throws \Exception
     * @return Resolver
     */
    protected function getResolver(Requirement $requirement) {
        $class = get_class($requirement);

        if (isset($this->resolverMap[$class]) && $this->resolverMap[$class] instanceof Resolver) {
            return $this->resolverMap[$class];
        }
        throw new \RuntimeException('no resolver found for: ' . $class);
    }

    /**
     * @param Requirement $requirement
     * @throws \Exception
     * @return mixed
     */
    protected function resolve(Requirement $requirement) {
        $requirementHash = null;
        if ($requirement->isCacheable()) {
            $requirementHash = spl_object_hash($requirement);
            if (isset($this->resolverCache[$requirementHash])) {
                return $this->resolverCache[$requirementHash];
            }
        }

        try {
            $data = $this->getResolver($requirement)->resolve($requirement, $this);
        } catch (\Exception $e) {
            $requirement->fail($e);
            throw new \Exception('Requirement "' . $requirement->getKey() . '" is required but could not be resolved');
        }

        if ($data === null && $requirement->isRequired()) {
            $requirement->fail();
            throw new \Exception('Requirement "' . $requirement->getKey() . '" is required but could not be resolved');
        }

        if ($requirement->isCacheable() && $requirementHash !== null) {
            $this->resolverCache[$requirementHash] = $data;
        }

        return $data;
    }
}
