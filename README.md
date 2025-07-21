<div align="center">

[![CGL](https://github.com/mteu/typo3-typed-extconf/actions/workflows/cgl.yaml/badge.svg)](https://github.com/mteu/typo3-typed-extconf/actions/workflows/cgl.yaml)
[![Tests](https://github.com/mteu/typo3-typed-extconf/actions/workflows/tests.yaml/badge.svg?branch=main)](https://github.com/mteu/typo3-typed-extconf/actions/workflows/tests.yaml)
[![Coverage](https://coveralls.io/repos/github/mteu/typo3-typed-extconf/badge.svg?branch=main)](https://coveralls.io/github/mteu/typo3-typed-extconf?branch=main)
[![Maintainability](https://qlty.sh/gh/mteu/projects/typo3-typed-extconf/maintainability.svg)](https://qlty.sh/gh/mteu/projects/typo3-typed-extconf)

# TYPO3 Typed Extension Configuration

</div>

This TYPO3 CMS extension aims to provide type-safe extension configuration
management, ensuring other extensions can rely on fully-typed configuration
values instead of the default string-only values from the TYPO3 backend.

## 🚀 Features

- **Type Safety**: Automatic conversion of string values from backend
configuration to proper PHP types (int, bool, array, etc.)
- **Schema Definition**: Define configuration schemas using PHP attributes with
expected types and default values
- **Automatic Validation**: Built-in validation using the Valinor library for
type mapping and validation
- **Default Handling**: Provide sensible defaults for missing configuration keys
- **Path Mapping**: Support for nested configuration paths with dot notation
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
        #[ExtConfProperty(default: 10)]
        public int $maxItems,

        #[ExtConfProperty(default: true, required: false)]
        public bool $enableFeature,

        #[ExtConfProperty(path: 'api.endpoint', default: '/api/v1')]
        public string $apiEndpoint,

        #[ExtConfProperty(default: ['default', 'fallback'])]
        public array $allowedTypes,
    ) {}
}
```

### 2. Access Typed Configuration

Inject the configuration service and access your typed configuration:

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

        // All properties are guaranteed to have the correct types
        $maxItems = $config->maxItems; // int
        $isEnabled = $config->enableFeature; // bool
        $endpoint = $config->apiEndpoint; // string
        $types = $config->allowedTypes; // array
    }
}
```

## Attribute Reference

### `#[ExtensionConfig]`

Class-level attribute to specify which TYPO3 extension the configuration belongs
to.

**Parameters:**
- `extensionKey` (string, optional): The TYPO3 extension key. If not provided,
- must be passed to the service method.

### `#[ExtConfProperty]`

Property/parameter-level attribute for configuration value mapping.

**Parameters:**
- `default` (mixed, optional): Default value if configuration key is missing
- `path` (string, optional): Custom configuration path using dot notation
(e.g., 'api.endpoint')
- `required` (bool, optional): Whether the configuration value is required
(default: false)

## Configuration Structure

Extension configuration in TYPO3 is typically stored in
`config/system/settings.php` under the
`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']` array, or in
`config/system/additional.php` for custom configurations. The backend
configuration interface allows administrators to modify these values, but all
values are stored as strings regardless of their intended type.

The extension expects TYPO3 extension configuration in the standard format. For
example, in your extension's `ext_conf_template.txt`:

```
# cat=basic; type=int+; label=Maximum items
maxItems = 10

# cat=features; type=boolean; label=Enable feature
enableFeature = 1

# cat=api; type=string; label=API Endpoint
api.endpoint = /api/v1

# cat=advanced; type=string; label=Allowed types (comma-separated)
allowedTypes = default,fallback
```

## Service Registration

The extension automatically registers its services. If you need to configure
them manually:

```yaml
# Configuration/Services.yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  mteu\TypedExtConf\:
    resource: '../Classes/*'
```

## File Structure

```
packages/typo3-typed-extconf/
├── Classes/
│   ├── Attribute/
│   │   ├── ExtConfProperty.php
│   │   └── ExtensionConfig.php
│   ├── Exception/
│   │   ├── ConfigurationException.php
│   │   └── SchemaValidationException.php
│   └── Provider/
│       ├── ExtensionConfigurationProvider.php
│       └── TypedExtensionConfigurationProvider.php
├── Configuration/
│   └── Services.yaml
├── Tests/
│   └── Unit/
│       ├── Fixture/
│       └── Provider/
├── composer.json
├── ext_emconf.php
└── README.md
```

## 🧑‍💻 Examples

### Real-world example with nested configuration

```php
#[ExtensionConfig(extensionKey: 'my_complex_ext')]
final readonly class ComplexConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'api.endpoint', default: '/api')]
        public string $endpoint,

        // Nested configuration object
        public DatabaseConfiguration $database,

        #[ExtConfProperty(default: 'production')]
        public string $environment,
    ) {}
}

final readonly class DatabaseConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'db.host', default: 'localhost')]
        public string $host,

        #[ExtConfProperty(path: 'db.port', default: 3306)]
        public int $port,

        #[ExtConfProperty(path: 'db.ssl', default: true)]
        public bool $enableSsl,
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
