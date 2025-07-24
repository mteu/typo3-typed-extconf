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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace mteu\TypedExtConf\Tests\Unit\Generator;

use mteu\TypedExtConf\Generator\ConfigurationClassGenerator;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * ConfigurationClassGeneratorTest.
 *
 * Tests the ConfigurationClassGenerator for proper PHP code generation
 * using Nette PhpGenerator with various property configurations.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(ConfigurationClassGenerator::class)]
final class ConfigurationClassGeneratorTest extends Framework\TestCase
{
    private ConfigurationClassGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ConfigurationClassGenerator();
    }

    #[Test]
    public function generateThrowsExceptionWhenNoPropertiesProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one property must be defined');

        $this->generator->generate('test_extension', 'TestConfiguration', []);
    }

    #[Test]
    public function generateCreatesBasicClassWithSingleProperty(): void
    {
        $properties = [
            [
                'name' => 'apiKey',
                'type' => 'string',
            ],
        ];

        $result = $this->generator->generate('test_extension', 'TestConfiguration', $properties);

        // Test basic PHP structure
        self::assertStringContainsString('<?php', $result);
        self::assertStringContainsString('declare(strict_types=1);', $result);

        // Test namespace
        self::assertStringContainsString('namespace Test\\Extension\\Configuration;', $result);

        // Test imports
        self::assertStringContainsString('use mteu\\TypedExtConf\\Attribute\\ExtConfProperty;', $result);
        self::assertStringContainsString('use mteu\\TypedExtConf\\Attribute\\ExtensionConfig;', $result);

        // Test class structure
        self::assertStringContainsString('final readonly class TestConfiguration', $result);
        self::assertStringContainsString('#[\\ExtensionConfig(extensionKey: \'test_extension\')]', $result);

        // Test constructor and property
        self::assertStringContainsString('public function __construct(', $result);
        self::assertStringContainsString('#[\\ExtConfProperty]', $result);
        self::assertStringContainsString('public string $apiKey', $result);
    }

    #[Test]
    public function generateCreatesClassWithMultipleProperties(): void
    {
        $properties = [
            [
                'name' => 'apiKey',
                'type' => 'string',
            ],
            [
                'name' => 'timeout',
                'type' => 'int',
            ],
            [
                'name' => 'enabled',
                'type' => 'bool',
            ],
        ];

        $result = $this->generator->generate('my_extension', 'MyConfiguration', $properties);

        // Test all properties are present
        self::assertStringContainsString('public string $apiKey', $result);
        self::assertStringContainsString('public int $timeout', $result);
        self::assertStringContainsString('public bool $enabled', $result);

        // Test namespace for underscore extension key
        self::assertStringContainsString('namespace My\\Extension\\Configuration;', $result);
    }

    #[Test]
    public function generateCreatesClassWithComplexPropertyAttributes(): void
    {
        $properties = [
            [
                'name' => 'apiKey',
                'type' => 'string',
                'default' => 'default-key',
                'path' => 'api.key',
                'required' => true,
            ],
            [
                'name' => 'timeout',
                'type' => 'int',
                'default' => 30,
            ],
            [
                'name' => 'tags',
                'type' => 'array',
                'default' => ['prod', 'api'],
            ],
        ];

        $result = $this->generator->generate('complex_ext', 'ComplexConfiguration', $properties);

        // Test ExtConfProperty attributes with parameters (no default values)
        self::assertStringContainsString('#[\\ExtConfProperty(path: \'api.key\', required: true)]', $result);
        self::assertStringContainsString('#[\\ExtConfProperty]', $result);

        // Test PHP constructor defaults
        self::assertStringContainsString('public string $apiKey = \'default-key\',', $result);
        self::assertStringContainsString('public int $timeout = 30,', $result);
        self::assertStringContainsString('public array $tags = [\'prod\', \'api\'],', $result);

        // Test namespace for complex extension key
        self::assertStringContainsString('namespace Complex\\Ext\\Configuration;', $result);
    }

    #[Test]
    public function generateHandlesSinglePartExtensionKey(): void
    {
        $properties = [
            [
                'name' => 'setting',
                'type' => 'string',
            ],
        ];

        $result = $this->generator->generate('myext', 'SimpleConfiguration', $properties);

        // Test namespace for single part extension key
        self::assertStringContainsString('namespace Vendor\\Myext\\Configuration;', $result);
    }

    #[Test]
    public function generateCreatesProperClassDocumentation(): void
    {
        $properties = [
            [
                'name' => 'test',
                'type' => 'string',
            ],
        ];

        $result = $this->generator->generate('doc_test', 'DocumentedConfiguration', $properties);

        // Test class documentation
        self::assertStringContainsString('DocumentedConfiguration.', $result);
        self::assertStringContainsString('Typed configuration class for extension \'doc_test\'.', $result);
        self::assertStringContainsString('This class provides type-safe access to extension configuration properties.', $result);
        self::assertStringContainsString('Generated using mteu/typo3-typed-extconf.', $result);
    }

    #[Test]
    public function generateHandlesDifferentDefaultValueTypes(): void
    {
        $properties = [
            [
                'name' => 'stringProp',
                'type' => 'string',
                'default' => 'test value',
            ],
            [
                'name' => 'intProp',
                'type' => 'int',
                'default' => 42,
            ],
            [
                'name' => 'floatProp',
                'type' => 'float',
                'default' => 3.14,
            ],
            [
                'name' => 'boolProp',
                'type' => 'bool',
                'default' => true,
            ],
            [
                'name' => 'nullProp',
                'type' => 'string',
                'default' => null,
            ],
        ];

        $result = $this->generator->generate('types_test', 'TypesConfiguration', $properties);

        // Test PHP constructor default values
        self::assertStringContainsString('public string $stringProp = \'test value\',', $result);
        self::assertStringContainsString('public int $intProp = 42,', $result);
        self::assertStringContainsString('public float $floatProp = 3.14,', $result);
        self::assertStringContainsString('public bool $boolProp = true,', $result);
        self::assertStringContainsString('public ?string $nullProp = null,', $result);
    }

    #[Test]
    public function generateHandlesArrayDefaultValues(): void
    {
        $properties = [
            [
                'name' => 'emptyArray',
                'type' => 'array',
                'default' => [],
            ],
            [
                'name' => 'stringArray',
                'type' => 'array',
                'default' => ['one', 'two', 'three'],
            ],
            [
                'name' => 'mixedArray',
                'type' => 'array',
                'default' => ['string', 123, true],
            ],
        ];

        $result = $this->generator->generate('array_test', 'ArrayConfiguration', $properties);

        // Test PHP constructor array defaults
        self::assertStringContainsString('public array $emptyArray = [],', $result);
        self::assertStringContainsString('public array $stringArray = [\'one\', \'two\', \'three\'],', $result);
        self::assertStringContainsString('public array $mixedArray = [\'string\', 123, true],', $result);
    }

    #[Test]
    public function generateHandlesOptionalPropertyFields(): void
    {
        $properties = [
            [
                'name' => 'minimalProp',
                'type' => 'string',
            ],
            [
                'name' => 'pathOnlyProp',
                'type' => 'string',
                'path' => 'custom.path',
            ],
            [
                'name' => 'requiredOnlyProp',
                'type' => 'string',
                'required' => true,
            ],
            [
                'name' => 'defaultOnlyProp',
                'type' => 'string',
                'default' => 'default-value',
            ],
        ];

        $result = $this->generator->generate('optional_test', 'OptionalConfiguration', $properties);

        // Test minimal property (no attributes)
        self::assertStringContainsString('#[\\ExtConfProperty]', $result);

        // Test path-only attribute
        self::assertStringContainsString('#[\\ExtConfProperty(path: \'custom.path\')]', $result);

        // Test required-only attribute
        self::assertStringContainsString('#[\\ExtConfProperty(required: true)]', $result);

        // Test property with PHP constructor default (not in attribute)
        self::assertStringContainsString('#[\\ExtConfProperty]', $result);
        self::assertStringContainsString('public string $defaultOnlyProp = \'default-value\',', $result);
    }

    #[Test]
    public function generateIgnoresPropertiesWithInvalidTypes(): void
    {
        /** @var array<mixed> $properties */
        $properties = [
            [
                'name' => 'validProp',
                'type' => 'string',
            ],
            [
                'name' => null, // Invalid name
                'type' => 'string',
            ],
            [
                'name' => 'invalidTypeProp',
                'type' => null, // Invalid type
            ],
            [
                'name' => 'anotherValidProp',
                'type' => 'int',
            ],
        ];

        // @phpstan-ignore-next-line argument.type (intentionally testing with invalid input types)
        $result = $this->generator->generate('validation_test', 'ValidationConfiguration', $properties);

        // Test only valid properties are included
        self::assertStringContainsString('public string $validProp', $result);
        self::assertStringContainsString('public int $anotherValidProp', $result);

        // Test invalid properties are not included
        self::assertStringNotContainsString('$null', $result);
        self::assertStringNotContainsString('$invalidTypeProp', $result);
    }

    #[Test]
    public function generateCreatesValidPhpCode(): void
    {
        $properties = [
            [
                'name' => 'complexProp',
                'type' => 'string',
                'default' => 'test\'s "quoted" value\\with\\backslashes',
                'path' => 'complex.path',
                'required' => true,
            ],
        ];

        $result = $this->generator->generate('syntax_test', 'SyntaxConfiguration', $properties);

        // Test that the generated code is syntactically valid PHP
        // This should not throw a parse error
        $tempFile = tempnam(sys_get_temp_dir(), 'php_syntax_test');
        file_put_contents($tempFile, $result);

        $syntaxCheck = shell_exec("php -l $tempFile 2>&1");
        unlink($tempFile);

        self::assertIsString($syntaxCheck);
        self::assertStringContainsString('No syntax errors detected', $syntaxCheck);
    }
}
