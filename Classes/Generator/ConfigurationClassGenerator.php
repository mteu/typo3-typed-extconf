<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "mteu/typo3-typed-extconf".
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

namespace mteu\TypedExtConf\Generator;

/**
 * ConfigurationClassGenerator.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class ConfigurationClassGenerator
{
    /**
     * Generate a typed configuration class.
     *
     * @param array<int, array<string, mixed>> $properties
     */
    public function generate(string $extensionKey, string $className, array $properties): string
    {
        if (count($properties) === 0) {
            throw new \InvalidArgumentException('At least one property must be defined');
        }

        $namespace = $this->generateNamespace($extensionKey);
        $classDoc = $this->generateClassDocumentation($className, $extensionKey);
        $imports = $this->generateImports();
        $extensionConfigAttribute = $this->generateExtensionConfigAttribute($extensionKey);
        $constructor = $this->generateConstructor($properties);

        return <<<PHP
<?php

declare(strict_types=1);

{$imports}

{$classDoc}
{$extensionConfigAttribute}
final readonly class {$className}
{
{$constructor}
}

PHP;
    }

    private function generateNamespace(string $extensionKey): string
    {
        // Convert extension key to namespace (e.g., my_extension -> MyExtension)
        $parts = explode('_', $extensionKey);
        $namespaceParts = array_map('ucfirst', $parts);
        $vendor = array_shift($namespaceParts);
        $extension = implode('', $namespaceParts);

        // If single part extension, use generic vendor
        if ($extension === '') {
            return "namespace Vendor\\{$vendor}\\Configuration;";
        }

        return "namespace {$vendor}\\{$extension}\\Configuration;";
    }

    private function generateClassDocumentation(string $className, string $extensionKey): string
    {
        return <<<DOC
/**
 *
 * {$className}.
 *
 * Typed configuration class for extension '{$extensionKey}'.
 *
 * This class provides type-safe access to extension configuration properties.
 * Generated using mteu/typo3-typed-extconf.
 */
DOC;
    }

    private function generateImports(): string
    {
        return <<<IMPORTS
use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;
IMPORTS;
    }

    private function generateExtensionConfigAttribute(string $extensionKey): string
    {
        return "#[ExtensionConfig(extensionKey: '{$extensionKey}')]";
    }

    /**
     * @param array<int, array<string, mixed>> $properties
     */
    private function generateConstructor(array $properties): string
    {
        $parameters = [];
        $maxParameterLength = 0;

        // First pass: generate parameter strings and find max length for alignment
        foreach ($properties as $property) {
            $parameterString = $this->generateConstructorParameter($property);
            $parameters[] = $parameterString;
            $maxParameterLength = max($maxParameterLength, strlen($parameterString));
        }

        // Second pass: generate with proper indentation
        $constructorParams = [];
        foreach ($properties as $i => $property) {
            $attribute = $this->generateExtConfPropertyAttribute($property);
            $parameter = $parameters[$i];

            $constructorParams[] = "        {$attribute}\n        {$parameter},";
        }

        $parameterList = implode("\n", $constructorParams);

        return <<<CONSTRUCTOR
    public function __construct(
{$parameterList}
    ) {}
CONSTRUCTOR;
    }

    /**
     * @param array<string, mixed> $property
     */
    private function generateConstructorParameter(array $property): string
    {
        $type = is_string($property['type']) ? $property['type'] : 'mixed';
        $name = is_string($property['name']) ? $property['name'] : 'property';

        return "public {$type} \${$name}";
    }

    /**
     * @param array<string, mixed> $property
     */
    private function generateExtConfPropertyAttribute(array $property): string
    {
        $attributes = [];

        // Add path if different from property name
        if (array_key_exists('path', $property) && is_string($property['path']) && $property['path'] !== $property['name']) {
            $attributes[] = "path: '{$property['path']}'";
        }

        // Add default value
        if (array_key_exists('default', $property)) {
            $type = is_string($property['type']) ? $property['type'] : 'mixed';
            $defaultValue = $this->formatDefaultValue($property['default'], $type);
            $attributes[] = "default: {$defaultValue}";
        }

        // Add required flag if true
        if (array_key_exists('required', $property) && $property['required'] === true) {
            $attributes[] = 'required: true';
        }

        $attributeString = implode(', ', $attributes);

        return "#[ExtConfProperty({$attributeString})]";
    }

    private function formatDefaultValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return 'null';
        }

        return match ($type) {
            'string' => "'" . addslashes(is_string($value) ? $value : '') . "'",
            'bool' => (bool)$value ? 'true' : 'false',
            'int', 'float' => is_numeric($value) ? (string)$value : '0',
            'array' => $this->formatArrayValue($value),
            default => "'" . addslashes(is_string($value) ? $value : '') . "'",
        };
    }

    private function formatArrayValue(mixed $value): string
    {
        if (!is_array($value)) {
            return '[]';
        }

        if (count($value) === 0) {
            return '[]';
        }

        // Format as PHP array syntax
        $items = [];
        foreach ($value as $item) {
            $itemString = is_string($item) ? $item : '';
            $items[] = "'" . addslashes($itemString) . "'";
        }

        return '[' . implode(', ', $items) . ']';
    }
}
