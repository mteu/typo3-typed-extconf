<div align="center">

[![CGL](https://github.com/mteu/typo3-typed-extconf/actions/workflows/cgl.yaml/badge.svg)](https://github.com/mteu/typo3-typed-extconf/actions/workflows/cgl.yaml)
[![Tests](https://github.com/mteu/typo3-typed-extconf/actions/workflows/tests.yaml/badge.svg?branch=main)](https://github.com/mteu/typo3-typed-extconf/actions/workflows/tests.yaml)
[![Coverage](https://coveralls.io/repos/github/mteu/typo3-typed-extconf/badge.svg?branch=main)](https://coveralls.io/github/mteu/typo3-typed-extconf?branch=main)
[![PHP Version Require](https://poser.pugx.org/mteu/typo3-typed-extconf/require/php)](https://packagist.org/packages/mteu/typo3-typed-extconf)

<img src="Resources/Public/Icons/Extension.svg" width="128" height="128" alt="Extension Icon">

# TYPO3 Typed Extension Configuration

![TYPO3 Support](https://badgen.net/badge/TYPO3/v13/FF8700?icon=typo3)
![TYPO3 Support](https://badgen.net/badge/TYPO3/v14/FF8700?icon=typo3)
![Latest version](https://typo3-badges.dev/badge/typed_extconf/version/shields.svg)
![Stability](https://typo3-badges.dev/badge/typed_extconf/stability/shields.svg)
![Total downloads](https://typo3-badges.dev/badge/typed_extconf/downloads/badgen.svg)
<!-- Generated with 🧡 at typo3-badges.dev -->

</div>

This TYPO3 CMS extension aims to provide a type-safe extension configuration
management for TYPO3, ensuring proper types instead of string-only values from
backend configuration or mixed types from `config/system/settings.php|additional.php`
(or custom solutions around those).

## 🦊 TYPO3 Support

|            | TYPO3 v12 | TYPO3 v13 | TYPO3 v14 |
|------------|-----------|-----------|-----------|
| up to v0.3 | ✅         | ✅         | ❌         |
| v1.x       | ❌         | ✅         | ✅         |


## 🚀 Features

- **Type Safety**: Automatic conversion from TYPO3's string configuration to proper PHP types
- **Schema Definition**: Define configuration using PHP attributes and constructor parameters
- **Path Mapping**: Support for nested configuration with dot notation (`api.endpoint`)
- **Configuration Generation**: Generate classes from `ext_conf_template.txt` or interactively
- **Dependency Injection**: Configuration classes auto-registered as services

## ⚡️ Installation

Add this package to your TYPO3 Extension:

```bash
composer require mteu/typo3-typed-extconf
```

This extension relies heavily on these key dependencies:
- [`cuyz/valinor`](https://github.com/CuyZ/Valinor) for type-safe object mapping and validation
- [`nette/phpgenerator`](https://github.com/nette/php-generator) for PHP code generation for configuration classes
- [`symfony/console`](https://github.com/symfony/console) for the automated (or interactive) code generation

## 💡 Usage

> [!TIP]
> For a comprehensive developer guide with advanced examples and best practices,
> check out the [Developer Guide](Documentation/developer-guide.md).

> [!NOTE]
> If you're in a hurry, you might want to have this package generate
> configuration classes automatically based on your extension's
> `ext_conf_template.txt`.
>
> Run `./vendor/bin/typo3 typed-extconf:generate` or consult the
> [Command Guide](Documentation/command-guide.md).
>
> Use with caution, though, since this functionality is not well tested, yet.

### 1. Define Configuration Schema

Create a configuration class for your extension using PHP attributes:

```php
<?php

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyExtensionConfig
{
    public function __construct(
        #[ExtConfProperty]
        public int $maxItems = 10,

        #[ExtConfProperty(required: false)]
        public bool $enableFeature = true,

        #[ExtConfProperty(path: 'api.endpoint')]
        public string $apiEndpoint = '/api/v1',

        #[ExtConfProperty]
        public array $allowedTypes = ['default', 'fallback'],
    ) {}
}
```

### 2. Access Typed Configuration

#### Direct Injection (Recommended)

Directly inject your configuration object using dependency injection:

```php
<?php

final readonly class MyService
{
    public function __construct(
        private MyExtensionConfig $config,
    ) {}

    public function doSomething(): void
    {
        // All properties are guaranteed to have the correct types
        $maxItems = $this->config->maxItems; // int
        $isEnabled = $this->config->enableFeature; // bool
        $endpoint = $this->config->apiEndpoint; // string
        $types = $this->config->allowedTypes; // array
    }
}
```

#### Using the `ExtensionConfigurationProvider`

Alternatively, use the configuration provider service:

```php
<?php

use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;

final readonly class MyService
{
    public function __construct(
        private ExtensionConfigurationProvider $configurationProvider,
    ) {}

    public function doSomething(): void
    {
        $config = $this->configurationProvider->get(MyExtensionConfig::class);

        // Use configuration...
    }
}
```

## 📙 Attribute Reference

### `#[ExtensionConfig]`

Class-level attribute to specify which TYPO3 extension the configuration belongs
to.

**Parameters:**
- `extensionKey` (string, required): The TYPO3 extension key.

### `#[ExtConfProperty]`

Property/parameter-level attribute for configuration value mapping.

**Parameters:**
- `path` (string, optional): Custom configuration path using dot notation (e.g., 'api.endpoint')
- `required` (bool, optional): Whether the configuration value is required (default: false)

> [!NOTE]
> Default values are defined as PHP constructor parameter defaults, not in the attribute.

## How It Works

TYPO3 stores extension configuration in `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']` as an associative array with
string values. Those strings could be manually altered by the developers to other types.
This extension automatically converts those types to proper PHP types using your configuration class schema.


## 🧑‍💻 Example

```php
#[ExtensionConfig(extensionKey: 'my_complex_ext')]
final readonly class ComplexConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'api.endpoint')]
        public string $endpoint = '/api',

        // Nested configuration object
        public DatabaseConfiguration $database,

        #[ExtConfProperty]
        public string $environment = 'production',
    ) {}
}

final readonly class DatabaseConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'db.host')]
        public string $host = 'localhost',

        #[ExtConfProperty(path: 'db.port')]
        public int $port = 3306,

        #[ExtConfProperty(path: 'db.ssl')]
        public bool $enableSsl = true,
    ) {}
}
```

## 🙏 Credits

This project is built on the excellent [CuyZ\Valinor](https://github.com/CuyZ/Valinor)
library, which provides the core type mapping and validation functionality.

Special thanks to:
- [CuyZ\Valinor](https://github.com/CuyZ/Valinor) for the powerful and flexible object mapping engine
- [Romain Canon](https://github.com/romm) personally and the Valinor contributors in general for their excellent work
- [Elias Häußler](https://github.com/eliashaeussler/) for his extensive help reviewing this extension and
contributing substantial improvements. Be sure to check out his Composer
packages — [`PHPUnit Attributes`](https://github.com/eliashaeussler/phpunit-attributes) and
[`Version Bumper`](https://github.com/eliashaeussler/version-bumper) — both of which greatly simplify maintaining this
project.

## 🤝 Contributing
Contributions are very welcome! Please have a look at the [Contribution Guide](CONTRIBUTING.md). It lays out the
workflow of submitting new features or bugfixes.

## 🔒 Security
Please refer to our [security policy](SECURITY.md) if you discover a security vulnerability in
this extension. Be warned, though. I cannot afford bounty. This is a private project.

## ⭐ License
This extension is licensed under the [GPL-2.0-or-later](LICENSE.txt) license.

## 💬 Support
For issues and feature requests, please use the [GitHub issue tracker](https://github.com/mteu/typo3-typed-extconf/issues).
