<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "mteu/typo3-typed-extconf".
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

namespace mteu\TypedExtConf\Command;

use mteu\TypedExtConf\Generator\ConfigurationClassGenerator;
use mteu\TypedExtConf\Parser\ExtConfTemplateParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * GenerateConfigurationCommand.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsCommand(
    name: 'typed-extconf:generate',
    description: 'Generate typed configuration classes for TYPO3 extensions'
)]
final class GenerateConfigurationCommand extends Command
{
    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly ExtConfTemplateParser $templateParser,
        private readonly ConfigurationClassGenerator $classGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'extension',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Extension key to generate configuration for'
            )
            ->addOption(
                'mode',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Generation mode: "template" (from ext_conf_template.txt) or "manual" (interactive)',
                'template'
            )
            ->addOption(
                'class-name',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Class name for the configuration (without namespace)'
            )
            ->addOption(
                'output-path',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output path for the generated class file'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing files without confirmation. Use with caution!'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $io->title('TYPO3 Typed Extension Configuration Generator');

        $mode = $input->getOption('mode');
        if (!in_array($mode, ['template', 'manual'], true)) {
            $question = new ChoiceQuestion(
                'Select generation mode:',
                ['template' => 'From ext_conf_template.txt', 'manual' => 'Manual configuration'],
                'template'
            );
            $mode = $helper->ask($input, $output, $question);
        }

        $extensionKey = $input->getOption('extension');

        if (!is_string($extensionKey)) {
            $extensionKey = $this->selectExtension($input, $output, $io, $helper);
        }

        if ($mode === 'template') {
            return $this->generateFromTemplate($input, $output, $io, $helper, $extensionKey);
        }

        return $this->generateManually($input, $output, $io, $helper, $extensionKey);
    }

    private function selectExtension(InputInterface $input, OutputInterface $output, SymfonyStyle $io, QuestionHelper $helper): string
    {
        $extensions = [];
        foreach ($this->packageManager->getActivePackages() as $package) {
            if ($package->getPackageKey() !== 'typo3-typed-extconf') {
                $extensions[] = $package->getPackageKey();
            }
        }

        if (count($extensions) === 0) {
            $io->error('No extensions found.');
            throw new \RuntimeException('No extensions found.');
        }

        sort($extensions);

        $question = new ChoiceQuestion(
            'Select extension to generate configuration for:',
            $extensions
        );

        $question->setAutocompleterValues($extensions);

        $result = $helper->ask($input, $output, $question);

        if (!is_string($result)) {
            throw new \RuntimeException('Invalid extension selection');
        }
        return $result;
    }

    private function generateFromTemplate(InputInterface $input, OutputInterface $output, SymfonyStyle $io, QuestionHelper $helper, string $extensionKey): int
    {
        $io->section("Generating configuration from ext_conf_template.txt for extension: {$extensionKey}");

        // Find ext_conf_template.txt
        $package = $this->packageManager->getPackage($extensionKey);
        $templatePath = $package->getPackagePath() . 'ext_conf_template.txt';

        if (!file_exists($templatePath)) {
            $io->error("No ext_conf_template.txt found in extension '{$extensionKey}'");

            $answer = $helper->ask(
                $input,
                $output,
                new ConfirmationQuestion('Would you like to generate configuration manually instead? ', false),
            );

            if (is_bool($answer) && $answer) {
                return $this->generateManually($input, $output, $io, $helper, $extensionKey);
            }

            return Command::FAILURE;
        }

        try {
            $templateData = $this->templateParser->parse($templatePath);

            if (count($templateData) === 0) {
                $io->warning('No configuration fields found in ext_conf_template.txt');
                return Command::SUCCESS;
            }

            $io->listing(array_map(
                fn(array $field): string => sprintf(
                    '%s (%s) - %s',
                    is_string($field['name']) ? $field['name'] : 'unknown',
                    is_string($field['type']) ? $field['type'] : 'mixed',
                    is_string($field['label']) ? $field['label'] : 'No label',
                ),
                $templateData
            ));

        } catch (\Exception $e) {
            $io->error('Failed to parse ext_conf_template.txt: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $classNameOption = $input->getOption('class-name');
        $className = is_string($classNameOption) ? $classNameOption : $this->askForClassName($input, $output, $helper, $extensionKey);

        return $this->generateAndSaveClass($input, $output, $io, $helper, $extensionKey, $className, $templateData);
    }

    private function generateManually(InputInterface $input, OutputInterface $output, SymfonyStyle $io, QuestionHelper $helper, string $extensionKey): int
    {
        $io->section("Manual configuration generation for extension: {$extensionKey}");

        $classNameOption = $input->getOption('class-name');
        $className = is_string($classNameOption) ? $classNameOption : $this->askForClassName($input, $output, $helper, $extensionKey);

        $properties = [];
        $io->writeln('Enter configuration properties (press Enter with empty name to finish):');

        while (true) {
            $propertyName = $helper->ask($input, $output, new Question('Property name: '));

            if ($propertyName === null || $propertyName === '') {
                break;
            }

            $type = $helper->ask(
                $input,
                $output,
                new ChoiceQuestion(
                    'Property type:',
                    ['string', 'int', 'float', 'bool', 'array'],
                    'string'
                ),
            );

            $defaultValue = $helper->ask($input, $output, new Question('Default value (optional): '));
            $propertyNameString = is_string($propertyName) ? $propertyName : 'property';
            $pathQuestion = new Question("Configuration path (default: {$propertyNameString}): ", $propertyNameString);
            $path = $helper->ask($input, $output, $pathQuestion);
            $path = is_string($path) ? $path : $propertyNameString;
            $required = $helper->ask($input, $output, new ConfirmationQuestion('Is required? ', false));

            $properties[] = [
                'name' => $propertyName,
                'type' => $type,
                'default' => $defaultValue,
                'path' => $path,
                'required' => $required,
                'label' => ucfirst(str_replace('_', ' ', is_string($propertyName) ? $propertyName : 'property')),
            ];

            $propertyNameString = is_string($propertyName) ? $propertyName : 'property';
            $typeString = is_string($type) ? $type : 'mixed';
            $io->writeln("Added property: {$propertyNameString} ({$typeString})");
        }

        if (count($properties) === 0) {
            $io->warning('No properties defined. Exiting.');
            return Command::SUCCESS;
        }

        return $this->generateAndSaveClass($input, $output, $io, $helper, $extensionKey, $className, $properties);
    }

    private function askForClassName(InputInterface $input, OutputInterface $output, QuestionHelper $helper, string $extensionKey): string
    {
        $defaultClassName = $this->generateDefaultClassName($extensionKey);

        $result = $helper->ask(
            $input,
            $output,
            new Question('Class name (without namespace): ', $defaultClassName),
        );
        return is_string($result) ? $result : $defaultClassName;
    }

    private function generateDefaultClassName(string $extensionKey): string
    {
        $parts = explode('_', $extensionKey);
        $className = '';

        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return $className . 'Configuration';
    }

    /**
     * @param array<int, array<string, mixed>> $configurationData
     */
    private function generateAndSaveClass(InputInterface $input, OutputInterface $output, SymfonyStyle $io, QuestionHelper $helper, string $extensionKey, string $className, array $configurationData): int
    {
        try {
            $classContent = $this->classGenerator->generate($extensionKey, $className, $configurationData);
        } catch (\Exception $e) {
            $io->error('Failed to generate class: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Determine output path
        $outputPath = $input->getOption('output-path');
        if ($outputPath === null) {
            $outputPath = $this->determineOutputPath($input, $output, $helper, $extensionKey, $className);
        }

        // Check if file exists
        $forceOption = $input->getOption('force');
        if (is_string($outputPath) && file_exists($outputPath) && (!is_bool($forceOption) || !$forceOption)) {
            $question = new ConfirmationQuestion(
                "File {$outputPath} already exists. Overwrite? ",
                false
            );

            $overwrite = $helper->ask($input, $output, $question);
            if (!is_bool($overwrite) || !$overwrite) {
                $io->writeln('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        if (!is_string($outputPath)) {
            $io->error('Invalid output path');
            return Command::FAILURE;
        }
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            // @todo: review the permissions, can we keep it as is or do we need to add more flexibility alas complexity
            if (!mkdir($directory, 0755, true)) {
                $io->error("Failed to create directory: {$directory}");
                return Command::FAILURE;
            }
        }

        if (file_put_contents($outputPath, $classContent) === false) {
            $io->error("Failed to write file: {$outputPath}");
            return Command::FAILURE;
        }

        $io->success("Configuration class generated successfully at: {$outputPath}");
        $io->writeln('');
        $io->writeln('Next steps:');
        $io->listing([
            'Review the generated class, its file system permissions, and adjust as needed',
            'Add the class to your extension\'s autoloader',
            'Use the ExtensionConfigurationProvider service to load your typed configuration',
        ]);

        return Command::SUCCESS;
    }

    private function determineOutputPath(InputInterface $input, OutputInterface $output, QuestionHelper $helper, string $extensionKey, string $className): string
    {
        $package = $this->packageManager->getPackage($extensionKey);
        $basePath = $package->getPackagePath();

        $defaultPath = $basePath . 'Classes/Configuration/' . $className . '.php';

        $result = $helper->ask(
            $input,
            $output,
            new Question('Output file path: ', $defaultPath),
        );

        return is_string($result) ? $result : $defaultPath;
    }
}
