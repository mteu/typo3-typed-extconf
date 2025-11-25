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

namespace mteu\TypedExtConf\Tests\Unit\DependencyInjection;

use mteu\TypedExtConf\Attribute\ExtensionConfig;
use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;
use mteu\TypedExtConf\Tests\Unit\Fixture\Configuration\SimpleTestConfiguration;
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
}
