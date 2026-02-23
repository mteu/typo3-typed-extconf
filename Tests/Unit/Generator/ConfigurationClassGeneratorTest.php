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
    public function generateCreatesValidPhpClass(): void
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
        ];

        $result = $this->generator->generate('test_extension', 'TestConfiguration', $properties);

        // Test basic PHP structure
        self::assertStringContainsString('<?php', $result);
        self::assertStringContainsString('declare(strict_types=1);', $result);
        self::assertStringContainsString('namespace Test\\Extension\\Configuration;', $result);

        // Test class structure
        self::assertStringContainsString('final readonly class TestConfiguration', $result);
        self::assertStringContainsString('#[ExtensionConfig(extensionKey: \'test_extension\')]', $result);

        // Test properties with PHP defaults (not in attributes)
        self::assertStringContainsString('#[ExtConfProperty(path: \'api.key\', required: true)]', $result);
        self::assertStringContainsString('public string $apiKey = \'default-key\',', $result);
        self::assertStringContainsString('#[ExtConfProperty]', $result);
        self::assertStringContainsString('public int $timeout = 30,', $result);
    }

    #[Test]
    public function generateHandlesNamespaceGeneration(): void
    {
        $properties = [['name' => 'test', 'type' => 'string']];

        // Test multi-part extension key
        $result1 = $this->generator->generate('my_extension', 'Config', $properties);
        self::assertStringContainsString('namespace My\\Extension\\Configuration;', $result1);

        // Test single-part extension key
        $result2 = $this->generator->generate('myext', 'Config', $properties);
        self::assertStringContainsString('namespace Vendor\\Myext\\Configuration;', $result2);
    }

    #[Test]
    public function generateHandlesVariousDefaultValueTypes(): void
    {
        $properties = [
            ['name' => 'stringVal', 'type' => 'string', 'default' => 'test'],
            ['name' => 'intVal', 'type' => 'int', 'default' => 42],
            ['name' => 'boolVal', 'type' => 'bool', 'default' => true],
            ['name' => 'arrayVal', 'type' => 'array', 'default' => ['a', 'b']],
            ['name' => 'nullVal', 'type' => 'string', 'default' => null],
        ];

        $result = $this->generator->generate('test_ext', 'Config', $properties);

        self::assertStringContainsString('public string $stringVal = \'test\',', $result);
        self::assertStringContainsString('public int $intVal = 42,', $result);
        self::assertStringContainsString('public bool $boolVal = true,', $result);
        self::assertStringContainsString('public array $arrayVal = [\'a\', \'b\'],', $result);
        self::assertStringContainsString('public ?string $nullVal = null,', $result);
    }

    #[Test]
    public function generateIgnoresInvalidProperties(): void
    {
        /** @var array<mixed> $properties */
        $properties = [
            ['name' => 'validProp', 'type' => 'string'],
            ['name' => null, 'type' => 'string'], // Invalid name
            ['name' => 'invalidType', 'type' => null], // Invalid type
        ];

        // ignored via configuration (intentionally testing with invalid input types)
        $result = $this->generator->generate('test_ext', 'Config', $properties);

        self::assertStringContainsString('public string $validProp', $result);
        self::assertStringNotContainsString('$null', $result);
        self::assertStringNotContainsString('$invalidType', $result);
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

        // Verify generated PHP is syntactically valid
        $tempFile = tempnam(sys_get_temp_dir(), 'php_syntax_test');
        file_put_contents($tempFile, $result);

        $syntaxCheck = shell_exec("php -l $tempFile 2>&1");
        unlink($tempFile);

        self::assertIsString($syntaxCheck);
        self::assertStringContainsString('No syntax errors detected', $syntaxCheck);
    }
}
