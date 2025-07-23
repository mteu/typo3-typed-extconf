# Configuration Generation Command Guide

The `typed-extconf:generate` command provides an interactive way to generate typed
configuration classes for TYPO3 extensions. This command helps developers quickly
create configuration classes that work with the typed extension configuration
system.

> [!WARNING]
> Use with caution, though, since this functionality is not well tested, yet.

## Overview

The command supports two generation modes:

1. **Template Mode**: Automatically generates configuration classes from existing
   `ext_conf_template.txt` files
2. **Manual Mode**: Interactive configuration creation with custom properties

## Usage

```bash
# Basic usage - interactive mode
./vendor/bin/typo3 typed-extconf:generate

# Generate from template for specific extension
./vendor/bin/typo3 typed-extconf:generate --extension=my_extension --mode=template

# Manual configuration for specific extension
./vendor/bin/typo3 typed-extconf:generate --extension=my_extension --mode=manual

# Specify custom class name and output path
./vendor/bin/typo3 typed-extconf:generate \
  --extension=my_extension \
  --class-name=MyCustomConfig \
  --output-path=/path/to/Classes/Configuration/MyCustomConfig.php

# Force overwrite existing files .. use with caution!
./vendor/bin/typo3 typed-extconf:generate --force
```

## Command Options

### `--extension` (`-e`)
**Type**: `string` (optional)
**Description**: Extension key to generate configuration for

If not provided, the command will present a list of available extensions to
choose from.

### `--mode` (`-m`)
**Type**: `string` (optional, default: `template`)
**Values**: `template` | `manual`
**Description**: Generation mode

- `template`: Parse existing `ext_conf_template.txt` file
- `manual`: Interactive property definition

### `--class-name` (`-c`)
**Type**: `string` (optional)
**Description**: Class name for the configuration (without namespace)

If not provided, generates a default name based on the extension key (e.g.,
`my_extension` becomes `MyExtensionConfiguration`).

### `--output-path` (`-o`)
**Type**: `string` (optional)
**Description**: Output path for the generated class file

If not provided, defaults to `Classes/Configuration/{ClassName}.php` within the
extension directory.

### `--force` (`-f`)
**Type**: `boolean` (optional)
**Description**: Overwrite existing files without confirmation

## Generation Modes

### Template Mode

Template mode parses existing `ext_conf_template.txt` files and automatically
generates typed configuration classes.

#### Supported TYPO3 Types

The parser recognizes these TYPO3 configuration types and maps them to PHP types:

| TYPO3 Type | PHP Type | Notes |
|------------|----------|--------|
| `boolean`, `bool` | `bool` | Supports various string representations |
| `int`, `integer` | `int` | Numeric values |
| `float`, `double` | `float` | Decimal values |
| `string`, `text` | `string` | Text values |
| `select`, `options` | `string` | Could be enhanced to enums in future |
| `user` | `string` | User functions typically return strings |

#### Example Template

Given this `ext_conf_template.txt`:

```
# cat=basic; type=int+; label=Maximum items to process
maxItems = 10

# cat=features; type=boolean; label=Enable special feature
enableFeature = 1

# cat=api; type=string; label=API Endpoint URL
api.endpoint = /api/v1

# cat=cache; type=int; label=Cache lifetime in seconds
cache.ttl = 3600
```

The command generates:

```php
<?php

declare(strict_types=1);

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * Typed configuration class for extension 'my_extension'.
 *
 * This class provides type-safe access to extension configuration values.
 * Generated using mteu/typo3-typed-extconf.
 */
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyExtensionConfiguration
{
    public function __construct(
        #[ExtConfProperty(default: 10)]
        public int $maxItems,
        #[ExtConfProperty(default: true)]
        public bool $enableFeature,
        #[ExtConfProperty(path: 'api.endpoint', default: '/api/v1')]
        public string $apiEndpoint,
        #[ExtConfProperty(path: 'cache.ttl', default: 3600)]
        public int $cacheTtl,
    ) {}
}
```

### Manual Mode

Manual mode provides an interactive interface for defining configuration
properties from scratch.

#### Interactive Workflow

1. **Extension Selection**: Choose from available extensions
2. **Class Name**: Specify or use generated class name
3. **Property Definition**: Define properties interactively:
   - Property name (camelCase)
   - Property type (`string`, `int`, `float`, `bool`, `array`)
   - Default value (optional)
   - Configuration path (dot notation)
   - Required flag

#### Example Session

```bash
$ ./vendor/bin/typo3 typed-extconf:generate --mode=manual
Select extension to generate configuration for:
> my_extension

Class name (without namespace) [MyExtensionConfiguration]:
> MyCustomConfiguration

Enter configuration properties (press Enter with empty name to finish):
Property name: apiUrl
Property type:
> string
Default value (optional): https://api.example.com
Configuration path (default: apiUrl): api.url
Is required? [no]: no

Property name: timeout
Property type:
> int
Default value (optional): 30
Configuration path (default: timeout): timeout
Is required? [no]: no

Property name:
[Enter to finish]

Generated configuration class successfully at: Classes/Configuration/MyCustomConfiguration.php
```

## Generated Code Structure

### Namespace Generation

The command automatically generates appropriate namespaces based on the extension
key:

- `my_extension` → `MyExtension\Configuration`
- `vendor_extension` → `Vendor\Extension\Configuration`
- Single word extensions get a generic vendor namespace

### Attribute Usage

Generated classes use the following attributes:

- `#[ExtensionConfig(extensionKey: 'extension_key')]`: Class-level extension
  identification
- `#[ExtConfProperty(...)]`: Property-level configuration mapping with:
  - `path`: Configuration path (dot notation for nested keys)
  - `default`: Default value if configuration is missing
  - `required`: Whether the configuration value is mandatory

### Type Conversion

The generator handles automatic type conversion for common TYPO3 configuration
patterns:

- Boolean values: `'1'`, `'true'`, `'yes'`, `'on'` → `true`
- Numeric strings: `'123'` → `123` (int), `'3.14'` → `3.14` (float)
- Array values: Comma-separated strings → arrays

## File Output

### Default Paths

Generated files are placed in the extension's `Classes/Configuration/` directory:

```
EXT:my_extension/
├── Classes/
│   └── Configuration/
│       └── MyExtensionConfiguration.php
├── ext_conf_template.txt
└── ...
```

### Directory Creation

The command automatically creates missing directories in the output path.

### Overwrite Protection

By default, the command asks for confirmation before overwriting existing files.
Use `--force` to skip confirmation.

## Integration with Extension Development

### Autoloading

Ensure your extension's `composer.json` includes proper autoloading:

```json
{
    "autoload": {
        "psr-4": {
            "Vendor\\Extension\\": "Classes/"
        }
    }
}
```

### Using Generated Classes

After generation, use the configuration classes in your services:

```php
use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;
use Vendor\Extension\Configuration\MyExtensionConfiguration;

final readonly class MyService
{
    public function __construct(
        private ExtensionConfigurationProvider $configurationProvider,
    ) {}

    public function getConfiguration(): MyExtensionConfiguration
    {
        return $this->configurationProvider->get(MyExtensionConfiguration::class);
    }
}
```

## Troubleshooting

### Extension Not Found

**Problem**: Extension doesn't appear in the selection list

**Solutions**:
- Ensure the extension is installed and active
- Check that the extension key is correctly registered
- Verify TYPO3's package manager recognizes the extension

### Template Parsing Errors

**Problem**: `ext_conf_template.txt` parsing fails

**Solutions**:
- Verify the template file syntax follows TYPO3 conventions
- Check for proper comment format: `# cat=category; type=type; label=Label`
- Ensure configuration keys don't contain invalid characters

### Generated Class Errors

**Problem**: Generated class has syntax or type errors

**Solutions**:
- Review the generated class for any unusual property names or types
- Check that default values are appropriate for their types
- Manually adjust the class if needed after generation

### Permission Issues

**Problem**: Cannot write to output directory

**Solutions**:
- Check file system permissions for the target directory
- Ensure the web server user can write to the extension directory
- Use a custom output path with appropriate permissions

## Best Practices

### Configuration Design

1. **Use meaningful names**: Choose descriptive property names that clearly
   indicate their purpose
2. **Provide sensible defaults**: Always include appropriate default values
3. **Group related settings**: Use dot notation for logical grouping
   (`api.endpoint`, `api.timeout`)
4. **Document complex settings**: Add comments to explain non-obvious
   configuration options

### Class Organization

1. **Single responsibility**: Keep configuration classes focused on one extension
2. **Readonly properties**: Generated classes use readonly properties for
   immutability
3. **Type safety**: Leverage PHP's type system for better IDE support and
   runtime safety

### Extension Integration

1. **Regular regeneration**: Regenerate classes when `ext_conf_template.txt`
   changes
2. **Version control**: Include generated classes in version control
3. **Testing**: Test configuration classes with various input scenarios

## Advanced Usage

### Custom Namespaces

For extensions requiring custom namespaces, manually adjust the generated class:

```php
// Generated
namespace MyExtension\Configuration;

// Custom
namespace Vendor\MyCustomExtension\Domain\Model\Configuration;
```

### Complex Default Values

For complex default values, consider using factory methods:

```php
#[ExtConfProperty(default: [])]
public array $advancedSettings,

public function getAdvancedSettingsWithDefaults(): array
{
    return array_merge([
        'timeout' => 30,
        'retries' => 3,
    ], $this->advancedSettings);
}
```

### Validation

Add validation methods to generated classes:

```php
public function isValid(): bool
{
    return $this->timeout > 0 && $this->retries >= 0;
}
```

## Migration from Legacy Configuration

### From Manual Array Access

**Before**:
```php
$config = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['my_extension'];
$timeout = (int)($config['timeout'] ?? 30);
```

**After**:
```php
$config = $this->configurationProvider->get(MyExtensionConfiguration::class);
$timeout = $config->timeout; // Already typed and with defaults
```

### From ExtensionConfiguration Service

**Before**:
```php
$config = GeneralUtility::makeInstance(ExtensionConfiguration::class)
    ->get('my_extension');
$enabled = (bool)($config['enabled'] ?? false);
```

**After**:
```php
$config = $this->configurationProvider->get(MyExtensionConfiguration::class);
$enabled = $config->enabled; // Type-safe boolean
```

## See Also

- [Developer Guide](developer-guide.md): Complete integration guide
- [README](../README.md): Extension overview and basic usage
- [TYPO3 Documentation](https://docs.typo3.org/): Official TYPO3 documentation
