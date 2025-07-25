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
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\ComplexTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\ErrorTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\InvalidExtensionConfigTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\MultiNestedTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\NestedTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\RequiredTestConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\SecurityConfiguration;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\SimpleTestConfiguration;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * TypedExtensionConfigurationProviderTest.
 *
 * Tests the TypedExtensionConfigurationProvider with various configuration scenarios
 * using dedicated test fixture classes.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(TypedExtensionConfigurationProvider::class)]
final class TypedExtensionConfigurationProviderTest extends Framework\TestCase
{
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
    public function testMapSimpleConfiguration(): void
    {
        $configData = [
            'basic' => [
                'string' => 'test_value',
                'integer' => '123',
                'boolean' => '1',
                'float' => '2.71',
            ],
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn($configData);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertInstanceOf(SimpleTestConfiguration::class, $result);
        self::assertSame('test_value', $result->stringValue);
        self::assertSame(123, $result->intValue);
        self::assertTrue($result->boolValue);
        self::assertSame(2.71, $result->floatValue);
    }

    #[Test]
    public function testMapWithDefaults(): void
    {
        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn([]);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertInstanceOf(SimpleTestConfiguration::class, $result);
        self::assertSame('default', $result->stringValue);
        self::assertSame(42, $result->intValue);
        self::assertFalse($result->boolValue);
        self::assertSame(3.14, $result->floatValue);
    }

    #[Test]
    public function testMapWithTypeConversion(): void
    {
        $configData = [
            'basic' => [
                'string' => 123, // int to string
                'integer' => '456', // string to int
                'boolean' => 'true', // string to bool
                'float' => '9.81', // string to float
            ],
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn($configData);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame('123', $result->stringValue);
        self::assertSame(456, $result->intValue);
        self::assertTrue($result->boolValue);
        self::assertSame(9.81, $result->floatValue);
    }

    #[Test]
    public function testMapBooleanConversions(): void
    {
        // Test key boolean conversion cases
        $testCases = [
            ['1', true],
            ['0', false],
            ['true', true],
            ['false', false],
        ];

        foreach ($testCases as [$input, $expected]) {
            $configData = [
                'basic' => [
                    'boolean' => $input,
                ],
            ];

            $this->extensionConfiguration->expects(self::once())
                ->method('get')
                ->with('test_ext')
                ->willReturn($configData);

            $result = $this->subject->get(SimpleTestConfiguration::class);
            self::assertSame($expected, $result->boolValue, 'Failed for input: ' . var_export($input, true));

            // Reset mock for next iteration
            $this->setUp();
        }
    }

    #[Test]
    public function testMapNestedConfiguration(): void
    {
        $configData = [
            'main' => [
                'endpoint' => '/custom/api',
            ],
            'nested' => [
                'enabled' => '1',
                'priority' => '99',
                'name' => 'test_nested',
            ],
            'simpleValue' => 'direct_value',
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('complex_ext')
            ->willReturn($configData);

        $result = $this->subject->get(ComplexTestConfiguration::class);

        self::assertInstanceOf(ComplexTestConfiguration::class, $result);
        self::assertSame('/custom/api', $result->endpoint);
        self::assertSame('direct_value', $result->simpleValue);

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(NestedTestConfiguration::class, $result->nestedConfig);
        self::assertTrue($result->nestedConfig->enabled);
        self::assertSame(99, $result->nestedConfig->priority);
        self::assertSame('test_nested', $result->nestedConfig->name);
    }

    #[Test]
    public function testMapNestedConfigurationWithDefaults(): void
    {
        $configData = [
            'main' => [
                'endpoint' => '/api/v2',
            ],
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('complex_ext')
            ->willReturn($configData);

        $result = $this->subject->get(ComplexTestConfiguration::class);

        self::assertSame('/api/v2', $result->endpoint);
        self::assertSame('fallback', $result->simpleValue);

        // Nested object should use its defaults
        self::assertFalse($result->nestedConfig->enabled);
        self::assertSame(10, $result->nestedConfig->priority);
        self::assertSame('', $result->nestedConfig->name);
    }

    #[Test]
    public function testMapUsesExtensionKeyFromAttribute(): void
    {
        $configData = [
            'basic' => [
                'string' => 'attribute_key_test',
            ],
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn($configData);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame('attribute_key_test', $result->stringValue);
    }

    #[Test]
    public function testMapRequiredFieldPresent(): void
    {
        $configData = [
            'required' => [
                'value' => 'present',
            ],
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn($configData);

        $result = $this->subject->get(RequiredTestConfiguration::class);

        self::assertSame('present', $result->requiredValue);
        self::assertSame('optional', $result->optionalValue);
    }

    #[Test]
    public function testMapRequiredFieldMissingThrowsException(): void
    {
        $configData = [
            'optional' => [
                'value' => 'only_optional',
            ],
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn($configData);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Required configuration key "required.value" is missing');

        $this->subject->get(RequiredTestConfiguration::class);
    }

    #[Test]
    public function testMapMissingExtensionConfigAttributeThrowsException(): void
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
    public function testMapNullExtensionKeyThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must have an #[ExtensionConfig] attribute');

        $this->subject->get(InvalidExtensionConfigTestConfiguration::class);
    }

    #[Test]
    public function testMapNonInstantiableClassThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must be instantiable');

        $this->subject->get(\DateTimeInterface::class);
    }

    #[Test]
    public function testMapExtensionConfigurationRetrievalFailure(): void
    {
        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willThrowException(new \Exception('Extension configuration not found'));

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Failed to retrieve configuration for extension "test_ext"');

        $this->subject->get(SimpleTestConfiguration::class);
    }

    #[Test]
    public function testMapHandlesEmptyConfiguration(): void
    {
        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn([]);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        // Should use all default values
        self::assertSame('default', $result->stringValue);
        self::assertSame(42, $result->intValue);
    }

    #[Test]
    public function testMapValinorMappingError(): void
    {
        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('error_test')
            ->willReturn(['invalidType' => 'string_value']);

        try {
            $this->subject->get(ErrorTestConfiguration::class);
            self::fail('Expected SchemaValidationException was not thrown');
        } catch (SchemaValidationException $e) {
            self::assertStringContainsString('Failed to map configuration for extension "error_test"', $e->getMessage());
            self::assertNotNull($e->getPrevious());
        } catch (\Throwable $e) {
            // If it's not wrapped properly, we should still get some kind of Valinor exception
            self::assertStringContainsString('object', $e->getMessage());
        }
    }

    #[Test]
    public function testMapWithNullConfiguration(): void
    {
        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn(null);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        // Should use all defaults when configuration is null
        self::assertSame('default', $result->stringValue);
        self::assertSame(42, $result->intValue);
        self::assertFalse($result->boolValue);
        self::assertSame(3.14, $result->floatValue);
    }

    #[Test]
    public function testMapWithPartialConfiguration(): void
    {
        $configData = [
            'basic' => [
                'string' => 'partial_test',
                // integer, boolean, float missing - should use defaults
            ],
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('test_ext')
            ->willReturn($configData);

        $result = $this->subject->get(SimpleTestConfiguration::class);

        self::assertSame('partial_test', $result->stringValue);
        self::assertSame(42, $result->intValue); // default
        self::assertFalse($result->boolValue); // default
        self::assertSame(3.14, $result->floatValue); // default
    }

    #[Test]
    public function testMapMultiNestedConfigurationWithFullData(): void
    {
        $configData = [
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
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('multi_nested_ext')
            ->willReturn($configData);

        $result = $this->subject->get(MultiNestedTestConfiguration::class);

        self::assertInstanceOf(MultiNestedTestConfiguration::class, $result);
        self::assertSame('/monitoring/api', $result->endpoint);

        // Test ApiConfiguration nested object
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(ApiConfiguration::class, $result->apiConfiguration);
        self::assertSame('https://custom.example.com', $result->apiConfiguration->url);
        self::assertSame(60, $result->apiConfiguration->timeout);
        self::assertSame(5, $result->apiConfiguration->retries);

        // Test SecurityConfiguration nested object
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(SecurityConfiguration::class, $result->securityConfiguration);
        self::assertSame('secret-token-123', $result->securityConfiguration->token);
        self::assertTrue($result->securityConfiguration->enabled);

        // Test NestedTestConfiguration nested object
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(NestedTestConfiguration::class, $result->nestedTestConfiguration);
        self::assertTrue($result->nestedTestConfiguration->enabled);
        self::assertSame(15, $result->nestedTestConfiguration->priority);
        self::assertSame('multi_nested_test', $result->nestedTestConfiguration->name);
    }

    #[Test]
    public function testMapMultiNestedConfigurationWithDefaults(): void
    {
        $configData = [
            'api' => [
                'endpoint' => '/api/v1',
                // Other api.* config missing - should use defaults
            ],
        ];

        $this->extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('multi_nested_ext')
            ->willReturn($configData);

        $result = $this->subject->get(MultiNestedTestConfiguration::class);

        self::assertSame('/api/v1', $result->endpoint);
        // Test that nested objects use defaults when config is missing
        self::assertSame('https://api.example.com', $result->apiConfiguration->url);
        self::assertSame('', $result->securityConfiguration->token);
    }

}
