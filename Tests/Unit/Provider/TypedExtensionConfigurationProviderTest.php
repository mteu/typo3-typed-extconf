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

namespace mteu\TypedExtConf\Tests\Unit\Provider;

use mteu\TypedExtConf\Exception\ConfigurationException;
use mteu\TypedExtConf\Exception\SchemaValidationException;
use mteu\TypedExtConf\Mapper\TreeMapperFactory;
use mteu\TypedExtConf\Provider\TypedExtensionConfigurationProvider;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\ApiConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\CircularChildTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\CircularParentTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\ComplexTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\ErrorTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\InvalidExtensionConfigTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\MultiNestedTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\NestedTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\RepeatedNestedTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\RequiredTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\SecurityConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\SelfReferencingTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\SimpleTestConfiguration;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * TypedExtensionConfigurationProviderTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(TypedExtensionConfigurationProvider::class)]
#[Framework\Attributes\CoversClass(SchemaValidationException::class)]
#[AllowMockObjectsWithoutExpectations]
final class TypedExtensionConfigurationProviderTest extends Framework\TestCase
{
    private const SECRET = 'TOP-SECRET-5f2b9c0e';

    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private TypedExtensionConfigurationProvider $subject;

    protected function setUp(): void
    {
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->subject = new TypedExtensionConfigurationProvider(
            $this->extensionConfiguration,
            new TreeMapperFactory(),
        );
    }

    #[Test]
    public function mapSimpleConfiguration(): void
    {
        $this->stubExtensionConfig('test_ext', [
            'basic' => [
                'string' => 'test_value',
                'integer' => '123',
                'boolean' => '1',
                'float' => '2.71',
            ],
        ]);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame('test_value', $result->stringValue);
        self::assertSame(123, $result->intValue);
        self::assertTrue($result->boolValue);
        self::assertSame(2.71, $result->floatValue);
    }

    #[Test]
    public function mapWithDefaults(): void
    {
        $this->stubExtensionConfig('test_ext', []);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame('default', $result->stringValue);
        self::assertSame(42, $result->intValue);
        self::assertFalse($result->boolValue);
        self::assertSame(3.14, $result->floatValue);
    }

    #[Test]
    public function mapWithTypeConversion(): void
    {
        $this->stubExtensionConfig('test_ext', [
            'basic' => [
                'string' => 123,
                'integer' => '456',
                'boolean' => 'true',
                'float' => '9.81',
            ],
        ]);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame('123', $result->stringValue);
        self::assertSame(456, $result->intValue);
        self::assertTrue($result->boolValue);
        self::assertSame(9.81, $result->floatValue);
    }

    /**
     * @return \Generator<string, array{mixed, bool}>
     */
    public static function booleanConversionDataProvider(): \Generator
    {
        yield 'string 1' => ['1', true];
        yield 'string 0' => ['0', false];
        yield 'string true' => ['true', true];
        yield 'string false' => ['false', false];
    }

    #[Test]
    #[DataProvider('booleanConversionDataProvider')]
    public function mapBooleanConversions(mixed $input, bool $expected): void
    {
        $this->stubExtensionConfig('test_ext', ['basic' => ['boolean' => $input]]);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame($expected, $result->boolValue);
    }

    #[Test]
    public function mapNestedConfiguration(): void
    {
        $this->stubExtensionConfig('complex_ext', [
            'main' => ['endpoint' => '/custom/api'],
            'nested' => [
                'enabled' => '1',
                'priority' => '99',
                'name' => 'test_nested',
            ],
            'simpleValue' => 'direct_value',
        ]);

        $result = $this->subject->get(ComplexTestConfiguration::class);

        self::assertSame('/custom/api', $result->endpoint);
        self::assertSame('direct_value', $result->simpleValue);

        self::assertInstanceOf(NestedTestConfiguration::class, $result->nestedConfig);
        self::assertTrue($result->nestedConfig->enabled);
        self::assertSame(99, $result->nestedConfig->priority);
        self::assertSame('test_nested', $result->nestedConfig->name);
    }

    #[Test]
    public function mapNestedConfigurationWithDefaults(): void
    {
        $this->stubExtensionConfig('complex_ext', [
            'main' => ['endpoint' => '/api/v2'],
        ]);

        $result = $this->subject->get(ComplexTestConfiguration::class);

        self::assertSame('/api/v2', $result->endpoint);
        self::assertSame('fallback', $result->simpleValue);
        self::assertFalse($result->nestedConfig->enabled);
        self::assertSame(10, $result->nestedConfig->priority);
        self::assertSame('', $result->nestedConfig->name);
    }

    #[Test]
    public function mapRequiredFieldPresent(): void
    {
        $this->stubExtensionConfig('test_ext', [
            'required' => ['value' => 'present'],
        ]);

        $result = $this->subject->get(RequiredTestConfiguration::class);

        self::assertSame('present', $result->requiredValue);
        self::assertSame('optional', $result->optionalValue);
    }

    #[Test]
    public function mapRequiredFieldMissingThrowsException(): void
    {
        $this->stubExtensionConfig('test_ext', [
            'optional' => ['value' => 'only_optional'],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Required configuration key "required.value" is missing');

        $this->subject->get(RequiredTestConfiguration::class);
    }

    #[Test]
    public function mapMissingExtensionConfigAttributeThrowsException(): void
    {
        $class = new class () {
            public function __construct(
                public readonly string $value = 'test',
            ) {}
        };

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must have an #[ExtensionConfig] attribute');

        $this->subject->get($class::class);
    }

    #[Test]
    public function mapNullExtensionKeyThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must have an #[ExtensionConfig] attribute');

        $this->subject->get(InvalidExtensionConfigTestConfiguration::class);
    }

    #[Test]
    public function mapNonInstantiableClassThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must be instantiable');

        $this->subject->get(\DateTimeInterface::class);
    }

    #[Test]
    public function mapExtensionConfigurationRetrievalFailure(): void
    {
        $this->extensionConfiguration->method('get')
            ->with('test_ext')
            ->willThrowException(new \Exception('Extension configuration not found'));

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Failed to retrieve configuration for extension "test_ext"');

        $this->subject->get(SimpleTestConfiguration::class);
    }

    #[Test]
    public function mapWithNullConfiguration(): void
    {
        $this->stubExtensionConfig('test_ext', null);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame('default', $result->stringValue);
        self::assertSame(42, $result->intValue);
        self::assertFalse($result->boolValue);
        self::assertSame(3.14, $result->floatValue);
    }

    #[Test]
    public function mapWithPartialConfiguration(): void
    {
        $this->stubExtensionConfig('test_ext', [
            'basic' => ['string' => 'partial_test'],
        ]);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame('partial_test', $result->stringValue);
        self::assertSame(42, $result->intValue);
        self::assertFalse($result->boolValue);
        self::assertSame(3.14, $result->floatValue);
    }

    #[Test]
    public function mapValinorMappingErrorIsWrappedInSchemaValidationException(): void
    {
        $this->stubExtensionConfig(
            'error_test',
            ['value' => ['array', 'where', 'int', 'expected']],
        );

        $this->expectException(SchemaValidationException::class);
        $this->expectExceptionMessage('Failed to map configuration for extension "error_test"');

        $this->subject->get(ErrorTestConfiguration::class);
    }

    #[Test]
    public function mapValinorMappingErrorIsNotChainedAsPreviousException(): void
    {
        $this->stubExtensionConfig(
            'error_test',
            ['value' => ['array', 'where', 'int', 'expected']],
        );

        $exception = $this->catchSchemaValidationException(ErrorTestConfiguration::class);

        self::assertNull($exception->getPrevious());
    }

    /**
     * @return \Generator<string, array{string, class-string, array<string, mixed>, string, string}>
     */
    public static function invalidSecretConfigurationDataProvider(): \Generator
    {
        yield 'array given for int' => [
            'error_test',
            ErrorTestConfiguration::class,
            ['value' => ['secret' => self::SECRET, 'other' => 1]],
            'value',
            '`int`',
        ];
        yield 'list given for string' => [
            'test_ext',
            SimpleTestConfiguration::class,
            ['basic' => ['string' => [self::SECRET]]],
            'stringValue',
            '`string`',
        ];
        yield 'array given for string in nested object' => [
            'complex_ext',
            ComplexTestConfiguration::class,
            ['nested' => ['name' => ['secret' => self::SECRET]]],
            'nestedConfig.name',
            '`string`',
        ];
    }

    /**
     * @param class-string $configClass
     * @param array<string, mixed> $rawConfig
     */
    #[Test]
    #[DataProvider('invalidSecretConfigurationDataProvider')]
    public function mapValinorMappingErrorReportsPathAndExpectedTypeButNotInvalidValue(
        string $extensionKey,
        string $configClass,
        array $rawConfig,
        string $expectedPath,
        string $expectedType,
    ): void {
        $this->stubExtensionConfig($extensionKey, $rawConfig);

        $exception = $this->catchSchemaValidationException($configClass);

        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            self::assertStringNotContainsString(self::SECRET, $current->getMessage());
        }
        self::assertStringNotContainsString(self::SECRET, (string)$exception);
        self::assertSame($extensionKey, $exception->extensionKey);
        self::assertSame([$expectedPath], $exception->invalidPaths);
        self::assertStringContainsString(
            sprintf(' - %s: expected %s', $expectedPath, $expectedType),
            $exception->getMessage(),
        );
    }

    #[Test]
    public function mapMultiNestedConfiguration(): void
    {
        $this->stubExtensionConfig('multi_nested_ext', [
            'api' => [
                'endpoint' => '/monitoring/api',
                'url' => 'https://custom.example.com',
                'timeout' => '60',
                'retries' => '5',
            ],
            'security' => [
                'token' => 'secret-token-123',
                'enabled' => '1',
            ],
            'nested' => [
                'enabled' => '1',
                'priority' => '15',
                'name' => 'multi_nested_test',
            ],
        ]);

        $result = $this->subject->get(MultiNestedTestConfiguration::class);

        self::assertSame('/monitoring/api', $result->endpoint);

        self::assertInstanceOf(ApiConfiguration::class, $result->apiConfiguration);
        self::assertSame('https://custom.example.com', $result->apiConfiguration->url);
        self::assertSame(60, $result->apiConfiguration->timeout);
        self::assertSame(5, $result->apiConfiguration->retries);

        self::assertInstanceOf(SecurityConfiguration::class, $result->securityConfiguration);
        self::assertSame('secret-token-123', $result->securityConfiguration->token);
        self::assertTrue($result->securityConfiguration->enabled);

        self::assertInstanceOf(NestedTestConfiguration::class, $result->nestedTestConfiguration);
        self::assertTrue($result->nestedTestConfiguration->enabled);
        self::assertSame(15, $result->nestedTestConfiguration->priority);
        self::assertSame('multi_nested_test', $result->nestedTestConfiguration->name);
    }

    #[Test]
    public function mapMultiNestedConfigurationWithDefaults(): void
    {
        $this->stubExtensionConfig('multi_nested_ext', [
            'api' => ['endpoint' => '/api/v1'],
        ]);

        $result = $this->subject->get(MultiNestedTestConfiguration::class);

        self::assertSame('/api/v1', $result->endpoint);
        self::assertSame('https://api.example.com', $result->apiConfiguration->url);
        self::assertSame('', $result->securityConfiguration->token);
    }

    /**
     * @param class-string $configClass
     */
    private function catchSchemaValidationException(string $configClass): SchemaValidationException
    {
        try {
            $this->subject->get($configClass);
        } catch (SchemaValidationException $exception) {
            return $exception;
        }

        self::fail('Expected SchemaValidationException was not thrown');
    }

    /**
     * @return \Generator<string, array{class-string, string}>
     */
    public static function circularConfigurationDataProvider(): \Generator
    {
        yield 'class referencing itself' => [
            SelfReferencingTestConfiguration::class,
            SelfReferencingTestConfiguration::class . ' -> ' . SelfReferencingTestConfiguration::class,
        ];
        yield 'nested class referencing its parent' => [
            CircularParentTestConfiguration::class,
            CircularParentTestConfiguration::class
                . ' -> ' . CircularChildTestConfiguration::class
                . ' -> ' . CircularParentTestConfiguration::class,
        ];
    }

    /**
     * @param class-string $configClass
     */
    #[Test]
    #[DataProvider('circularConfigurationDataProvider')]
    public function mapCircularConfigurationThrowsExceptionNamingTheCycle(
        string $configClass,
        string $expectedCycle,
    ): void {
        $this->stubExtensionConfig('circular_test', []);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionCode(1795285635);
        $this->expectExceptionMessage('Circular configuration reference detected: ' . $expectedCycle);

        $this->subject->get($configClass);
    }

    #[Test]
    public function mapSameNestedClassInSiblingParametersIsNotCircular(): void
    {
        $this->stubExtensionConfig('repeated_nested_ext', [
            'nested' => ['name' => 'shared'],
        ]);

        $result = $this->subject->get(RepeatedNestedTestConfiguration::class);

        self::assertSame('shared', $result->first->name);
        self::assertSame('shared', $result->second->name);
    }

    private function stubExtensionConfig(string $extensionKey, mixed $value): void
    {
        $this->extensionConfiguration->method('get')
            ->with($extensionKey)
            ->willReturn($value);
    }
}
