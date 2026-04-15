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

namespace mteu\TypedExtConf\Tests\Unit\Parser;

use mteu\TypedExtConf\Parser\ExtConfTemplateParser;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * ExtConfTemplateParserTest.
 *
 * Tests the ExtConfTemplateParser for parsing TYPO3 ext_conf_template.txt
 * configuration files into structured property definitions.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(ExtConfTemplateParser::class)]
final class ExtConfTemplateParserTest extends Framework\TestCase
{
    private ExtConfTemplateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ExtConfTemplateParser();
    }

    #[Test]
    public function parseValidTemplateWithTypedFields(): void
    {
        $templateFile = __DIR__ . '/../Fixture/ExtConfTemplate/valid_template.txt';
        $result = $this->parser->parse($templateFile);

        self::assertCount(3, $result);

        self::assertSame('apiKey', $result[0]['name']);
        self::assertSame('api.key', $result[0]['path']);
        self::assertSame('string', $result[0]['type']);
        self::assertSame('default-key', $result[0]['default']);

        self::assertSame('timeout', $result[1]['name']);
        self::assertSame('int', $result[1]['type']);
        self::assertSame(30, $result[1]['default']);

        self::assertSame('enabled', $result[2]['name']);
        self::assertSame('bool', $result[2]['type']);
        self::assertTrue($result[2]['default']);
    }

    #[Test]
    public function parseFieldsWithoutComments(): void
    {
        $templateFile = __DIR__ . '/../Fixture/ExtConfTemplate/no_comments.txt';
        $result = $this->parser->parse($templateFile);

        self::assertCount(2, $result);
        self::assertSame('simpleField', $result[0]['name']);
        self::assertSame('string', $result[0]['type']);
        self::assertSame('test_value', $result[0]['default']);

        self::assertSame('numericField', $result[1]['name']);
        self::assertSame('string', $result[1]['type']);
        self::assertSame('42', $result[1]['default']);
    }

    #[Test]
    public function parseEmptyAndMalformedLines(): void
    {
        $templateFile = __DIR__ . '/../Fixture/ExtConfTemplate/malformed.txt';
        $result = $this->parser->parse($templateFile);

        self::assertCount(2, $result);
        self::assertSame('validField', $result[0]['name']);
        self::assertSame('anotherField', $result[1]['name']);
    }

    #[Test]
    public function parseEmptyFileReturnsEmptyArray(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ext_conf_empty_');
        self::assertIsString($tempFile);
        file_put_contents($tempFile, '');

        try {
            $result = $this->parser->parse($tempFile);
            self::assertSame([], $result);
        } finally {
            unlink($tempFile);
        }
    }

    #[Test]
    public function parseArrayTypeValues(): void
    {
        $templateFile = __DIR__ . '/../Fixture/ExtConfTemplate/array_types.txt';
        $result = $this->parser->parse($templateFile);

        self::assertSame('string', $result[0]['type']);
        self::assertSame('item1,item2,item3', $result[0]['default']);

        self::assertSame('string', $result[1]['type']);
        self::assertSame('single_value', $result[1]['default']);
    }

    #[Test]
    public function parseThrowsExceptionForNonexistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template file not found:');

        $this->parser->parse('/nonexistent/file.txt');
    }

    #[Test]
    public function parseHandlesUnreadableFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ext_conf_unreadable_test');
        self::assertIsString($tempFile);
        file_put_contents($tempFile, 'content');
        chmod($tempFile, 0000);

        if (is_readable($tempFile)) {
            chmod($tempFile, 0644);
            unlink($tempFile);
            self::markTestSkipped('Cannot test file permissions in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read template file:');

        try {
            $this->parser->parse($tempFile);
        } finally {
            chmod($tempFile, 0644);
            unlink($tempFile);
        }
    }

    #[Test]
    public function parseBooleanConversions(): void
    {
        $templateFile = __DIR__ . '/../Fixture/ExtConfTemplate/boolean_values.txt';
        $result = $this->parser->parse($templateFile);

        self::assertSame('bool', $result[0]['type']);
        self::assertTrue($result[0]['default']);

        self::assertSame('bool', $result[1]['type']);
        self::assertTrue($result[1]['default']);

        self::assertSame('bool', $result[2]['type']);
        self::assertFalse($result[2]['default']);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function typo3TypeMappingDataProvider(): \Generator
    {
        yield 'boolean' => ['boolean', 'bool'];
        yield 'bool' => ['bool', 'bool'];
        yield 'int' => ['int', 'int'];
        yield 'integer' => ['integer', 'int'];
        yield 'int+' => ['int+', 'int'];
        yield 'float' => ['float', 'float'];
        yield 'double' => ['double', 'float'];
        yield 'string' => ['string', 'string'];
        yield 'text' => ['text', 'string'];
        yield 'wrap' => ['wrap', 'string'];
        yield 'offset' => ['offset', 'string'];
        yield 'select' => ['select', 'string'];
        yield 'options' => ['options', 'string'];
        yield 'user' => ['user', 'string'];
        yield 'unknown type defaults to string' => ['foobar', 'string'];
    }

    #[Test]
    #[DataProvider('typo3TypeMappingDataProvider')]
    public function mapTypo3TypeToPhpTypeReturnsExpectedType(string $typo3Type, string $expectedPhpType): void
    {
        $reflection = new \ReflectionMethod($this->parser, 'mapTypo3TypeToPhpType');
        $result = $reflection->invoke($this->parser, $typo3Type);

        self::assertSame($expectedPhpType, $result);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function keyToPropertyNameDataProvider(): \Generator
    {
        yield 'dot notation' => ['api.key', 'apiKey'];
        yield 'underscore notation' => ['api_key', 'apiKey'];
        yield 'mixed separators' => ['some_nested.value', 'someNestedValue'];
        yield 'already camelCase' => ['apiKey', 'apiKey'];
        yield 'single word' => ['timeout', 'timeout'];
        yield 'leading dot' => ['.hidden', 'hidden'];
        yield 'leading underscore' => ['_private', 'private'];
        yield 'double dots' => ['api..key', 'apiKey'];
        yield 'triple segments' => ['a.b.c', 'aBC'];
    }

    #[Test]
    #[DataProvider('keyToPropertyNameDataProvider')]
    public function convertKeyToPropertyNameReturnsExpectedResult(string $key, string $expected): void
    {
        $reflection = new \ReflectionMethod($this->parser, 'convertKeyToPropertyName');
        $result = $reflection->invoke($this->parser, $key);

        self::assertSame($expected, $result);
    }

    /**
     * @return \Generator<string, array{string, string, mixed}>
     */
    public static function defaultValueConversionDataProvider(): \Generator
    {
        yield 'empty string stays empty' => ['', 'string', ''];
        yield 'empty bool is false' => ['', 'bool', false];
        yield 'empty int is zero' => ['', 'int', 0];
        yield 'empty float is zero' => ['', 'float', 0.0];
        yield 'string value unchanged' => ['hello', 'string', 'hello'];
        yield 'int value cast' => ['42', 'int', 42];
        yield 'float value cast' => ['3.14', 'float', 3.14];
        yield 'bool true from 1' => ['1', 'bool', true];
        yield 'bool true from yes' => ['yes', 'bool', true];
        yield 'bool true from on' => ['on', 'bool', true];
        yield 'bool false from 0' => ['0', 'bool', false];
        yield 'bool false from no' => ['no', 'bool', false];
        yield 'bool false from off' => ['off', 'bool', false];
    }

    #[Test]
    #[DataProvider('defaultValueConversionDataProvider')]
    public function convertDefaultValueReturnsExpectedResult(string $value, string $phpType, mixed $expected): void
    {
        $reflection = new \ReflectionMethod($this->parser, 'convertDefaultValue');
        $result = $reflection->invoke($this->parser, $value, $phpType);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function parseLabelWithSemicolonIsCapturedFully(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ext_conf_semicolon_');
        self::assertIsString($tempFile);

        $content = "# cat=basic; type=string; label=Timeout (in seconds; default: 30)\ntimeout = 30";
        file_put_contents($tempFile, $content);

        try {
            $result = $this->parser->parse($tempFile);

            self::assertCount(1, $result);
            self::assertSame('Timeout (in seconds; default: 30)', $result[0]['label']);
        } finally {
            unlink($tempFile);
        }
    }
}
