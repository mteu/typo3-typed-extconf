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

namespace mteu\TypedExtConf\Tests\Unit\Fixture\Configuration;

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * SimpleTestConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'test_ext')]
final readonly class SimpleTestConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'basic.string')]
        public string $stringValue = 'default',
        #[ExtConfProperty(path: 'basic.integer')]
        public int $intValue = 42,
        #[ExtConfProperty(path: 'basic.boolean')]
        public bool $boolValue = false,
        #[ExtConfProperty(path: 'basic.float')]
        public float $floatValue = 3.14,
    ) {}
}
