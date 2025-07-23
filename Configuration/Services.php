<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "typed-extconf".
 *
 * Copyright (C) 2025
 * Elias Häußler <elias@haeussler.dev>, Martin Adler <mteu@mailbox.org>
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

use CuyZ\Valinor\Mapper\TreeMapper;
use mteu\TypedExtConf\Attribute\ExtensionConfig;
use mteu\TypedExtConf\Mapper\MapperFactory;
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
                    $attribute->extensionKey,
                ])
                ->setPublic(true);
        }
    );

    $container->register(TreeMapper::class)
        ->setFactory([new Reference(MapperFactory::class), 'create']);
};
