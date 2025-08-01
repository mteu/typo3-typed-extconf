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
    public function commandGeneratesClassFromTemplate(): void
    {
        $outputFile = $this->tempOutputDir . '/TestConfiguration.php';

        $commandTester = $this->executeCommand([
            '--extension' => 'test_extension',
            '--mode' => 'template',
            '--class-name' => 'TestConfiguration',
            '--output-path' => $outputFile,
            '--force' => true,
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertFileExists($outputFile);

        $content = file_get_contents($outputFile);
        self::assertIsString($content);
        // Test the essential generated content from template
        self::assertStringContainsString('class TestConfiguration', $content);
        self::assertStringContainsString('$apiKey', $content);
        self::assertStringContainsString('$timeout', $content);
        self::assertStringContainsString('$enabled', $content);
    }

    #[Test]
    public function commandHandlesMissingTemplate(): void
    {
        $command = $this->get(GenerateConfigurationCommand::class);
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
    }

    #[Test]
    public function commandGeneratesClassFromManualInput(): void
    {
        $outputFile = $this->tempOutputDir . '/ManualConfiguration.php';

        $command = $this->get(GenerateConfigurationCommand::class);
        $application = new Application();
        $application->add($command);
        $commandTester = new CommandTester($command);

        // Simulate manual property input - must be set before execute()
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

        $content = file_get_contents($outputFile);
        self::assertIsString($content);
        self::assertStringContainsString('class ManualConfiguration', $content);
        self::assertStringContainsString('$apiUrl', $content);
    }

    #[Test]
    public function commandCreatesNestedDirectories(): void
    {
        $outputFile = $this->tempOutputDir . '/deep/nested/path/DeepConfiguration.php';

        $commandTester = $this->executeCommand([
            '--extension' => 'test_extension',
            '--mode' => 'template',
            '--class-name' => 'DeepConfiguration',
            '--output-path' => $outputFile,
            '--force' => true,
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertFileExists($outputFile);
        self::assertDirectoryExists(dirname($outputFile));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeCommand(array $options): CommandTester
    {
        $command = $this->get(GenerateConfigurationCommand::class);
        $application = new Application();
        $application->add($command);
        $commandTester = new CommandTester($command);
        $commandTester->execute($options);
        return $commandTester;
    }

    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
