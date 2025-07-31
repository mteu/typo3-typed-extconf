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

namespace mteu\TypedExtConf\Tests\Unit\Command;

use mteu\TypedExtConf\Command\GenerateConfigurationCommand;
use mteu\TypedExtConf\Generator\ConfigurationClassGenerator;
use mteu\TypedExtConf\Parser\ExtConfTemplateParser;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * GenerateConfigurationCommandTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(GenerateConfigurationCommand::class)]
final class GenerateConfigurationCommandTest extends Framework\TestCase
{
    #[Test]
    public function commandCanBeInstantiated(): void
    {
        $packageManager = $this->createMock(PackageManager::class);
        $templateParser = new ExtConfTemplateParser();
        $classGenerator = new ConfigurationClassGenerator();

        $command = new GenerateConfigurationCommand($packageManager, $templateParser, $classGenerator);

        self::assertSame('typed-extconf:generate', $command->getName());
        self::assertStringContainsString('Generate typed configuration classes', $command->getDescription());
    }
}
