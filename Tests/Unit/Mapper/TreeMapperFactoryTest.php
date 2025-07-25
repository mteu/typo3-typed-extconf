<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "typed-extconf".
 *
 * Copyright (C) 2025 Martin Adler <mteu@mailbox.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace mteu\TypedExtConf\Tests\Unit\Mapper;

use CuyZ\Valinor\Mapper\MappingError;
use mteu\TypedExtConf\Mapper\TreeMapperFactory;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * TreeMapperFactoryTest.
 *
 * Tests the TreeMapperFactory for proper mapper creation
 * and configuration handling.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class TreeMapperFactoryTest extends Framework\TestCase
{
    private TreeMapperFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new TreeMapperFactory();
    }

    #[Test]
    public function createReturnsWorkingMapper(): void
    {
        $mapper = $this->factory->create();

        // Test that the mapper can actually map something simple
        $testClass = new class () {
            public function __construct(public readonly string $value = 'default') {}
        };

        $result = $mapper->map($testClass::class, ['value' => 'test']);
        self::assertSame('test', $result->value);
    }

    #[Test]
    public function createReturnsNewInstanceEachTime(): void
    {
        $mapper1 = $this->factory->create();
        $mapper2 = $this->factory->create();

        self::assertNotSame($mapper1, $mapper2);
    }

    #[Test]
    public function createdMapperAllowsSuperfluousKeys(): void
    {
        $mapper = $this->factory->create();

        $testClass = new class () {
            public function __construct(
                public readonly string $required = 'default'
            ) {}
        };

        // Data with extra keys that should be ignored
        $data = [
            'required' => 'test_value',
            'extra_key' => 'should_be_ignored',
            'another_extra' => 123,
        ];

        $result = $mapper->map($testClass::class, $data);

        self::assertInstanceOf($testClass::class, $result);
        self::assertSame('test_value', $result->required);
    }

    #[Test]
    public function createdMapperHandlesTypeConversions(): void
    {
        $mapper = $this->factory->create();

        $testClass = new class () {
            public function __construct(
                public readonly string $stringValue = '',
                public readonly int $intValue = 0,
                public readonly bool $boolValue = false,
                public readonly float $floatValue = 0.0
            ) {}
        };

        $data = [
            'stringValue' => 'test_string',
            'intValue' => 456,
            'boolValue' => true,
            'floatValue' => 3.14,
        ];

        $result = $mapper->map($testClass::class, $data);

        self::assertSame('test_string', $result->stringValue);
        self::assertSame(456, $result->intValue);
        self::assertTrue($result->boolValue);
        self::assertSame(3.14, $result->floatValue);
    }

    #[Test]
    public function createdMapperThrowsMappingErrorForInvalidData(): void
    {
        $mapper = $this->factory->create();

        $testClass = new class () {
            public function __construct(
                public readonly int $requiredInt = 0
            ) {}
        };

        $invalidData = [
            'requiredInt' => 'not_a_number_string',
        ];

        $this->expectException(MappingError::class);
        $mapper->map($testClass::class, $invalidData);
    }

    #[Test]
    public function createdMapperHandlesBasicClassMapping(): void
    {
        $mapper = $this->factory->create();

        $testClass = new class () {
            public function __construct(
                public readonly string $name = 'default_name',
                public readonly int $value = 42
            ) {}
        };

        $data = [
            'name' => 'test_name',
            'value' => 100,
        ];

        $result = $mapper->map($testClass::class, $data);

        self::assertInstanceOf($testClass::class, $result);
        self::assertSame('test_name', $result->name);
        self::assertSame(100, $result->value);
    }
}
