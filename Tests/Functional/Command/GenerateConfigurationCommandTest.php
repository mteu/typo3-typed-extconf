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

namespace mteu\TypedExtConf\Tests\Functional\Command;

use mteu\TypedExtConf\Command\GenerateConfigurationCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * GenerateConfigurationCommandTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class GenerateConfigurationCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/typed_extconf',
        'typo3conf/ext/typed_extconf/Tests/Functional/Fixtures/Extensions/test_extension',
        'typo3conf/ext/typed_extconf/Tests/Functional/Fixtures/Extensions/no_template_extension',
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'hash' => [
                        'backend' => \TYPO3\CMS\Core\Cache\Backend\NullBackend::class,
                    ],
                    'pages' => [
                        'backend' => \TYPO3\CMS\Core\Cache\Backend\NullBackend::class,
                    ],
                    'runtime' => [
                        'backend' => \TYPO3\CMS\Core\Cache\Backend\NullBackend::class,
                    ],
                ],
            ],
        ],
    ];

    private string $tempOutputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempOutputDir = Environment::getVarPath() . '/tests/functional_' . uniqid();
        mkdir($this->tempOutputDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempOutputDir)) {
            $this->removeDirectory($this->tempOutputDir);
        }

        parent::tearDown();
    }

    #[Test]
    public function commandGeneratesClassFromTemplateWithRealServices(): void
    {
        $command = $this->get(GenerateConfigurationCommand::class);

        $packageManager = $this->get(PackageManager::class);
        $testPackage = $packageManager->getPackage('test_extension');

        self::assertInstanceOf(PackageManager::class, $packageManager);
        self::assertSame('test_extension', $testPackage->getPackageKey());

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $outputFile = $this->tempOutputDir . '/TestExtensionConfiguration.php';

        $commandTester->execute([
            '--extension' => 'test_extension',
            '--mode' => 'template',
            '--class-name' => 'TestExtensionConfiguration',
            '--output-path' => $outputFile,
            '--force' => true,
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertFileExists($outputFile);

        $generatedContent = file_get_contents($outputFile);
        self::assertIsString($generatedContent);
        self::assertStringStartsWith('<?php', $generatedContent);

        self::assertMatchesRegularExpression('/final readonly class TestExtensionConfiguration/', $generatedContent);
        self::assertMatchesRegularExpression('/namespace [A-Za-z\\\\]+;/', $generatedContent);
        self::assertStringContainsString('$apiKey', $generatedContent);
        self::assertStringContainsString('$timeout', $generatedContent);
        self::assertStringContainsString('$enabled', $generatedContent);

        $display = $commandTester->getDisplay();

        self::assertStringContainsString('test_extension', $display);
        self::assertStringContainsString('Configuration class generated successfully', $display);
    }

    #[Test]
    public function commandHandlesMissingTemplateWithRealExtension(): void
    {
        $command = $this->get(GenerateConfigurationCommand::class);

        $packageManager = $this->get(PackageManager::class);
        $package = $packageManager->getPackage('no_template_extension');
        $templatePath = $package->getPackagePath() . 'ext_conf_template.txt';

        self::assertFalse(file_exists($templatePath), 'Template should not exist for this test');

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['no']); // Don't switch to manual mode

        $commandTester->execute([
            '--extension' => 'no_template_extension',
            '--mode' => 'template',
        ]);

        self::assertSame(1, $commandTester->getStatusCode());
        self::assertStringContainsString('No ext_conf_template.txt found', $commandTester->getDisplay());
        self::assertStringContainsString('no_template_extension', $commandTester->getDisplay());
    }

    #[Test]
    public function commandWorksWithInteractiveExtensionSelection(): void
    {
        $command = $this->get(GenerateConfigurationCommand::class);

        // Verify both test extensions are available to PackageManager
        $packageManager = $this->get(PackageManager::class);
        $activePackages = $packageManager->getActivePackages();

        $extensionKeys = array_keys($activePackages);
        self::assertContains('test_extension', $extensionKeys);
        self::assertContains('no_template_extension', $extensionKeys);

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $outputFile = $this->tempOutputDir . '/InteractiveConfiguration.php';

        // Simulate interactive selection: select extension, then provide other inputs
        $commandTester->setInputs([
            'test_extension',
            'InteractiveConfiguration',
            $outputFile,
        ]);

        $commandTester->execute([
            '--mode' => 'template',
            '--force' => true,
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertFileExists($outputFile);

        $generatedContent = file_get_contents($outputFile);
        self::assertIsString($generatedContent);
        self::assertStringStartsWith('<?php', $generatedContent);
        self::assertStringContainsString('final readonly class InteractiveConfiguration', $generatedContent);
    }

    #[Test]
    public function commandGeneratesClassFromManualInputWithRealServices(): void
    {
        $command = $this->get(GenerateConfigurationCommand::class);

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $outputFile = $this->tempOutputDir . '/ManualConfiguration.php';

        $commandTester->setInputs([
            'apiUrl',      // property name
            'string',      // type
            'https://api.example.com', // default value
            'api.url',     // path
            'yes',         // required
            '',            // empty name to finish
        ]);

        $commandTester->execute([
            '--extension' => 'test_extension',
            '--mode' => 'manual',
            '--class-name' => 'ManualConfiguration',
            '--output-path' => $outputFile,
            '--force' => true,
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertFileExists($outputFile);

        $generatedContent = file_get_contents($outputFile);
        self::assertIsString($generatedContent);
        self::assertStringStartsWith('<?php', $generatedContent);
        self::assertStringContainsString('final readonly class ManualConfiguration', $generatedContent);
        self::assertStringContainsString('$apiUrl', $generatedContent);
    }

    #[Test]
    public function commandCreatesOutputDirectoryWithRealFileSystem(): void
    {
        $command = $this->get(GenerateConfigurationCommand::class);

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $outputFile = $this->tempOutputDir . '/deep/nested/path/DeepConfiguration.php';

        $commandTester->execute([
            '--extension' => 'test_extension',
            '--mode' => 'template',
            '--class-name' => 'DeepConfiguration',
            '--output-path' => $outputFile,
            '--force' => true,
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertFileExists($outputFile);
        self::assertDirectoryExists(dirname($outputFile));

        $generatedContent = file_get_contents($outputFile);
        self::assertIsString($generatedContent);
        self::assertStringContainsString('final readonly class DeepConfiguration', $generatedContent);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
