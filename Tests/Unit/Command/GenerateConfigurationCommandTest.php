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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * GenerateConfigurationCommandTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(GenerateConfigurationCommand::class)]
final class GenerateConfigurationCommandTest extends TestCase
{
    #[Test]
    public function commandExecutesSuccessfully(): void
    {
        $package = $this->createMock(Package::class);
        $package->method('getPackageKey')->willReturn('test_extension');
        $package->method('getPackagePath')->willReturn('/path/to/test_extension/');

        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->method('getActivePackages')->willReturn([$package]);
        $packageManager->method('getPackage')->with('test_extension')->willReturn($package);

        $templateParser = new ExtConfTemplateParser();
        $classGenerator = new ConfigurationClassGenerator();

        $application = new Application();
        $command = new GenerateConfigurationCommand($packageManager, $templateParser, $classGenerator);
        $application->add($command);

        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['test_extension', 'yes']);
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
    }
}
