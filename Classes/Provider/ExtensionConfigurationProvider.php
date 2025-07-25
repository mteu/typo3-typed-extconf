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

use mteu\TypedExtConf\Exception\ConfigurationException;
use mteu\TypedExtConf\Exception\SchemaValidationException;

/**
 * ExtensionConfigurationProvider.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
interface ExtensionConfigurationProvider
{
    /**
     * @template T of object
     * @param class-string<T> $configClass
     * @return T
     * @throws ConfigurationException
     * @throws SchemaValidationException
     */
    public function get(string $configClass): object;
}
