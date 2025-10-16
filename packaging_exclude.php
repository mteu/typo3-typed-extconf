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

return [
    'directories' => [
        '.build',
        '.ddev',
        '.git',
        '.github',
        '.idea',
        'Tests',
        'tailor-version-artefact',
        'var',
    ],
    'files' => [
        '.editorconfig',
        '.php-cs-fixer.cache',
        '.phpunit.result.cache',
        'DS_Store',
        'CODE_OF_CONDUCT.md',
        'CODEOWNERS',
        'composer.lock',
        'CONTRIBUTING.md',
        // 'crowdin.yaml',
        'editorconfig',
        'gitattributes',
        'gitignore',
        'php-cs-fixer.php',
        'phpstan.neon',
        'phpstan.inc.neon',
        'phpstan-baseline.neon',
        'packaging_exclude.php',
        'phpunit.functional.xml',
        'phpunit.unit.xml',
        'rector.php',
        'renovate.json',
        'SECURITY.md',
        'typo3-vendor-bundler.yaml',
        'version-bumper.yaml',
    ],
];
