<?php

/*
 * This file is part of the TYPO3 CMS extension "typed_extconf".
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/** @noinspection PhpUndefinedVariableInspection */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Typed Extension Configuration',
    'description' => 'Aims to provide a type-safe extension configuration management for TYPO3, ensuring proper types instead of string-only values from backend configuration or mixed types from config/system/settings.php',
    'category' => 'services',
    'version' => '0.1.1',
    'state' => 'alpha',
    'author' => 'Martin Adler',
    'author_email' => 'mteu@mailbox.org',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.31-13.4.99',
            'php' => '8.2.0-8.4.99',
        ],
    ],
];
