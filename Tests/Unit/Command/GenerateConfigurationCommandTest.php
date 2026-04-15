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
use PHPUnit\Framework\Attributes\DataProvider;
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
final class GenerateConfigurationCommandTest extends UnitTestCase
{
    private MockObject&PackageManager $packageManagerMock;
    private MockObject&TemplateParser $templateParserMock;
    private MockObject&ClassGenerator $classGeneratorMock;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->packageManagerMock = $this->createMock(PackageManager::class);
        $this->templateParserMock = $this->createMock(TemplateParser::class);
        $this->classGeneratorMock = $this->createMock(ClassGenerator::class);
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
    #[AllowMockObjectsWithoutExpectations]
    public function commandIsRegisteredWithCorrectNameAndDescription(): void
    {
        $command = $this->createCommand();

        self::assertSame('typed-extconf:generate', $command->getName());
        self::assertSame(
            'Generate typed configuration classes for TYPO3 extensions',
            $command->getDescription(),
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function commandDefinesAllExpectedOptions(): void
    {
        $command = $this->createCommand();
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasOption('extension'));
        self::assertTrue($definition->hasOption('mode'));
        self::assertTrue($definition->hasOption('class-name'));
        self::assertTrue($definition->hasOption('output-path'));
        self::assertTrue($definition->hasOption('force'));

        self::assertSame('e', $definition->getOption('extension')->getShortcut());
        self::assertSame('m', $definition->getOption('mode')->getShortcut());
        self::assertSame('c', $definition->getOption('class-name')->getShortcut());
        self::assertSame('o', $definition->getOption('output-path')->getShortcut());
        self::assertSame('f', $definition->getOption('force')->getShortcut());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function modeOptionDefaultsToTemplate(): void
    {
        $command = $this->createCommand();

        self::assertSame(
            'template',
            $command->getDefinition()->getOption('mode')->getDefault(),
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function forceOptionIsValueNone(): void
    {
        $command = $this->createCommand();

        self::assertFalse(
            $command->getDefinition()->getOption('force')->acceptValue(),
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function templateModeFailsWhenTemplateFileDoesNotExist(): void
    {
        $packageMock = $this->createMock(Package::class);
        $packageMock->method('getPackagePath')->willReturn('/tmp/nonexistent_path/');

        $this->packageManagerMock->method('getPackage')
            ->with('my_extension')
            ->willReturn($packageMock);

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['no']); // decline manual fallback

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
    #[AllowMockObjectsWithoutExpectations]
    public function templateModeReturnsSuccessWhenNoFieldsFound(): void
    {
        $templateDir = sys_get_temp_dir() . '/typed_extconf_test_' . uniqid();
        mkdir($templateDir, 0755, true);
        file_put_contents($templateDir . '/ext_conf_template.txt', '');

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

        unlink($templateDir . '/ext_conf_template.txt');
        rmdir($templateDir);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function templateModeReturnsFailureWhenParserThrows(): void
    {
        $templateDir = sys_get_temp_dir() . '/typed_extconf_test_' . uniqid();
        mkdir($templateDir, 0755, true);
        file_put_contents($templateDir . '/ext_conf_template.txt', 'something');

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

        unlink($templateDir . '/ext_conf_template.txt');
        rmdir($templateDir);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function manualModeReturnsSuccessWhenNoPropertiesDefined(): void
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // First input: class name (accept default), second: empty property name to stop
        $tester->setInputs(['MyConfiguration', '']);

        $tester->execute([
            '--extension' => 'my_extension',
            '--mode' => 'manual',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No properties defined', $tester->getDisplay());
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function defaultClassNameDataProvider(): \Generator
    {
        yield 'single word' => ['news', 'NewsConfiguration'];
        yield 'two words' => ['my_extension', 'MyExtensionConfiguration'];
        yield 'three words' => ['my_cool_extension', 'MyCoolExtensionConfiguration'];
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('defaultClassNameDataProvider')]
    public function generateDefaultClassNameProducesExpectedResult(
        string $extensionKey,
        string $expectedClassName,
    ): void {
        $command = $this->createCommand();

        $reflection = new \ReflectionMethod($command, 'generateDefaultClassName');

        self::assertSame($expectedClassName, $reflection->invoke($command, $extensionKey));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
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
    #[AllowMockObjectsWithoutExpectations]
    public function generatorFailureReturnsFailure(): void
    {
        $templateDir = sys_get_temp_dir() . '/typed_extconf_test_' . uniqid();
        mkdir($templateDir, 0755, true);
        file_put_contents($templateDir . '/ext_conf_template.txt', 'content');

        $packageMock = $this->createMock(Package::class);
        $packageMock->method('getPackagePath')->willReturn($templateDir . '/');

        $this->packageManagerMock->method('getPackage')
            ->with('my_extension')
            ->willReturn($packageMock);

        $this->templateParserMock->method('parse')->willReturn([
            ['name' => 'apiKey', 'type' => 'string', 'label' => 'API Key'],
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

        unlink($templateDir . '/ext_conf_template.txt');
        rmdir($templateDir);
    }
}
