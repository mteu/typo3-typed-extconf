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

namespace mteu\TypedExtConf\Parser;

/**
 * ExtConfTemplateParser.
 *
 * Parse ext_conf_template.txt and extract configuration field definitions.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class ExtConfTemplateParser
{
    /**
     * @return list<array{name: string, type: string, default?: mixed, path?: string, required?: bool, label?: string}>
     */
    public function parse(string $templatePath): array
    {
        if (!file_exists($templatePath)) {
            throw new \InvalidArgumentException("Template file not found: {$templatePath}");
        }

        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read template file: {$templatePath}");
        }

        return $this->parseContent($content);
    }

    /**
     * @return list<array{name: string, type: string, default?: mixed, path?: string, required?: bool, label?: string}>
     */
    private function parseContent(string $content): array
    {
        $lines = explode("\n", $content);
        $fields = [];
        $currentComment = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Parse comment lines (configuration metadata)
            if (str_starts_with($line, '#')) {
                $currentComment = $this->parseComment($line);
                continue;
            }

            // Parse configuration line
            if (str_contains($line, '=')) {
                $field = $this->parseConfigurationLine($line, $currentComment);

                if ($field !== null) {
                    $fields[] = $field;
                }

                $currentComment = null; // Reset after processing
            }
        }

        return $fields;
    }

    /**
     * Parse TYPO3 configuration format: # cat=category; type=type; label=Label text
     *
     * @return array<string, string>|null
     */
    private function parseComment(string $line): ?array
    {
        $comment = trim(substr($line, 1));

        $result = preg_match('/cat=([^;]+);\s*type=([^;]+);\s*label=(.+)/', $comment, $matches);

        if ($result !== 1) {
            return null;
        }

        return [
            'category' => trim($matches[1]),
            'type' => trim($matches[2]),
            'label' => trim($matches[3]),
        ];
    }

    /**
     * @param array<string, string>|null $comment
     * @return array{name: string, type: string, default?: mixed, path?: string, required?: bool, label?: string}|null
     */
    private function parseConfigurationLine(string $line, ?array $comment): ?array
    {
        [$key, $defaultValue] = explode('=', $line, 2);
        $key = trim($key);
        $defaultValue = trim($defaultValue);

        if ($key === '') {
            return null;
        }

        // Determine PHP type from TYPO3 type
        $typo3Type = $comment['type'] ?? 'string';
        $phpType = $this->mapTypo3TypeToPhpType($typo3Type);

        // Convert default value based on type
        $convertedDefault = $this->convertDefaultValue($defaultValue, $phpType);

        return [
            'name' => $this->convertKeyToPropertyName($key),
            'path' => $key,
            'type' => $phpType,
            'default' => $convertedDefault,
            'required' => false, // TYPO3 ext_conf_template.txt doesn't specify required fields
            'label' => $comment['label'] ?? ucfirst(str_replace(['_', '.'], ' ', $key)),
            'category' => $comment['category'] ?? 'basic',
            'typo3_type' => $typo3Type,
        ];
    }

    private function mapTypo3TypeToPhpType(string $typo3Type): string
    {
        // Handle TYPO3 type modifiers (e.g., int+, string)
        $baseType = preg_replace('/[+\-]/', '', $typo3Type);

        return match ($baseType) {
            'boolean', 'bool' => 'bool',
            'int', 'integer' => 'int',
            'float', 'double' => 'float',
            'string', 'text', 'wrap', 'offset' => 'string',
            'select', 'options' => 'string', // Could be enum in the future
            'user' => 'string', // User functions typically return strings
            default => 'string', // Default to string for unknown types
        };
    }

    private function convertDefaultValue(string $value, string $phpType): mixed
    {
        if ($value === '') {
            return match ($phpType) {
                'bool' => false,
                'int' => 0,
                'float' => 0.0,
                'array' => [],
                default => '',
            };
        }

        return match ($phpType) {
            'bool' => $this->convertToBoolean($value),
            'int' => (int)$value,
            'float' => (float)$value,
            'array' => $this->convertToArray($value),
            default => $value,
        };
    }

    private function convertToBoolean(string $value): bool
    {
        $value = strtolower(trim($value));

        return match ($value) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off', '' => false,
            default => (bool)$value,
        };
    }

    /**
     * @return list<string>
     */
    private function convertToArray(string $value): array
    {
        if ($value === '') {
            return [];
        }

        // Handle common separators
        $separators = [',', ';', '|', "\n"];

        foreach ($separators as $separator) {
            if (str_contains($value, $separator)) {
                return array_map('trim', explode($separator, $value));
            }
        }

        // If no separator found, return single item array
        return [$value];
    }

    private function convertKeyToPropertyName(string $key): string
    {
        // Convert dot notation and underscores to camelCase
        $parts = preg_split('/[._]/', $key);
        if ($parts === false) {
            return $key;
        }

        $propertyName = array_shift($parts) ?? '';
        foreach ($parts as $part) {
            $propertyName .= ucfirst($part);
        }

        return lcfirst($propertyName);
    }
}
