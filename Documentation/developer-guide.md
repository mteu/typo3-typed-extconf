# Developer Guide: TYPO3 Typed Extension Configuration

This guide provides comprehensive instructions for developers on how to
integrate and use the `mteu/typo3-typed-extconf` extension in their TYPO3
projects to achieve type-safe extension configuration.

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration Schema Definition](#configuration-schema-definition)
- [Service Integration](#service-integration)
- [Advanced Features](#advanced-features)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Best Practices](#best-practices)

## Installation

Add the extension to your TYPO3 project using Composer:

```bash
composer require mteu/typo3-typed-extconf
```

The extension automatically registers its services and is ready to use
immediately after installation.

## Quick Start

### Step 1: Create Your Configuration Class

Create a configuration class that defines the schema for your extension's
configuration:

```php
<?php

declare(strict_types=1);

namespace Vendor\MyExtension\Configuration;

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

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
    ) {}
}
```

### Step 2: Inject and Use the Configuration Mapper

Inject the configuration mapper service in your classes:

```php
<?php

declare(strict_types=1);

namespace Vendor\MyExtension\Service;

use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;
use Vendor\MyExtension\Configuration\MyExtensionConfiguration;

final readonly class MyService
{
    public function __construct(
        private ExtensionConfigurationProvider $extensionConfigurationProvider,
    ) {}

    public function doSomething(): void
    {
        $config = $this->extensionConfigurationProvider->get(MyExtensionConfiguration::class);

        // All properties are now properly typed
        $maxItems = $config->maxItems; // int
        $isEnabled = $config->enableFeature; // bool
        $endpoint = $config->apiEndpoint; // string

        // Use your configuration...
        if ($isEnabled && $maxItems > 0) {
            // Your business logic here
        }
    }
}
```

### Step 3: Configure Your Extension

Create or update your `ext_conf_template.txt`:

```
# cat=basic; type=int+; label=Maximum items to process
maxItems = 10

# cat=features; type=boolean; label=Enable special feature
enableFeature = 1

# cat=api; type=string; label=API Endpoint URL
api.endpoint = /api/v1
```

## Configuration Schema Definition

### Basic Types

The extension supports all standard PHP types with automatic conversion:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class TypeExampleConfiguration
{
    public function __construct(
        #[ExtConfProperty(default: 'default string')]
        public string $stringValue,

        #[ExtConfProperty(default: 42)]
        public int $intValue,

        #[ExtConfProperty(default: 3.14)]
        public float $floatValue,

        #[ExtConfProperty(default: true)]
        public bool $boolValue,
    ) {}
}
```

### Boolean Conversion

The extension provides flexible boolean conversion from TYPO3's string-based
configuration:

- `'1'`, `'true'`, `'yes'`, `'on'` → `true`
- `'0'`, `'false'`, `'no'`, `'off'` → `false`

### Path Mapping

Use dot notation to map nested configuration paths:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class PathMappingConfiguration
{
    public function __construct(
        // Maps to $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['my_extension']['database']['host']
        #[ExtConfProperty(path: 'database.host', default: 'localhost')]
        public string $dbHost,

        // Maps to $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['my_extension']['cache']['ttl']
        #[ExtConfProperty(path: 'cache.ttl', default: 3600)]
        public int $cacheTtl,
    ) {}
}
```

### Required Fields

Mark configuration values as required to enforce their presence:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class RequiredFieldsConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'api.key', required: true)]
        public string $apiKey,

        #[ExtConfProperty(default: 'fallback')]
        public string $optionalValue,
    ) {}
}
```

### Nested Configuration Objects

Create complex configuration hierarchies using nested objects:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class ComplexConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'app.name', default: 'MyApp')]
        public string $appName,

        // Nested configuration object
        public DatabaseConfiguration $database,
        public CacheConfiguration $cache,
    ) {}
}

final readonly class DatabaseConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'db.host', default: 'localhost')]
        public string $host,

        #[ExtConfProperty(path: 'db.port', default: 3306)]
        public int $port,

        #[ExtConfProperty(path: 'db.ssl', default: false)]
        public bool $enableSsl,
    ) {}
}

final readonly class CacheConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'cache.backend', default: 'file')]
        public string $backend,

        #[ExtConfProperty(path: 'cache.lifetime', default: 86400)]
        public int $lifetime,
    ) {}
}
```

## Service Integration

### Dependency Injection

The extension uses TYPO3's dependency injection system. Simply type-hint the
`ExtensionConfigurationProvider` interface:

```php
use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;

final readonly class MyController
{
    public function __construct(
        private ExtensionConfigurationProvider $extensionConfigurationProvider,
        // ... other dependencies
    ) {}
}
```

### Manual Service Retrieval

If you need to access the service manually:

```php
use TYPO3\CMS\Core\Utility\GeneralUtility;
use mteu\TypedExtConf\Provider\TypedExtensionConfigurationProvider;

$provider = GeneralUtility::makeInstance(TypedExtensionConfigurationProvider::class);
$config = $provider->get(MyConfiguration::class);
```

### Override Extension Key

You can override the extension key at runtime:

```php
// Use a different extension key than what's defined in the #[ExtensionConfig] attribute
$config = $this->extensionConfigurationProvider->get(MyConfiguration::class, 'other_extension');
```

## Advanced Features

### Default Value Strategies

Handle missing configuration gracefully with defaults:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class DefaultsConfiguration
{
    public function __construct(
        // Simple default
        #[ExtConfProperty(default: 'production')]
        public string $environment,

        // Computed default (use with caution)
        #[ExtConfProperty(default: 100)]
        public int $maxMemoryMb,

        // Required field without default
        #[ExtConfProperty(required: true)]
        public string $licenseKey,
    ) {}
}
```

### Complex Path Resolution

Handle deeply nested configuration structures:

```php
#[ExtensionConfig(extensionKey: 'example')]
final readonly class ExampleConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'providers.database.enabled', default: true)]
        public bool $databaseExampleEnabled,

        #[ExtConfProperty(path: 'providers.cache.threshold', default: 80)]
        public int $cacheThreshold,

        #[ExtConfProperty(path: 'notifications.email.recipients', default: '')]
        public string $emailRecipients,
    ) {}
}
```

## Testing

### Unit Testing Configuration Classes

Test your configuration classes using PHPUnit:

```php
<?php

declare(strict_types=1);

namespace Vendor\MyExtension\Tests\Unit\Configuration;

use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Vendor\MyExtension\Configuration\MyExtensionConfiguration;

final class MyExtensionConfigurationTest extends TestCase
{
    public function testConfigurationMapping(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('my_extension')
            ->willReturn([
                'maxItems' => '50',
                'enableFeature' => '1',
                'api' => ['endpoint' => '/api/v2'],
            ]);

        $provider = new TypedExtensionConfigurationProvider($extensionConfiguration);
        $config = $provider->get(MyExtensionConfiguration::class);

        self::assertSame(50, $config->maxItems);
        self::assertTrue($config->enableFeature);
        self::assertSame('/api/v2', $config->apiEndpoint);
    }
}
```

### Integration Testing

Test with real TYPO3 configuration:

```php
<?php

declare(strict_types=1);

namespace Vendor\MyExtension\Tests\Functional\Configuration;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;
use Vendor\MyExtension\Configuration\MyExtensionConfiguration;

final class ConfigurationIntegrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/my_extension',
    ];

    public function testRealConfiguration(): void
    {
        // Set up configuration in $GLOBALS['TYPO3_CONF_VARS']
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['my_extension'] = [
            'maxItems' => '25',
            'enableFeature' => '0',
            'api' => ['endpoint' => '/test/api'],
        ];

        $provider = $this->get(ExtensionConfigurationProvider::class);
        $config = $provider->get(MyExtensionConfiguration::class);

        self::assertSame(25, $config->maxItems);
        self::assertFalse($config->enableFeature);
        self::assertSame('/test/api', $config->apiEndpoint);
    }
}
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Configuration Class Not Found

**Error**: `Class "Vendor\MyExtension\Configuration\MyConfiguration" not found`

**Solution**: Ensure your configuration class is autoloaded. Check your
`composer.json` autoload section:

```json
{
    "autoload": {
        "psr-4": {
            "Vendor\\MyExtension\\": "Classes/"
        }
    }
}
```

#### 2. Missing ExtensionConfig Attribute

**Error**: `Configuration class must have an #[ExtensionConfig] attribute`

**Solution**: Add the `#[ExtensionConfig]` attribute to your configuration class:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyConfiguration { /* ... */ }
```

#### 3. Type Conversion Errors

**Error**: `Failed to map configuration for extension "my_ext": ...`

**Solution**: Ensure your TYPO3 configuration values can be converted to the expected types. Check your `ext_conf_template.txt` and backend configuration.

#### 4. Null Extension Key

**Error**: `Extension key must be specified`

**Solution**: Either set the extension key in the attribute or pass it as a parameter:

```php
// Option 1: Set in attribute
#[ExtensionConfig(extensionKey: 'my_extension')]

// Option 2: Pass as parameter
$config = $mapper->map(MyConfiguration::class, 'my_extension');
```

### Debugging Configuration

Enable debug mode to see what configuration data is being processed:

```php
// In your development environment, you can inspect raw configuration:
$rawConfig = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['my_extension'] ?? [];
var_dump($rawConfig);
```

## Best Practices

### 1. Use Readonly Classes

Always declare your configuration classes as `readonly` for immutability:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyConfiguration
{
    // Constructor parameters automatically become readonly properties
}
```

### 2. Provide Sensible Defaults

Always provide sensible defaults for optional configuration values:

```php
public function __construct(
    #[ExtConfProperty(default: 10)] // Good: sensible default
    public int $maxItems,

    #[ExtConfProperty()] // Avoid: no default, might cause issues
    public int $timeout,
) {}
```

### 3. Use Specific Types

Be specific about your types to catch configuration errors early:

```php
public function __construct(
    #[ExtConfProperty(default: 3600)] // Good: specific int type
    public int $cacheLifetime,

    #[ExtConfProperty(default: 'value')] // Avoid: mixed type
    public mixed $someValue,
) {}
```

### 4. Group Related Configuration

Use nested objects to group related configuration:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyConfiguration
{
    public function __construct(
        public DatabaseConfig $database,
        public CacheConfig $cache,
        public ApiConfig $api,
    ) {}
}
```

### 5. Document Your Configuration

Use PHPDoc to document complex configuration options:

```php
/**
 * @param int $maxRetries Maximum number of API retry attempts (0 = no retries)
 * @param int $timeoutMs Request timeout in milliseconds
 */
public function __construct(
    #[ExtConfProperty(default: 3)]
    public int $maxRetries,

    #[ExtConfProperty(default: 5000)]
    public int $timeoutMs,
) {}
```

### 6. Validate Configuration

Consider adding validation methods to your configuration classes:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyConfiguration
{
    public function __construct(
        #[ExtConfProperty(default: 80)]
        public int $cacheThreshold,
    ) {}

    public function isValid(): bool
    {
        return $this->cacheThreshold >= 0 && $this->cacheThreshold <= 100;
    }
}
```

### 7. Use Type-Safe Enums

For configuration with limited values, consider using enums:

```php
enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
}

#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyConfiguration
{
    public function __construct(
        #[ExtConfProperty(default: 'info')]
        public LogLevel $logLevel,
    ) {}
}
```

## Conclusion

The `mteu/typo3-typed-extconf` extension provides a powerful foundation for
type-safe extension configuration in TYPO3 v13. By following this guide and the
best practices outlined above, you can eliminate runtime errors caused by type
mismatches and significantly improve your extension's developer experience.

For additional support or to report issues, please visit the
[project repository](https://github.com/mteu/typodrei).
