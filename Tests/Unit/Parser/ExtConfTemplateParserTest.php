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
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(ExtConfTemplateParser::class)]
final class ExtConfTemplateParserTest extends Framework\TestCase
{
    private ExtConfTemplateParser $parser;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->parser = new ExtConfTemplateParser();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                chmod($tempFile, 0644);
                unlink($tempFile);
            }
        }
        $this->tempFiles = [];
    }

    #[Test]
    public function parseValidTemplateWithTypedFields(): void
    {
        $result = $this->parser->parse(__DIR__ . '/../Fixture/ExtConfTemplate/valid_template.txt');

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
        $result = $this->parser->parse(__DIR__ . '/../Fixture/ExtConfTemplate/no_comments.txt');

        self::assertCount(2, $result);
        self::assertSame('simpleField', $result[0]['name']);
        self::assertSame('string', $result[0]['type']);
        self::assertSame('test_value', $result[0]['default']);

        self::assertSame('numericField', $result[1]['name']);
        self::assertSame('string', $result[1]['type']);
        self::assertSame('42', $result[1]['default']);
    }

    #[Test]
    public function parseSkipsMalformedLinesAndKeepsValidOnes(): void
    {
        $result = $this->parser->parse(__DIR__ . '/../Fixture/ExtConfTemplate/malformed.txt');

        self::assertCount(2, $result);
        self::assertSame('validField', $result[0]['name']);
        self::assertSame('value', $result[0]['default']);
        self::assertSame('anotherField', $result[1]['name']);
        self::assertSame(123, $result[1]['default']);
    }

    #[Test]
    public function parseEmptyFileReturnsEmptyArray(): void
    {
        $result = $this->parser->parse($this->writeTempTemplate(''));
        self::assertSame([], $result);
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
        $tempFile = $this->writeTempTemplate('content');
        chmod($tempFile, 0000);

        if (is_readable($tempFile)) {
            self::markTestSkipped('Cannot test file permissions in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read template file:');

        $this->parser->parse($tempFile);
    }

    #[Test]
    public function parseLabelWithSemicolonIsCapturedFully(): void
    {
        $tempFile = $this->writeTempTemplate(
            "# cat=basic; type=string; label=Timeout (in seconds; default: 30)\ntimeout = 30"
        );

        $result = $this->parser->parse($tempFile);

        self::assertCount(1, $result);
        self::assertSame('Timeout (in seconds; default: 30)', $result[0]['label']);
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
    public function parseMapsTypo3TypesToPhpTypes(string $typo3Type, string $expectedPhpType): void
    {
        $tempFile = $this->writeTempTemplate(
            "# cat=basic; type={$typo3Type}; label=Some label\nkey = value"
        );

        $result = $this->parser->parse($tempFile);

        self::assertSame($expectedPhpType, $result[0]['type']);
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
    public function parseConvertsKeyToPropertyName(string $key, string $expected): void
    {
        $tempFile = $this->writeTempTemplate("{$key} = value");

        $result = $this->parser->parse($tempFile);

        self::assertCount(1, $result);
        self::assertSame($expected, $result[0]['name']);
        self::assertSame($key, $result[0]['path']);
    }

    /**
     * @return \Generator<string, array{string, string, mixed}>
     */
    public static function defaultValueConversionDataProvider(): \Generator
    {
        yield 'empty string stays empty' => ['', 'string', ''];
        yield 'empty bool is false' => ['', 'boolean', false];
        yield 'empty int is zero' => ['', 'int', 0];
        yield 'empty float is zero' => ['', 'float', 0.0];
        yield 'string value unchanged' => ['hello', 'string', 'hello'];
        yield 'int value cast' => ['42', 'int', 42];
        yield 'float value cast' => ['3.14', 'float', 3.14];
        yield 'bool true from 1' => ['1', 'boolean', true];
        yield 'bool true from yes' => ['yes', 'boolean', true];
        yield 'bool true from on' => ['on', 'boolean', true];
        yield 'bool false from 0' => ['0', 'boolean', false];
        yield 'bool false from no' => ['no', 'boolean', false];
        yield 'bool false from off' => ['off', 'boolean', false];
    }

    #[Test]
    #[DataProvider('defaultValueConversionDataProvider')]
    public function parseConvertsDefaultValueByType(string $value, string $typo3Type, mixed $expected): void
    {
        $tempFile = $this->writeTempTemplate(
            "# cat=basic; type={$typo3Type}; label=Some label\nkey = {$value}"
        );

        $result = $this->parser->parse($tempFile);

        self::assertSame($expected, $result[0]['default']);
    }

    private function writeTempTemplate(string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ext_conf_template_');
        self::assertIsString($tempFile);
        file_put_contents($tempFile, $content);
        $this->tempFiles[] = $tempFile;
        return $tempFile;
    }
}
