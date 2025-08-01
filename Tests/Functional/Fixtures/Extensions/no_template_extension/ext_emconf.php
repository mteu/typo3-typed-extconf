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

$EM_CONF[$_EXTKEY] = [
    'title' => 'Extension Without Template',
    'description' => 'Fixture extension without ext_conf_template.txt for testing',
    'category' => 'misc',
    'version' => '1.0.0',
    'state' => 'stable',
    'author' => 'Test Author',
    'author_email' => 'test@example.com',
    'constraints' => [
        'depends' => [
            'typo3' => '12.0.0-12.99.99',
        ],
    ],
];
