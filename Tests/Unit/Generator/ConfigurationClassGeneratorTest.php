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

namespace mteu\TypedExtConf\Tests\Unit\Generator;

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;
use mteu\TypedExtConf\Generator\ConfigurationClassGenerator;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * ConfigurationClassGeneratorTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(ConfigurationClassGenerator::class)]
final class ConfigurationClassGeneratorTest extends Framework\TestCase
{
    private ConfigurationClassGenerator $generator;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->generator = new ConfigurationClassGenerator();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
        $this->tempFiles = [];
    }

    #[Test]
    public function generateThrowsExceptionWhenNoPropertiesProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one property must be defined');

        $this->generator->generate('test_extension', 'TestConfiguration', []);
    }

    #[Test]
    public function generatedClassHasExpectedStructure(): void
    {
        $reflection = $this->generateAndReflect(
            'valid_class_ext',
            'ValidClassConfiguration',
            [
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
            ],
        );

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        $extensionConfigAttrs = $reflection->getAttributes(ExtensionConfig::class);
        self::assertCount(1, $extensionConfigAttrs);
        self::assertSame('valid_class_ext', $extensionConfigAttrs[0]->newInstance()->extensionKey);

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        self::assertCount(2, $parameters);

        self::assertSame('apiKey', $parameters[0]->getName());
        self::assertSame('string', self::namedTypeName($parameters[0]));
        self::assertSame('default-key', $parameters[0]->getDefaultValue());

        $apiKeyAttribute = $parameters[0]->getAttributes(ExtConfProperty::class)[0]->newInstance();
        self::assertSame('api.key', $apiKeyAttribute->path);
        self::assertTrue($apiKeyAttribute->required);

        self::assertSame('timeout', $parameters[1]->getName());
        self::assertSame('int', self::namedTypeName($parameters[1]));
        self::assertSame(30, $parameters[1]->getDefaultValue());

        $timeoutAttribute = $parameters[1]->getAttributes(ExtConfProperty::class)[0]->newInstance();
        self::assertNull($timeoutAttribute->path);
        self::assertFalse($timeoutAttribute->required);
    }

    #[Test]
    public function generateHandlesNamespaceGeneration(): void
    {
        $properties = [['name' => 'test', 'type' => 'string']];

        $result1 = $this->generator->generate('my_extension_one', 'Config', $properties);
        self::assertStringContainsString('namespace My\\ExtensionOne\\Configuration;', $result1);

        $result2 = $this->generator->generate('singleword', 'Config', $properties);
        self::assertStringContainsString('namespace Vendor\\Singleword\\Configuration;', $result2);
    }

    #[Test]
    public function generateHandlesVariousDefaultValueTypes(): void
    {
        $reflection = $this->generateAndReflect(
            'various_defaults_ext',
            'VariousDefaultsConfiguration',
            [
                ['name' => 'stringVal', 'type' => 'string', 'default' => 'test'],
                ['name' => 'intVal', 'type' => 'int', 'default' => 42],
                ['name' => 'boolVal', 'type' => 'bool', 'default' => true],
                ['name' => 'arrayVal', 'type' => 'array', 'default' => ['a', 'b']],
                ['name' => 'nullVal', 'type' => 'string', 'default' => null],
            ],
        );

        $parameters = $reflection->getConstructor()?->getParameters() ?? [];

        self::assertSame('test', $parameters[0]->getDefaultValue());
        self::assertSame(42, $parameters[1]->getDefaultValue());
        self::assertTrue($parameters[2]->getDefaultValue());
        self::assertSame(['a', 'b'], $parameters[3]->getDefaultValue());
        self::assertNull($parameters[4]->getDefaultValue());
        self::assertTrue($parameters[4]->allowsNull());
    }

    #[Test]
    public function generateIgnoresInvalidProperties(): void
    {
        $reflection = $this->generateAndReflect(
            'ignores_invalid_ext',
            'IgnoresInvalidConfiguration',
            [
                ['name' => 'validProp', 'type' => 'string'],
                ['name' => null, 'type' => 'string'],
                ['name' => 'invalidType', 'type' => null],
            ],
        );

        $parameters = $reflection->getConstructor()?->getParameters() ?? [];

        self::assertCount(1, $parameters);
        self::assertSame('validProp', $parameters[0]->getName());
    }

    #[Test]
    public function generateCreatesSyntacticallyValidPhp(): void
    {
        $properties = [
            [
                'name' => 'complexValue',
                'type' => 'string',
                'default' => 'test\'s "quoted" value\\with\\backslashes',
                'path' => 'complex.path',
                'required' => true,
            ],
        ];

        $result = $this->generator->generate('test_ext', 'Config', $properties);

        $tempFile = tempnam(sys_get_temp_dir(), 'php_syntax_test');
        self::assertIsString($tempFile);
        $this->tempFiles[] = $tempFile;
        file_put_contents($tempFile, $result);

        $syntaxCheck = shell_exec("php -l $tempFile 2>&1");
        self::assertIsString($syntaxCheck);
        self::assertStringContainsString('No syntax errors detected', $syntaxCheck);
    }

    /**
     * @param list<array<string, mixed>> $properties
     * @return \ReflectionClass<object>
     */
    private function generateAndReflect(string $extensionKey, string $className, array $properties): \ReflectionClass
    {
        /** @var list<array{name: string, type: string, default?: mixed, path?: string, required?: bool, label?: string}> $typedProperties */
        $typedProperties = $properties;

        $code = $this->generator->generate($extensionKey, $className, $typedProperties);

        $tempFile = tempnam(sys_get_temp_dir(), 'gen_class_');
        self::assertIsString($tempFile);
        $this->tempFiles[] = $tempFile;
        file_put_contents($tempFile, $code);

        $fqn = $this->resolveFullyQualifiedName($extensionKey, $className);

        if (!class_exists($fqn, false)) {
            require $tempFile;
        }

        self::assertTrue(class_exists($fqn), "Generated class {$fqn} was not loaded.");

        return new \ReflectionClass($fqn);
    }

    private static function namedTypeName(\ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        return $type instanceof \ReflectionNamedType ? $type->getName() : null;
    }

    private function resolveFullyQualifiedName(string $extensionKey, string $className): string
    {
        $parts = explode('_', $extensionKey);
        $namespaceParts = array_map(ucfirst(...), $parts);
        $vendor = array_shift($namespaceParts);
        $extension = implode('', $namespaceParts);

        $namespace = $extension === ''
            ? "Vendor\\{$vendor}\\Configuration"
            : "{$vendor}\\{$extension}\\Configuration";

        return "{$namespace}\\{$className}";
    }
}
