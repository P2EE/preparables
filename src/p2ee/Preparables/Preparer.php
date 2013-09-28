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
        $requirementHash = sha1(get_class($requirement).$requirement->getCacheKey());
        if ($requirement->isCacheable()) {
            if (isset($this->resolverCache[$requirementHash])) {
                $data = $this->resolverCache[$requirementHash];
                $this->checkRequirementData($requirement, $data);
                return $data;
            }
        }

        try {
            $data = $this->getResolver($requirement)->resolve($requirement, $this);
        } catch (\Exception $e) {
            $requirement->fail($e);
        }

        $this->checkRequirementData($requirement, $data);

        if ($requirement->isCacheable()) {
            $this->resolverCache[$requirementHash] = $data;
        }

        return $data;
    }

    /**
     * @param Requirement $requirement
     * @param $data
     * @throws \Exception
     */
    private function checkRequirementData(Requirement $requirement, $data) {
        if ($data === null && $requirement->isRequired()) {
            $requirement->fail();
            throw new \Exception('Requirement "' . $requirement->getKey() . '" is required but could not be resolved');
        }
    }
}
