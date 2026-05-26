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

namespace mteu\TypedExtConf\Tests\Unit\Mapper;

use mteu\TypedExtConf\Mapper\TreeMapperFactory;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * TreeMapperFactoryTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class TreeMapperFactoryTest extends Framework\TestCase
{
    #[Test]
    public function createdMapperAllowsSuperfluousKeys(): void
    {
        $mapper = (new TreeMapperFactory())->create();

        $testClass = new class () {
            public function __construct(
                public readonly string $required = 'default'
            ) {}
        };

        $result = $mapper->map($testClass::class, [
            'required' => 'test_value',
            'extra_key' => 'should_be_ignored',
            'another_extra' => 123,
        ]);

        self::assertSame('test_value', $result->required);
    }
}
