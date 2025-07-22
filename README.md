<div align="center">

[![CGL](https://github.com/mteu/typo3-typed-extconf/actions/workflows/cgl.yaml/badge.svg)](https://github.com/mteu/typo3-typed-extconf/actions/workflows/cgl.yaml)
[![Tests](https://github.com/mteu/typo3-typed-extconf/actions/workflows/tests.yaml/badge.svg?branch=main)](https://github.com/mteu/typo3-typed-extconf/actions/workflows/tests.yaml)
[![Coverage](https://coveralls.io/repos/github/mteu/typo3-typed-extconf/badge.svg?branch=main)](https://coveralls.io/github/mteu/typo3-typed-extconf?branch=main)
[![Maintainability](https://qlty.sh/gh/mteu/projects/typo3-typed-extconf/maintainability.svg)](https://qlty.sh/gh/mteu/projects/typo3-typed-extconf)

# TYPO3 Typed Extension Configuration

[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![PHP Version Require](https://poser.pugx.org/mteu/typo3-typed-extconf/require/php)](https://packagist.org/packages/mteu/typo3-typed-extconf)

</div>

This TYPO3 CMS extension aims to provide type-safe extension configuration
management, ensuring other extensions can rely on fully-typed configuration
values instead of the default string-only values from the TYPO3 backend.

> [!WARNING]
> This project has just been prototyped and is not yet recommended for
> productive use. Please wait for the official release.

## 🚧 Todos
- Evaluate a TYPO3 v12 backport
- Improve quality of testing
- Test with more real-world projects

## 🚀 Features

- **Type Safety**: Automatic conversion of string values from backend
configuration to proper PHP types (int, bool, array, etc.)
- **Schema Definition**: Define configuration schemas using PHP attributes with
expected types and default values
- **Automatic Validation**: Built-in validation using the Valinor library for
type mapping and validation
- **Default Handling**: Provide sensible defaults for missing configuration keys
- **Path Mapping**: Support for nested configuration paths with dot notation
- **Dependency Injection**: Configuration classes are automatically registered as DI services
- **Developer Experience**: Simple API for accessing typed configuration values

## ⚡️ Installation

Add this package to your TYPO3 Extension:

```bash
composer require mteu/typo3-typed-extconf
```

## 💡 Usage

> [!TIP]
> For a comprehensive developer guide with advanced examples and best practices,
> check out the [Developer Guide](Documentation/developer-guide.md).

> [!NOTE]
> If you're in a hurry you might want to have this package generate
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
        #[ExtConfProperty()]
        public int $maxItems = 10,

        #[ExtConfProperty(required: false)]
        public bool $enableFeature = true,

        #[ExtConfProperty(path: 'api.endpoint')]
        public string $apiEndpoint = '/api/v1',

        #[ExtConfProperty()]
        public array $allowedTypes = ['default', 'fallback'],
    ) {}
}
```

### 2. Access Typed Configuration

#### Option A: Direct Injection (Recommended)

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

#### Option B: Using the Provider

Alternatively, use the configuration provider service:

```php
<?php

use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;

final readonly class MyService
{
    public function __construct(
        private ExtensionConfigurationProvider $extensionConfigurationProvider,
    ) {}

    public function doSomething(): void
    {
        $config = $this->extensionConfigurationProvider->get(MyExtensionConfig::class);

        // Use configuration...
    }
}
```

## 📙 Attribute Reference

### `#[ExtensionConfig]`

Class-level attribute to specify which TYPO3 extension the configuration belongs
to.

**Parameters:**
- `extensionKey` (string, optional): The TYPO3 extension key. If not provided,
must be passed to the service method.

### `#[ExtConfProperty]`

Property/parameter-level attribute for configuration value mapping.

**Parameters:**
- `path` (string, optional): Custom configuration path using dot notation
(e.g., 'api.endpoint')
- `required` (bool, optional): Whether the configuration value is required
(default: false)

## Configuration Structure

Extension configuration in TYPO3 is typically stored in
`config/system/settings.php` under the
`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']` array, or in
`config/system/additional.php`, or custom configurations.

TYPO3's backend configuration interface allows administrators to modify these values, but all
values set through the backend module will be stored as strings regardless of their intended type.

This package retrieves the configuration with `TYPO3\CMS\Core\Configuration\ExtensionConfiguration`
regardless on how it got there.


## 🧑‍💻 Real-world example with nested configuration

```php
#[ExtensionConfig(extensionKey: 'my_complex_ext')]
final readonly class ComplexConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'api.endpoint')]
        public string $endpoint = '/api',

        // Nested configuration object
        public DatabaseConfiguration $database,

        #[ExtConfProperty()]
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

## 🤝 Contributing
Contributions are very welcome! Please have a look at the [Contribution Guide](CONTRIBUTING.md). It lays out the
workflow of submitting new features or bugfixes.

## 🔒 Security
Please refer to our [security policy](SECURITY.md) if you discover a security vulnerability in
this extension. Be warned, though. I cannot afford bounty. This is private project.

## ⭐ License
This extension is licensed under the [GPL-2.0-or-later](LICENSE.md) license.

## 💬 Support
For issues and feature requests, please use the [GitHub issue tracker](https://github.com/mteu/typo3-typed-extconf/issues).
