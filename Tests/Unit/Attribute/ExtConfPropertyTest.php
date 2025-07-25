<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "typed_extconf".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace mteu\TypedExtConf\Tests\Unit\Attribute;

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * ExtConfPropertyTest.
 *
 * Tests the ExtConfProperty attribute for proper construction,
 * metadata handling, and reflection capabilities.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class ExtConfPropertyTest extends Framework\TestCase
{
    #[Test]
    public function constructWithDefaults(): void
    {
        $attribute = new ExtConfProperty();

        self::assertNull($attribute->path);
        self::assertFalse($attribute->required);
    }

    #[Test]
    public function constructWithAllParameters(): void
    {
        $attribute = new ExtConfProperty('api.endpoint', true);

        self::assertSame('api.endpoint', $attribute->path);
        self::assertTrue($attribute->required);
    }

    #[Test]
    public function attributeIsReadonly(): void
    {
        $reflection = new \ReflectionClass(ExtConfProperty::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->getProperty('path')->isReadOnly());
        self::assertTrue($reflection->getProperty('required')->isReadOnly());
    }

    #[Test]
    public function attributeHasCorrectTargets(): void
    {
        $reflection = new \ReflectionClass(ExtConfProperty::class);
        $attributeAttrs = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributeAttrs);

        $attributeInstance = $attributeAttrs[0]->newInstance();
        $expectedTargets = \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER;

        self::assertSame($expectedTargets, $attributeInstance->flags);
    }

    #[Test]
    public function attributeCanBeUsedOnParameter(): void
    {
        $testClass = new class () {
            public function __construct(
                #[ExtConfProperty(path: 'test.param', required: true)]
                public readonly string $testParam = 'default'
            ) {}
        };

        $reflection = new \ReflectionClass($testClass);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        $testParam = $parameters[0];

        $attributes = $testParam->getAttributes(ExtConfProperty::class);
        self::assertCount(1, $attributes);

        $attributeInstance = $attributes[0]->newInstance();
        self::assertSame('test.param', $attributeInstance->path);
        self::assertTrue($attributeInstance->required);
    }
}
