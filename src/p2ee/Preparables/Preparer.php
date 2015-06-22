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
    public function prepare(Preparable $preparable, $prefills = []) {
        $generator = $preparable->collect();
        if (!$this->isGenerator($generator)) {
            return;
        }

        /** @var \Generator[] $generators */
        $generators = [];
        $generators[spl_object_hash($preparable)] = $generator;

        /** @var Preparable[] $preparables */
        $preparables = [];
        $preparables[spl_object_hash($preparable)] = $preparable;

        $prefillList = [];
        $prefillList[spl_object_hash($preparable)] = $prefills;

        $isDone = false;
        while (!$isDone) {
            $requirementsToResolve = [];
            // collect one iteration over all active preperables
            $toUnset = [];
            foreach ($generators as $preperableHash => $generatorItem) {
                if (defined('HHVM_VERSION')) {
                    $generatorItem->next();
                }
                if (!$generatorItem->valid()) {
                    $toUnset[] = $preperableHash;
                    continue;
                }
                /** @var Requirement[] $items */
                $items = $generatorItem->current();
                foreach ($items as $requirement) {
                    $class = get_class($requirement);
                    if (!isset($requirementsToResolve[$class])) {
                        $requirementsToResolve[$class] = [];
                    }
                    if (!isset($requirementsToResolve[$class][$preperableHash])) {
                        $requirementsToResolve[$class][$preperableHash] = [];
                    }

                    $requirementsToResolve[$class][$preperableHash][] = $requirement;
                }
                $generatorItem->next();
            }

            foreach ($toUnset as $hash) {
                unset($generators[$hash]);
            }

            // resolve one iteration
            foreach ($requirementsToResolve as $class => $requirementList) {
                $resolverResultList = $this->resolveList($requirementList, $prefillList);
                foreach ($resolverResultList as $preperableHash => $resultList) {
                    if (!isset($preparables[$preperableHash])) {
                        continue;
                    }
                    $tmpPreperable = $preparables[$preperableHash];
                    foreach ($resultList as $key => $results) {
                        foreach ($results as $result) {
                            $tmpPreperable->inject($key, $result['data']);
                            // if a result is a preperable itself add to generator and preperable list
                            if ($result['data'] instanceof Preparable) {
                                $generators[spl_object_hash($result['data'])] = $result['data']->collect();
                                $preparables[spl_object_hash($result['data'])] = $result['data'];
                                $prefillList[spl_object_hash($result['data'])] = $result['requirement']->getPrefills();
                            }
                        }
                    }
                }
            }

            if (count($generators) == 0) {
                $isDone = true;
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
     * @param Requirement[][] $requirements
     * @return array
     */
    protected function resolveList(array $requirements, $prefillList) {
        $result = [];
        foreach($requirements as $hash => $requirementList) {
            $result[$hash] = [];
            foreach($requirementList as $requirement) {
                $prefills = [];
                if ($prefillList[$hash]) {
                    $prefills = $prefillList[$hash];
                }
                if (isset($prefills[$requirement->getKey()])) {
                    $result[$hash][$requirement->getKey()][] = [
                        'data' => $prefills[$requirement->getKey()],
                        'requirement' => $requirement
                    ];
                } else {
                    $result[$hash][$requirement->getKey()][] = [
                        'data' => $this->resolve($requirement),
                        'requirement' => $requirement
                    ];
                }
            }
        }
        return $result;
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
