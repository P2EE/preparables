<?php
namespace p2ee\Preparables;

class PreparerTest extends \PHPUnit\Framework\TestCase {

    public function testCreation() {
        $preparer = new Preparer([]);
        $this->assertInstanceOf(Preparer::class, $preparer);
    }

    public function testObjectPreparation() {
        $testValue = 1;
        $testKey = 'test1';

        $requirement = new TestRequirement($testKey, false);

        $preparable = $this->buildPreparableMock($requirement, $testKey, $testValue);

        $resolver = $this->buildResolverMock($requirement, $testValue);

        $preparer = new Preparer([
            TestRequirement::class => $resolver
        ]);

        $preparer->prepare($preparable);
    }

    public function testNonGeneratorPreparation() {
        $testValue = 1;
        $testKey = 'test1';

        $requirement = new TestRequirement($testKey, false);

        /**
         * @var $preparable \PHPUnit_Framework_MockObject_MockObject | Preparable
         */
        $preparable = $this->getMockBuilder(Preparable::class)->getMock();

        $preparable->expects($this->once())
            ->method('collect')
            ->will($this->returnCallback(function () use ($requirement) {
                return [
                    $requirement
                ];
            }));

        $resolver = $this->buildResolverMock($requirement, $testValue, 0);

        $preparer = new Preparer([
            TestRequirement::class => $resolver
        ]);

        $preparer->prepare($preparable);
    }

    /**
     * @ expectedException \Exception
     */
    public function testMissingResolver() {
        $testValue = 1;
        $testKey = 'test1';

        /**
         * @var $preparable \PHPUnit_Framework_MockObject_MockObject | Preparable
         */
        $requirement = $this->getMockBuilder(Requirement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $requirement->expects($this->any())
            ->method('getKey')
            ->willReturn($testKey);
        $requirement->expects($this->any())
            ->method('isRequired')
            ->willReturn(true);
        $requirement->expects($this->once())
            ->method('fail')
            ->with(
                $this->isInstanceOf(\RuntimeException::class)
            );

        /**
         * @var $preparable \PHPUnit_Framework_MockObject_MockObject | Preparable
         */
        $preparable = $this->getMockBuilder(Preparable::class)->getMock();

        $preparable->expects($this->once())
            ->method('collect')
            ->will($this->returnCallback(function () use ($requirement) {
                yield [
                    $requirement
                ];
            }));

        $resolver = $this->buildResolverMock($requirement, $testValue, 0);

        $preparer = new Preparer([
            'SomeOtherClassName' => $resolver
        ]);

        $preparer->prepare($preparable);
    }
    /**
     * @expectedException \Exception
     * @expectedExceptionMessage is required but could not be resolved
     */
    public function testFailedResolving() {
        $testValue = null;
        $testKey = 'test1';

        /**
         * @var $requirement \PHPUnit_Framework_MockObject_MockObject | TestRequirement
         */
        $requirement = new TestRequirement($testKey, false);

        /**
         * @var $preparable \PHPUnit_Framework_MockObject_MockObject | Preparable
         */
        $preparable = $this->getMockBuilder(Preparable::class)->getMock();

        $preparable->expects($this->once())
            ->method('collect')
            ->will($this->returnCallback(function () use ($requirement) {
                yield [
                    $requirement
                ];
            }));

        $resolver = $this->buildResolverMock($requirement, $testValue, 1);

        $preparer = new Preparer([
            get_class($requirement) => $resolver
        ]);

        $preparer->prepare($preparable);
        $this->assertTrue($requirement->isFailed);
    }

    public function testNestedPreperation() {
        $testValue = 1;
        $testKey1 = 'test1';
        $testKey2 = 'test2';

        $requirement1 = new TestRequirement($testKey1, true);
        $requirement2 = new TestRequirement($testKey2, true);

        $preparable2 = $this->getMockBuilder(Preparable::class)->getMock();
        $preparable2->expects($this->once())
            ->method('collect')
            ->will($this->returnCallback(function () use ($requirement2) {
                yield [
                    $requirement2
                ];
            }));

        $preparable2->expects($this->once())
            ->method('inject')
            ->with(
                $this->equalTo($testKey2),
                $this->equalTo($testValue)
            );


        /** @var \PHPUnit_Framework_MockObject_MockObject | Preparable $preparable1 */
        $preparable1 = $this->getMockBuilder(Preparable::class)->getMock();
        $callback = function () use ($requirement1) {
            yield [
                $requirement1
            ];
        };
        $preparable1->expects($this->once())
            ->method('collect')
            ->will($this->returnValue($callback()));

        $preparable1->expects($this->once())
            ->method('inject')
            ->with(
                $this->equalTo($testKey1),
                $this->identicalTo($preparable2)
            );

        $resolver = $this->getMockBuilder(Resolver::class)->getMock();
        $resolver->expects($this->any())
            ->method('resolve')->with(
                $this->isInstanceOf(Requirement::class)
            )->will($this->returnCallback(function($requirement) use (
                $requirement1,
                $requirement2,
                $preparable1,
                $preparable2,
                $testValue
            ) {
                if($requirement === $requirement1) {
                    return $preparable2;
                } elseif ($requirement === $requirement2) {
                    return $testValue;
                }
                return null;
            }));

        $preparer = new Preparer([
            TestRequirement::class => $resolver
        ]);

        $preparer->prepare($preparable1);
    }

    public function testResolverCaching() {
        $testValue = 1;
        $testKey = 'test1';

        $requirement1 = new TestRequirement($testKey, true);
        $requirement2 = new TestRequirement($testKey, true);

        $preparable1 = $preparable = $this->buildPreparableMock($requirement1, $testKey, $testValue);

        $preparable2 = $preparable = $this->buildPreparableMock($requirement2, $testKey, $testValue);

        $resolver = $this->buildResolverMock($requirement1, $testValue);

        $preparer = new Preparer([
            TestRequirement::class => $resolver
        ]);

        $preparer->prepare($preparable1);
        $preparer->prepare($preparable2);
    }

    public function testPrefills() {
        $testValue = 1;
        $testKey = 'test1';

        $requirement = new TestRequirement($testKey, true);

        $preparable = $this->buildPreparableMock($requirement, $testKey, $testValue);

        $resolver = $this->buildResolverMock($requirement, $testValue, 0);

        $preparer = new Preparer([
            TestRequirement::class => $resolver
        ]);

        $preparer->prepare($preparable, [$testKey => $testValue]);
    }

    public function testUnusedPrefillsShouldNotBePrefilled() {
        $testValue = 1;
        $testKey = 'test1';

        $requirement = new TestRequirement($testKey, true);

        $preparable = $this->buildPreparableMock($requirement, $testKey, $testValue);

        $resolver = $this->buildResolverMock($requirement, $testValue, 1);

        $preparer = new Preparer([
            TestRequirement::class => $resolver
        ]);

        $preparer->prepare($preparable, [$testKey . '123' => $testValue]);
    }

    /**
     * @param $requirement
     * @param $testValue
     * @param int $resolveCalls
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function buildResolverMock($requirement, $testValue, $resolveCalls = 1) {
        if ($resolveCalls == 0) {
            $expectation = $this->never();
        } else if ($resolveCalls == 1) {
            $expectation = $this->once();
        } else {
            $expectation = $this->exactly($resolveCalls);
        }

        $resolver = $this->getMockBuilder(Resolver::class)->getMock();

        if ($resolveCalls == 0) {
            $resolver->expects($expectation)
                ->method('resolve');
        } else {
            $resolver->expects($expectation)
                ->method('resolve')->with(
                    $this->identicalTo($requirement)
                )->will($this->returnValue($testValue));
        }
        return $resolver;
    }

    /**
     * @param $requirement
     * @param $testKey
     * @param $testValue
     * @return \PHPUnit_Framework_MockObject_MockObject | Preparable
     */
    protected function buildPreparableMock($requirement, $testKey, $testValue) {
        $preparable = $this->getMockBuilder(Preparable::class)->getMock();

        $preparable->expects($this->once())
            ->method('collect')
            ->will($this->returnCallback(function () use ($requirement) {
                yield [
                    $requirement
                ];
            }));

        $preparable->expects($this->once())
            ->method('inject')
            ->with(
                $this->equalTo($testKey),
                $this->equalTo($testValue)
            );
        return $preparable;
    }
}

class TestRequirement extends Requirement {
    /**
     * @var
     */
    private $cachable;

    public function __construct($key, $cachable) {
        $this->key = $key;
        $this->cachable = $cachable;
    }

    public function isCacheable() {
        return $this->cachable;
    }

    public function getCacheKey() {
        return $this->getKey();
    }

    public function getPrefills() {
        return [];
    }
}
