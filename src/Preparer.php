<?php
namespace p2ee\Preparables;

/**
 * @service
 */
class Preparer {

    /**
     * @var Preparable[]
     */
    protected $preparables = [];

    /**
     * @var array
     */
    protected $prefillList = [];

    /**
     * @var \Generator[]
     */
    protected $generators = [];

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
     * @return bool
     */
    protected function addGeneratorFormPreparable(Preparable $preparable) {
        $generator = $preparable->collect();
        if (!$this->isGenerator($generator)) {
            return false;
        }

        /** @var \Generator[] $generators */
        $this->generators[spl_object_hash($preparable)] = $generator;
        return true;
    }

    /**
     * @return \Generator
     */
    protected function generatorItems() {
        $initializedGenerators = [];
        while (true) {
            $generatorsToReturn = [];
            $toUnset = [];
            foreach ($this->generators as $preparableHash => $generatorItem) {
                if (defined('HHVM_VERSION') && !isset($initializedGenerators[$preparableHash])) {
                    $initializedGenerators[$preparableHash] = true;
                    $generatorItem->next();
                }

                if ($generatorItem->valid()) {
                    $generatorsToReturn[$preparableHash] = $generatorItem;
                } else {
                    $toUnset[] = $preparableHash;
                }
            }

            foreach ($toUnset as $hash) {
                unset($this->generators[$hash]);
            }

            if (count($generatorsToReturn) == 0) {
                break;
            } else {
                yield $generatorsToReturn;
            }
        }
    }

    /**
     * @param Preparable $preparable
     * @param array $prefills
     */
    public function prepare(Preparable $preparable, $prefills = []) {
        if (!$this->addGeneratorFormPreparable($preparable)) {
            return;
        }
        $this->addPreparable($preparable);

        $this->addPreparablePrefill($preparable, $prefills);

        foreach ($this->generatorItems() as $generators) {
            $requirementsToResolve = $this->collectRequirementList($generators);

            $this->resolveRequirementList($requirementsToResolve);
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
            $data = $this->getResolver($requirement)->resolve($requirement);
        } catch (\Exception $e) {
            $requirement->fail($e);
            return null;
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
    protected function resolveList(array $requirements) {
        $result = [];
        foreach ($requirements as $hash => $requirementList) {
            $result[$hash] = [];
            foreach ($requirementList as $requirement) {
                $data = null;
                if (isset($this->prefillList[$hash]) && isset($this->prefillList[$hash][$requirement->getKey()])) {
                    $data = $this->prefillList[$hash][$requirement->getKey()];
                    unset($this->prefillList[$hash][$requirement->getKey()]);
                } else {
                    $data = $this->resolve($requirement);
                }

                if (!isset($result[$hash][$requirement->getKey()])) {
                    $result[$hash][$requirement->getKey()] = [];
                }
                $result[$hash][$requirement->getKey()][] = $data;
                if ($data instanceof Preparable) {
                    $this->addGeneratorFormPreparable($data);
                    $this->addPreparable($data);
                    $this->addPreparablePrefill($data, $requirement->getPrefills());
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

    /**
     * @param Preparable $preparable
     */
    protected function addPreparable(Preparable $preparable) {
        /** @var Preparable[] $preparables */
        $this->preparables[spl_object_hash($preparable)] = $preparable;
    }

    /**
     * @param Preparable $preparable
     * @param $prefills
     */
    protected function addPreparablePrefill(Preparable $preparable, $prefills) {
        $this->prefillList[spl_object_hash($preparable)] = $prefills;
    }

    /**
     * @param \Generator[] $generators
     * @return Requirement[][][]
     */
    protected function collectRequirementList($generators) {
        $requirementsToResolve = [];

        foreach ($generators as $preparableHash => $generatorItem) {
            /** @var Requirement[] $items */
            $items = $generatorItem->current();
            foreach ($items as $requirement) {
                $class = get_class($requirement);
                if (!isset($requirementsToResolve[$class])) {
                    $requirementsToResolve[$class] = [];
                }
                if (!isset($requirementsToResolve[$class][$preparableHash])) {
                    $requirementsToResolve[$class][$preparableHash] = [];
                }

                $requirementsToResolve[$class][$preparableHash][] = $requirement;
            }
            $generatorItem->next();
        }
        return $requirementsToResolve;
    }

    /**
     * @param $requirementsToResolve
     */
    protected function resolveRequirementList($requirementsToResolve) {
        // resolve one iteration
        foreach ($requirementsToResolve as $class => $requirementList) {
            $resolverResultList = $this->resolveList($requirementList);
            foreach ($resolverResultList as $preparableHash => $resultList) {
                if (!isset($this->preparables[$preparableHash])) {
                    continue;
                }
                $tmpPreparable = $this->preparables[$preparableHash];
                foreach ($resultList as $key => $results) {
                    foreach ($results as $result) {
                        $tmpPreparable->inject($key, $result);
                    }
                }
            }
        }
    }
}
