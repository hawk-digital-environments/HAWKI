<?php
declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\DecoratorTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Utils\DecoratorTraitTestFixtures\DecoratedModel;
use Tests\Unit\Utils\DecoratorTraitTestFixtures\ExtendedParentModel;
use Tests\Unit\Utils\DecoratorTraitTestFixtures\ParentModel;
use Tests\Unit\Utils\DecoratorTraitTestFixtures\StandaloneModel;

#[CoversTrait(DecoratorTrait::class)]
class DecoratorTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        // Reset static properties modified during tests
        ParentModel::$tag = 'original';
        DecoratedModel::$tag = 'decorated_original';
    }

    // =========================================================================
    // Property copying
    // =========================================================================

    public function testItCopiesPublicProperties(): void
    {
        $parent = new ParentModel('bob');
        $sut = DecoratedModel::createDecoratedOf($parent);

        static::assertSame('bob', $sut->name);
    }

    public function testItCopiesProtectedProperties(): void
    {
        $parent = new ParentModel('alice', 'admin');
        $sut = DecoratedModel::createDecoratedOf($parent);

        static::assertSame('admin', $sut->getRole());
    }

    public function testItCopiesPrivateProperties(): void
    {
        $parent = new ParentModel('alice', 'user', 'topsecret');
        $sut = DecoratedModel::createDecoratedOf($parent);

        static::assertSame('topsecret', $sut->getSecret());
    }

    public function testItCopiesStaticProperties(): void
    {
        ParentModel::$tag = 'runtime_tag';
        $parent = new ParentModel();

        $sut = DecoratedModel::createDecoratedOf($parent);

        static::assertSame('runtime_tag', DecoratedModel::$tag);
    }

    public function testItSkipsUninitializedStaticProperties(): void
    {
        // ParentModel::$uninitializedStatic has no default value and is never assigned,
        // so isInitialized(null) returns false and the trait must skip it (line 110).
        $parent = new ParentModel();
        $sut = DecoratedModel::createDecoratedOf($parent);

        $ref = new \ReflectionProperty(ParentModel::class, 'uninitializedStatic');
        static::assertFalse($ref->isInitialized(null));
        static::assertInstanceOf(DecoratedModel::class, $sut);
    }

    public function testItSkipsPropertiesNotInTargetClassHierarchy(): void
    {
        // ExtendedParentModel adds $extra which DecoratedModel does not inherit.
        // The inner target-search loop exhausts without finding it, so line 101 continues.
        $extended = new ExtendedParentModel('bob', 'moderator', 's3cr3t');
        $sut = DecoratedModel::createDecoratedOf($extended);

        // The known properties are still copied correctly
        static::assertSame('bob', $sut->name);
        // $extra must not exist on the decorated instance
        static::assertFalse(property_exists($sut, 'extra'));
    }

    public function testItSkipsUninitializedProperties(): void
    {
        $parent = new ParentModel(); // $uninitializedProp is never set
        $sut = DecoratedModel::createDecoratedOf($parent);

        $ref = new \ReflectionProperty(ParentModel::class, 'uninitializedProp');
        static::assertFalse($ref->isInitialized($sut));
    }

    // =========================================================================
    // Method behaviour after decoration
    // =========================================================================

    public function testItUsesOverriddenMethod(): void
    {
        $parent = new ParentModel();
        $sut = DecoratedModel::createDecoratedOf($parent);

        static::assertSame('decorated', $sut->identify());
    }

    public function testItUsesInheritedMethodWithCopiedState(): void
    {
        $parent = new ParentModel('alice', 'editor', 'mysecret');
        $sut = DecoratedModel::createDecoratedOf($parent);

        // getSecret() is defined on ParentModel but should operate on copied state
        static::assertSame('mysecret', $sut->getSecret());
    }

    // =========================================================================
    // Exception cases
    // =========================================================================

    public function testItThrowsLogicExceptionWhenClassHasNoParent(): void
    {
        $dummy = new \stdClass();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Class %s must extend another class to use %s; I would expect you want to extend: %s',
            StandaloneModel::class,
            DecoratorTrait::class,
            \stdClass::class
        ));

        StandaloneModel::createDecoratedOf($dummy);
    }

    public function testItThrowsInvalidArgumentExceptionForWrongParentType(): void
    {
        $dummy = new \stdClass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'When inheriting all properties, the parent object must be an instance of %s, %s given.',
            ParentModel::class,
            \stdClass::class
        ));

        DecoratedModel::createDecoratedOf($dummy);
    }
}
