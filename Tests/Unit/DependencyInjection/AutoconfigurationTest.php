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

namespace mteu\TypedExtConf\Tests\Unit\DependencyInjection;

use EliasHaeussler\PHPUnitAttributes\Attribute\RequiresPackage;
use mteu\TypedExtConf\Attribute\ExtensionConfig;
use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;
use mteu\TypedExtConf\Tests\Unit\Fixture\SimpleTestConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * AutoconfigurationTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class AutoconfigurationTest extends TestCase
{
    #[Test]
    #[RequiresPackage(
        package: 'symfony/dependency-injection',
        versionRequirement: '>= 7.3',
    )]
    public function testAttributeAutoconfigurationRegistersService(): void
    {
        $container = new ContainerBuilder();

        $configurator = require __DIR__ . '/../../../Configuration/Services.php';
        assert(is_callable($configurator));

        $configurator($container);

        $reflectionClass = new \ReflectionClass(SimpleTestConfiguration::class);
        $attribute = $reflectionClass->getAttributes(ExtensionConfig::class)[0]->newInstance();

        $definition = new ChildDefinition('abstract.service');

        $autoconfigurationCallbacks = $container->getAttributeAutoconfigurators();

        self::assertArrayHasKey(ExtensionConfig::class, $autoconfigurationCallbacks);

        $configurators = $autoconfigurationCallbacks[ExtensionConfig::class];
        self::assertIsArray($configurators);

        $callback = reset($configurators);
        self::assertIsCallable($callback);

        $callback($definition, $attribute, $reflectionClass);

        $factory = $definition->getFactory();
        self::assertIsArray($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame(ExtensionConfigurationProvider::class, (string)$factory[0]);
        self::assertSame('get', $factory[1]);

        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame(SimpleTestConfiguration::class, $arguments[0]);
    }

    #[Test]
    #[RequiresPackage(
        package: 'symfony/dependency-injection',
        versionRequirement: '< 7.3',
        message: 'getAutoconfiguredAttributes() is deprecated in >= 7.3',
    )]
    public function testAttributeAutoconfigurationRegistersServiceForSymfonyDependencyInjectionUpTo73(): void
    {
        $container = new ContainerBuilder();

        $configurator = require __DIR__ . '/../../../Configuration/Services.php';
        assert(is_callable($configurator));

        $configurator($container);

        $reflectionClass = new \ReflectionClass(SimpleTestConfiguration::class);
        $attribute = $reflectionClass->getAttributes(ExtensionConfig::class)[0]->newInstance();

        $definition = new ChildDefinition('abstract.service');

        /** @phpstan-ignore method.deprecated */
        $autoconfigurationCallbacks = $container->getAutoconfiguredAttributes();

        self::assertArrayHasKey(ExtensionConfig::class, $autoconfigurationCallbacks);

        // Execute the callback
        $callback = $autoconfigurationCallbacks[ExtensionConfig::class];
        $callback($definition, $attribute, $reflectionClass);

        $factory = $definition->getFactory();
        self::assertIsArray($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame(ExtensionConfigurationProvider::class, (string)$factory[0]);
        self::assertSame('get', $factory[1]);

        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame(SimpleTestConfiguration::class, $arguments[0]);
    }
}
