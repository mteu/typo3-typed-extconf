# TYPO3 Typed Extension Configuration

A TYPO3 v13 extension that provides type-safe extension configuration
management, ensuring other extensions can rely on fully-typed configuration
values instead of the default string-only values from the TYPO3 backend.

## Features

- **Type Safety**: Automatic conversion of string values from backend
configuration to proper PHP types (int, bool, array, etc.)
- **Schema Definition**: Define configuration schemas using PHP attributes with
expected types and default values
- **Automatic Validation**: Built-in validation using the Valinor library for
type mapping and validation
- **Default Handling**: Provide sensible defaults for missing configuration keys
- **Path Mapping**: Support for nested configuration paths with dot notation
- **Developer Experience**: Simple API for accessing typed configuration values
- **Interface-based Design**: Mockable interface for testing

## Installation

Add the extension to your TYPO3 project:

```bash
composer require mteu/typo3-typed-extconf
```

## Usage

> **📖 For a comprehensive developer guide with advanced examples and best practices, see [Documentation/developer-guide.md](Documentation/developer-guide.md)**
>
> **⚡ Generate configuration classes automatically using the CLI command: `./vendor/bin/typo3 typed-extconf:generate` - see [Documentation/command-guide.md](Documentation/command-guide.md)**

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

Class-level attribute to specify which TYPO3 extension the configuration belongs to.

**Parameters:**
- `extensionKey` (string, optional): The TYPO3 extension key. If not provided, must be passed to the service method.

### `#[ExtConfProperty]`

Property/parameter-level attribute for configuration value mapping.

**Parameters:**
- `default` (mixed, optional): Default value if configuration key is missing
- `path` (string, optional): Custom configuration path using dot notation (e.g., 'api.endpoint')
- `required` (bool, optional): Whether the configuration value is required (default: false)

## Configuration Structure

The extension expects TYPO3 extension configuration in the standard format. For example, in your extension's `ext_conf_template.txt`:

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

The extension automatically registers its services. If you need to configure them manually:

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

## Error Handling

The extension provides specific exceptions:

- `mteu\TypedExtConf\Exception\ConfigurationException`: Configuration retrieval or schema issues
- `mteu\TypedExtConf\Exception\SchemaValidationException`: Type mapping validation failures

## Requirements

- TYPO3 v13.4+
- PHP 8.3+
- cuyz/valinor ^2.0

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
│       └── Mapper/
├── composer.json
├── ext_emconf.php
└── README.md
```

## Examples

### Real-world Example: Nested Configuration

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

This extension provides a robust foundation for type-safe extension configuration in TYPO3 v13, eliminating runtime errors caused by type mismatches and improving developer experience.
