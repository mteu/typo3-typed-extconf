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

namespace mteu\TypedExtConf\Provider;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\TreeMapper;
use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;
use mteu\TypedExtConf\Exception\ConfigurationException;
use mteu\TypedExtConf\Exception\SchemaValidationException;
use mteu\TypedExtConf\Mapper\MapperFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * TypedExtensionConfigurationProvider.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class TypedExtensionConfigurationProvider implements ExtensionConfigurationProvider
{
    private TreeMapper $mapper;

    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        MapperFactory $mapperFactory,
    ) {
        $this->mapper = $mapperFactory->create();
    }

    /**
     * @template T of object
     * @param class-string<T> $configClass
     * @return T
     * @throws ConfigurationException
     * @throws SchemaValidationException
     */
    public function get(string $configClass): object
    {
        $reflection = new \ReflectionClass($configClass);

        if (!$reflection->isInstantiable()) {
            throw new ConfigurationException(
                sprintf('Configuration class "%s" must be instantiable', $configClass)
            );
        }

        $extensionKey = $this->resolveExtensionKey($reflection);
        $rawConfig = $this->getRawConfiguration($extensionKey);
        $configData = $this->prepareConfigurationData($reflection, $rawConfig);

        try {
            return $this->mapper->map($configClass, $configData);
        } catch (MappingError $error) {
            $messageParts = [
                sprintf('Failed to map configuration for extension "%s":', $extensionKey),
            ];

            foreach ($error->messages() as $message) {
                $messageParts[] = sprintf(' - %s: %s', $message->path(), $message->toString());
            }

            throw new SchemaValidationException(
                implode(PHP_EOL, $messageParts),
                1753453309,
                $error,
            );
        }
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function resolveExtensionKey(\ReflectionClass $reflection): string
    {
        $extensionConfigAttributes = $reflection->getAttributes(ExtensionConfig::class);

        if ($extensionConfigAttributes === []) {
            throw new ConfigurationException(
                sprintf('Configuration class "%s" must have an #[ExtensionConfig] attribute', $reflection->getName())
            );
        }

        /** @var ExtensionConfig $extensionConfig */
        $extensionConfig = $extensionConfigAttributes[0]->newInstance();

        return $extensionConfig->extensionKey;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRawConfiguration(string $extensionKey): array
    {
        try {
            $config = $this->extensionConfiguration->get($extensionKey);
            /** @var array<string, mixed> $result */
            $result = is_array($config) ? $config : [];
            return $result;
        } catch (\Exception $exception) {
            throw new ConfigurationException(
                sprintf('Failed to retrieve configuration for extension "%s": %s', $extensionKey, $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param array<string, mixed> $rawConfig
     * @return array<string, mixed>
     */
    private function prepareConfigurationData(\ReflectionClass $reflection, array $rawConfig): array
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $rawConfig;
        }

        $configData = [];

        foreach ($constructor->getParameters() as $parameter) {
            $configData[$parameter->getName()] = $this->resolveParameterValue($parameter, $rawConfig);
        }

        return $configData;
    }

    /**
     * @param array<string, mixed> $rawConfig
     */
    private function resolveParameterValue(\ReflectionParameter $parameter, array $rawConfig): mixed
    {
        $attribute = $this->getExtConfPropertyAttribute($parameter);
        $configKey = $this->determineConfigKey($parameter, $attribute);
        /** @var array<string, mixed> $typedRawConfig */
        $typedRawConfig = $rawConfig;
        $rawValue = $this->getNestedValue($typedRawConfig, $configKey);

        if ($rawValue !== null) {
            return $this->convertValueToParameterType($rawValue, $parameter);
        }

        if ($attribute?->required === true) {
            throw new ConfigurationException(
                sprintf('Required configuration key "%s" is missing', $configKey)
            );
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($this->isNestedConfigurationObject($parameter)) {
            return $this->createNestedConfiguration($parameter, $rawConfig);
        }

        return null;
    }

    private function determineConfigKey(\ReflectionParameter $parameter, ?ExtConfProperty $attribute): string
    {
        if ($attribute === null) {
            return $parameter->getName();
        }

        return $attribute->path ?? $parameter->getName();
    }

    /**
     * @param array<string, mixed> $rawConfig
     */
    private function createNestedConfiguration(\ReflectionParameter $parameter, array $rawConfig): mixed
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        /** @var class-string $className */
        $className = $type->getName();

        /** @var array<string, mixed> $typedRawConfig */
        $typedRawConfig = $rawConfig;
        return $this->prepareConfigurationData(
            new \ReflectionClass($className),
            $typedRawConfig
        );
    }

    private function getExtConfPropertyAttribute(\ReflectionParameter $parameter): ?ExtConfProperty
    {
        $attributes = $parameter->getAttributes(ExtConfProperty::class);

        if ($attributes === []) {
            return null;
        }

        /** @var \ReflectionAttribute<ExtConfProperty> $attribute */
        $attribute = $attributes[0];

        return $attribute->newInstance();
    }

    private function isNestedConfigurationObject(\ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $className = $type->getName();

        if (!class_exists($className)) {
            return false;
        }

        $classReflection = new \ReflectionClass($className);
        $constructor = $classReflection->getConstructor();

        if ($constructor === null) {
            return false;
        }

        // Check if the class has any ExtConfProperty attributes on constructor parameters
        foreach ($constructor->getParameters() as $constructorParam) {
            if ($this->getExtConfPropertyAttribute($constructorParam) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function getNestedValue(array $config, string $path): mixed
    {
        if (str_contains($path, '.')) {
            $keys = explode('.', $path);
            $value = $config;

            foreach ($keys as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    return null;
                }
                $value = $value[$key];
            }

            return $value;
        }

        return array_key_exists($path, $config) ? $config[$path] : null;
    }

    private function convertValueToParameterType(mixed $value, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => is_string($value) || is_numeric($value) ? (int)$value : $value,
            'float' => is_string($value) || is_numeric($value) ? (float)$value : $value,
            'string' => is_scalar($value) ? (string)$value : $value,
            default => $value,
        };
    }

}
