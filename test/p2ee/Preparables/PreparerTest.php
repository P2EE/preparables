<?php
namespace p2ee\Preparables;

class PreparerTest extends \PHPUnit_Framework_TestCase {

    public function testCreation() {
        $preparer = new Preparer([]);
        $this->assertInstanceOf(Preparer::class, $preparer);
    }

    public function testObjectPreparation() {
        $testValue = 1;
        $testKey = 'test1';

        $requirement = new TestRequirement($testKey);

        $preparable = $this->getMock(Preparable::class);

        $preparable->expects($this->once())
            ->method('collect')
            ->will($this->returnCallback(function () use($requirement) {
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

        $resolver = $this->getMock(Resolver::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with(
                $this->identicalTo($requirement),
                $this->isInstanceOf(Preparer::class)
            )
            ->will($this->returnValue($testValue));

        $preparer = new Preparer([
            TestRequirement::class => $resolver
        ]);

        $preparer->prepare($preparable);
    }
}

class TestRequirement extends Requirement {

    public function __construct($key) {
        $this->key = $key;
    }

    public function isCacheable() {
        return false;
    }
}