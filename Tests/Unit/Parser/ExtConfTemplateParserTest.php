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

namespace mteu\TypedExtConf\Tests\Unit\Parser;

use mteu\TypedExtConf\Parser\ExtConfTemplateParser;
use PHPUnit\Framework;
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

        // Check API key field
        self::assertSame('apiKey', $result[0]['name']);
        self::assertArrayHasKey('path', $result[0]);
        self::assertSame('api.key', $result[0]['path']);
        self::assertSame('string', $result[0]['type']);
        self::assertArrayHasKey('default', $result[0]);
        self::assertSame('default-key', $result[0]['default']);

        // Check timeout field
        self::assertSame('timeout', $result[1]['name']);
        self::assertSame('int', $result[1]['type']);
        self::assertArrayHasKey('default', $result[1]);
        self::assertSame(30, $result[1]['default']);

        // Check boolean field
        self::assertSame('enabled', $result[2]['name']);
        self::assertSame('bool', $result[2]['type']);
        self::assertArrayHasKey('default', $result[2]);
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
        self::assertArrayHasKey('default', $result[0]);
        self::assertSame('test_value', $result[0]['default']);

        self::assertSame('numericField', $result[1]['name']);
        self::assertSame('string', $result[1]['type']); // Without type comment, defaults to string
        self::assertArrayHasKey('default', $result[1]);
        self::assertSame('42', $result[1]['default']);
    }

    #[Test]
    public function parseEmptyAndMalformedLines(): void
    {
        $templateFile = __DIR__ . '/../Fixture/ExtConfTemplate/malformed.txt';
        $result = $this->parser->parse($templateFile);

        // Should only parse valid lines
        self::assertCount(2, $result);
        self::assertSame('validField', $result[0]['name']);
        self::assertSame('anotherField', $result[1]['name']);
    }

    #[Test]
    public function parseArrayTypeValues(): void
    {
        $templateFile = __DIR__ . '/../Fixture/ExtConfTemplate/array_types.txt';
        $result = $this->parser->parse($templateFile);

        // TYPO3 array type maps to PHP string (not actual array conversion)
        self::assertSame('string', $result[0]['type']);
        self::assertArrayHasKey('default', $result[0]);
        self::assertSame('item1,item2,item3', $result[0]['default']);
        self::assertArrayHasKey('typo3_type', $result[0]);
        self::assertSame('array', $result[0]['typo3_type']);

        self::assertSame('string', $result[1]['type']);
        self::assertArrayHasKey('default', $result[1]);
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
        file_put_contents($tempFile, 'content');

        // Make file unreadable by changing permissions
        chmod($tempFile, 0000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read template file:');

        try {
            $this->parser->parse($tempFile);
        } finally {
            // Restore permissions for cleanup
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
        self::assertArrayHasKey('default', $result[0]);
        self::assertTrue($result[0]['default']);

        self::assertSame('bool', $result[1]['type']);
        self::assertArrayHasKey('default', $result[1]);
        self::assertTrue($result[1]['default']);

        self::assertSame('bool', $result[2]['type']);
        self::assertArrayHasKey('default', $result[2]);
        self::assertFalse($result[2]['default']);
    }

}
