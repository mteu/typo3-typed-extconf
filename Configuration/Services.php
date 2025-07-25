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

use mteu\TypedExtConf\Attribute\ExtensionConfig;
use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerBuilder $container): void {
    $container->registerAttributeForAutoconfiguration(
        ExtensionConfig::class,
        static function (
            ChildDefinition $definition,
            ExtensionConfig $attribute,
            \ReflectionClass $reflector,
        ): void {
            $definition
                ->setFactory([
                    new Reference(ExtensionConfigurationProvider::class), 'get',
                ])
                ->setArguments([
                    $reflector->name,
                ])
                ->setPublic(true);
        }
    );
};
