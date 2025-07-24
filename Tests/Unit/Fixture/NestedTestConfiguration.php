<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "typed-extconf".
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace mteu\TypedExtConf\Tests\Unit\Fixture;

use mteu\TypedExtConf\Attribute\ExtConfProperty;

/**
 * NestedTestConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class NestedTestConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'nested.enabled')]
        public bool $enabled = false,
        #[ExtConfProperty(path: 'nested.priority')]
        public int $priority = 10,
        #[ExtConfProperty(path: 'nested.name')]
        public string $name = '',
    ) {}
}
