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

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->setHeader('This file is part of the TYPO3 CMS extension "typed_extconf".');
$config->setParallelConfig(\PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
$config->getFinder()->in(dirname(__DIR__, 2));

return $config;
