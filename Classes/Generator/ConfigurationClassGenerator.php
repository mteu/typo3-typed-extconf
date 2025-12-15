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

namespace mteu\TypedExtConf\Generator;

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

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
     * @param list<array{name: string, type: string, default?: mixed, path?: string, required?: bool, label?: string}> $properties
     */
    public function generate(string $extensionKey, string $className, array $properties): string
    {
        if (count($properties) === 0) {
            throw new \InvalidArgumentException('At least one property must be defined');
        }

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($this->generateNamespaceName($extensionKey));
        $namespace->addUse(ExtConfProperty::class);
        $namespace->addUse(ExtensionConfig::class);

        $class = $namespace->addClass($className);
        $class->setFinal(true);
        $class->setReadOnly(true);
        $class->setComment($this->generateClassDocumentation($className, $extensionKey));
        $class->addAttribute(ExtensionConfig::class, ['extensionKey' => $extensionKey]);

        $constructor = $class->addMethod('__construct');
        $constructor->setPublic();

        foreach ($properties as $property) {
            $this->addConstructorParameter($constructor, $property);
        }

        $printer = new PsrPrinter();
        return $printer->printFile($file);
    }

    private function generateNamespaceName(string $extensionKey): string
    {
        // Convert extension key to namespace (e.g., my_extension -> MyExtension)
        $parts = explode('_', $extensionKey);
        $namespaceParts = array_map(ucfirst(...), $parts);
        $vendor = array_shift($namespaceParts);
        $extension = implode('', $namespaceParts);

        // If single part extension, use generic vendor
        if ($extension === '') {
            return "Vendor\\{$vendor}\\Configuration";
        }

        return "{$vendor}\\{$extension}\\Configuration";
    }

    private function generateClassDocumentation(string $className, string $extensionKey): string
    {
        return "{$className}.\n\nTyped configuration class for extension '{$extensionKey}'.\n\nThis class provides type-safe access to extension configuration properties.\nGenerated using mteu/typo3-typed-extconf.";
    }

    /**
     * @param array{name: string, type: string, default?: mixed, path?: string, required?: bool, label?: string} $property
     */
    private function addConstructorParameter(\Nette\PhpGenerator\Method $constructor, array $property): void
    {
        if (!is_string($property['name']) || !is_string($property['type'])) {
            return;
        }

        $parameter = $constructor->addPromotedParameter($property['name']);
        $parameter->setType($property['type']);
        $parameter->setPublic();

        // Set PHP default value if provided
        if (array_key_exists('default', $property)) {
            $parameter->setDefaultValue($this->formatDefaultValueForParameter($property['default'], $property['type']));
        }

        // Build ExtConfProperty attribute (only for mapping metadata)
        $attributeArgs = [];

        // Add path if different from property name
        if (array_key_exists('path', $property) && is_string($property['path']) && $property['path'] !== $property['name']) {
            $attributeArgs['path'] = $property['path'];
        }

        // Add required flag if true
        if (array_key_exists('required', $property) && $property['required'] === true) {
            $attributeArgs['required'] = true;
        }

        $parameter->addAttribute(ExtConfProperty::class, $attributeArgs);
    }

    private function formatDefaultValueForParameter(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'string' => is_string($value) ? $value : '',
            'bool' => (bool)$value,
            'int' => is_numeric($value) ? (int)$value : 0,
            'float' => is_numeric($value) ? (float)$value : 0.0,
            'array' => $this->formatArrayValueForParameter($value),
            default => is_string($value) ? $value : '',
        };
    }

    /**
     * @return array<mixed>
     */
    private function formatArrayValueForParameter(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        // Return the array as-is for PHP parameter defaults
        return $value;
    }
}
