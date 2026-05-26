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
use mteu\TypedExtConf\Generator\ClassGenerator;
use mteu\TypedExtConf\Parser\TemplateParser;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * GenerateConfigurationCommandTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(GenerateConfigurationCommand::class)]
#[AllowMockObjectsWithoutExpectations]
final class GenerateConfigurationCommandTest extends UnitTestCase
{
    private MockObject&PackageManager $packageManagerMock;
    private MockObject&TemplateParser $templateParserMock;
    private MockObject&ClassGenerator $classGeneratorMock;

    /**
     * @var list<string>
     */
    private array $tempDirs = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->packageManagerMock = $this->createMock(PackageManager::class);
        $this->templateParserMock = $this->createMock(TemplateParser::class);
        $this->classGeneratorMock = $this->createMock(ClassGenerator::class);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->tempDirs = [];

        parent::tearDown();
    }

    private function createCommand(): GenerateConfigurationCommand
    {
        $command = new GenerateConfigurationCommand(
            $this->packageManagerMock,
            $this->templateParserMock,
            $this->classGeneratorMock,
        );

        $command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            new \Symfony\Component\Console\Helper\QuestionHelper(),
        ]));

        return $command;
    }

    #[Test]
    public function templateModeFailsWhenTemplateFileDoesNotExist(): void
    {
        $packageMock = $this->createMock(Package::class);
        $packageMock->method('getPackagePath')->willReturn('/tmp/nonexistent_path/');

        $this->packageManagerMock->method('getPackage')
            ->with('my_extension')
            ->willReturn($packageMock);

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['no']);

        $tester->execute([
            '--extension' => 'my_extension',
            '--mode' => 'template',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            'No ext_conf_template.txt found',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function templateModeReturnsSuccessWhenNoFieldsFound(): void
    {
        $templateDir = $this->createTemplateDir('');

        $packageMock = $this->createMock(Package::class);
        $packageMock->method('getPackagePath')->willReturn($templateDir . '/');

        $this->packageManagerMock->method('getPackage')
            ->with('my_extension')
            ->willReturn($packageMock);

        $this->templateParserMock->method('parse')->willReturn([]);

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            '--extension' => 'my_extension',
            '--mode' => 'template',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            'No configuration fields found',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function templateModeReturnsFailureWhenParserThrows(): void
    {
        $templateDir = $this->createTemplateDir('something');

        $packageMock = $this->createMock(Package::class);
        $packageMock->method('getPackagePath')->willReturn($templateDir . '/');

        $this->packageManagerMock->method('getPackage')
            ->with('my_extension')
            ->willReturn($packageMock);

        $this->templateParserMock->method('parse')
            ->willThrowException(new \RuntimeException('Parse error'));

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            '--extension' => 'my_extension',
            '--mode' => 'template',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed to parse', $tester->getDisplay());
    }

    #[Test]
    public function manualModeReturnsSuccessWhenNoPropertiesDefined(): void
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['MyConfiguration', '']);

        $tester->execute([
            '--extension' => 'my_extension',
            '--mode' => 'manual',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No properties defined', $tester->getDisplay());
    }

    #[Test]
    public function classNameDefaultsToCamelCasedExtensionKey(): void
    {
        $templateDir = $this->createTemplateDir('content');
        $outputFile = $templateDir . '/MyCoolExtensionConfiguration.php';

        $packageMock = $this->createMock(Package::class);
        $packageMock->method('getPackagePath')->willReturn($templateDir . '/');

        $this->packageManagerMock->method('getPackage')
            ->with('my_cool_extension')
            ->willReturn($packageMock);

        $this->templateParserMock->method('parse')->willReturn([
            [
                'name' => 'foo',
                'type' => 'string',
                'default' => '',
                'path' => 'foo',
                'required' => false,
                'label' => 'Foo',
                'category' => 'basic',
                'typo3_type' => 'string',
            ],
        ]);

        $capturedClassName = null;
        $this->classGeneratorMock->method('generate')
            ->willReturnCallback(
                function (string $extensionKey, string $className) use (&$capturedClassName): string {
                    $capturedClassName = $className;
                    return '<?php // generated';
                },
            );

        $command = $this->createCommand();
        $tester = new CommandTester($command);
        $tester->setInputs(['']);

        $tester->execute([
            '--extension' => 'my_cool_extension',
            '--mode' => 'template',
            '--output-path' => $outputFile,
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('MyCoolExtensionConfiguration', $capturedClassName);
    }

    #[Test]
    public function selectExtensionThrowsWhenNoExtensionsAvailable(): void
    {
        $this->packageManagerMock->method('getActivePackages')->willReturn([]);

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No extensions found.');

        $tester->execute([
            '--mode' => 'template',
        ], ['interactive' => true]);
    }

    #[Test]
    public function generatorFailureReturnsFailure(): void
    {
        $templateDir = $this->createTemplateDir('content');

        $packageMock = $this->createMock(Package::class);
        $packageMock->method('getPackagePath')->willReturn($templateDir . '/');

        $this->packageManagerMock->method('getPackage')
            ->with('my_extension')
            ->willReturn($packageMock);

        $this->templateParserMock->method('parse')->willReturn([
            [
                'name' => 'apiKey',
                'type' => 'string',
                'default' => '',
                'path' => 'apiKey',
                'required' => false,
                'label' => 'API Key',
                'category' => 'basic',
                'typo3_type' => 'string',
            ],
        ]);

        $this->classGeneratorMock->method('generate')
            ->willThrowException(new \RuntimeException('Generation failed'));

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            '--extension' => 'my_extension',
            '--mode' => 'template',
            '--class-name' => 'MyConfig',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed to generate class', $tester->getDisplay());
    }

    #[Test]
    public function ownExtensionIsExcludedFromActivePackagesPicker(): void
    {
        $ownPackage = $this->createMock(Package::class);
        $ownPackage->method('getPackageKey')->willReturn('typed_extconf');

        $alpha = $this->createMock(Package::class);
        $alpha->method('getPackageKey')->willReturn('alpha_ext');
        $alpha->method('getPackagePath')
            ->willReturn(sys_get_temp_dir() . '/missing_alpha_' . uniqid() . '/');

        $beta = $this->createMock(Package::class);
        $beta->method('getPackageKey')->willReturn('beta_ext');

        $this->packageManagerMock->method('getActivePackages')
            ->willReturn([$ownPackage, $alpha, $beta]);
        $this->packageManagerMock->method('getPackage')
            ->with('alpha_ext')
            ->willReturn($alpha);

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['alpha_ext', 'no']);

        $tester->execute(
            ['--mode' => 'template'],
            ['interactive' => true],
        );

        $display = $tester->getDisplay();
        self::assertStringContainsString('alpha_ext', $display);
        self::assertStringContainsString('beta_ext', $display);
        self::assertStringNotContainsString('typed_extconf', $display);
    }

    private function createTemplateDir(string $templateContent): string
    {
        $dir = sys_get_temp_dir() . '/typed_extconf_test_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/ext_conf_template.txt', $templateContent);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
}
