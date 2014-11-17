<?php
namespace p2ee\Preparables;

/**
 * @service
 *
 * Class Preparer
 * @package p2ee\Preparables
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
     * @var array
     */
    protected $resolverSteps = [];

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
        $generator = $preparable->collect();
        if (!$this->isGenerator($generator)) {
            return;
        }

        // list of requirements

        // iterate over requirement list

                // get requirement uniq key

                // add requirement to resolver

                // run resolver

                // eventual add new requirements to requirement list for next

                // push data back

            // if there are no more new data then break

        foreach ($generator as $requirementList) {
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
        $requirementHash = sha1(get_class($requirement) . $requirement->getCacheKey());
        if ($requirement->isCacheable()) {
            if (isset($this->resolverCache[$requirementHash])) {
                $data = $this->resolverCache[$requirementHash];
                $this->checkRequirementData($requirement, $data);
                return $data;
            }
        }

        $data = null;
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

    private function isGenerator($obj) {
        if (class_exists('Generator')) {
            return is_a($obj, 'Generator');
        }
        if (class_exists('Continuation')) {
            return is_a($obj, 'Continuation');
        }
        return false;
    }
}
