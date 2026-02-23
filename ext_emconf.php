<?php

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

/** @noinspection PhpUndefinedVariableInspection */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Typed Extension Configuration',
    'description' => 'Aims to provide a type-safe extension configuration management for TYPO3, ensuring proper types instead of string-only values from backend configuration or mixed types from config/system/settings.php',
    'category' => 'services',
    'version' => '1.1.1',
    'state' => 'stable',
    'author' => 'Martin Adler',
    'author_email' => 'mteu@mailbox.org',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.18-14.2.99',
            'php' => '8.2.0-8.5.99',
        ],
    ],
];
