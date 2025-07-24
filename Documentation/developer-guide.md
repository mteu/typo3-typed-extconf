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
        #[ExtConfProperty()]
        public int $maxItems = 10,

        #[ExtConfProperty()]
        public bool $enableFeature = true,

        #[ExtConfProperty(path: 'api.endpoint')]
        public string $apiEndpoint = '/api/v1',
    ) {}
}
```

### Step 2: Use Your Configuration

#### Recommended: Direct Injection

Simply inject your configuration class directly:

```php
<?php

declare(strict_types=1);

namespace Vendor\MyExtension\Service;

use Vendor\MyExtension\Configuration\MyExtensionConfiguration;

final readonly class MyService
{
    public function __construct(
        private MyExtensionConfiguration $config,
    ) {}

    public function doSomething(): void
    {
        // All properties are now properly typed
        $maxItems = $this->config->maxItems; // int
        $isEnabled = $this->config->enableFeature; // bool
        $endpoint = $this->config->apiEndpoint; // string

        // Use your configuration...
        if ($isEnabled && $maxItems > 0) {
            // Your business logic here
        }
    }
}
```

#### Alternative: Using the Provider

If you need more control, use the configuration provider:

```php
<?php

declare(strict_types=1);

namespace Vendor\MyExtension\Service;

use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;
use Vendor\MyExtension\Configuration\MyExtensionConfiguration;

final readonly class MyService
{
    public function __construct(
        private ExtensionConfigurationProvider $configurationProvider,
    ) {}

    public function doSomething(): void
    {
        $config = $this->configurationProvider->get(MyExtensionConfiguration::class);
        // Use configuration...
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
        #[ExtConfProperty()]
        public string $stringValue = 'default string',

        #[ExtConfProperty()]
        public int $intValue = 42,

        #[ExtConfProperty()]
        public float $floatValue = 3.14,

        #[ExtConfProperty()]
        public bool $boolValue = true,
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
        #[ExtConfProperty(path: 'database.host')]
        public string $dbHost = 'localhost',

        // Maps to $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['my_extension']['cache']['ttl']
        #[ExtConfProperty(path: 'cache.ttl')]
        public int $cacheTtl = 3600,
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

        #[ExtConfProperty()]
        public string $optionalValue = 'fallback',
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
        public DatabaseConfiguration $database,
        public CacheConfiguration $cache,
        #[ExtConfProperty(path: 'app.name')]
        public string $appName = 'MyApp',
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
        public bool $enableSsl = false,
    ) {}
}

final readonly class CacheConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'cache.backend')]
        public string $backend = 'file',

        #[ExtConfProperty(path: 'cache.lifetime')]
        public int $lifetime = 86400,
    ) {}
}
```

## Service Integration

### Automatic Service Registration

The extension automatically registers your configuration classes as DI services through the `#[ExtensionConfig]`
attribute. This is handled by the service configuration in `Configuration/Services.php`, which registers
attribute autoconfiguration for `#[ExtensionConfig]`.

The DI container setup includes:
- `TreeMapperFactory`: Main service implementing `MapperFactory`, creates preconfigured `TreeMapper` instances
- `TypedExtensionConfigurationProvider`: Main service implementing `ExtensionConfigurationProvider`

### Dependency Injection

You can inject your configuration classes directly:

```php
use Vendor\MyExtension\Configuration\MyExtensionConfiguration;

final readonly class MyController
{
    public function __construct(
        private MyExtensionConfiguration $config,
        // ... other dependencies
    ) {}
}
```

Alternatively, inject the provider service if you need more control:

```php
use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;

final readonly class MyController
{
    public function __construct(
        private ExtensionConfigurationProvider $configurationProvider,
        // ... other dependencies
    ) {}
}
```

### Manual Service Retrieval

If you need to access the service manually:

```php
use TYPO3\CMS\Core\Utility\GeneralUtility;
use mteu\TypedExtConf\Provider\ExtensionConfigurationProvider;

$provider = GeneralUtility::makeInstance(ExtensionConfigurationProvider::class);
$config = $provider->get(MyConfiguration::class);
```

### Configuration Access

The configuration is accessed through the provider service:

```php
// Access configuration using the extension key defined in the #[ExtensionConfig] attribute
$config = $this->configurationProvider->get(MyConfiguration::class);
```

### TreeMapper Configuration

The extension uses the [`CuyZ\Valinor`](https://github.com/CuyZ/Valinor) TreeMapper
with the following default configuration:

- `allowSuperfluousKeys()`: Permits extra keys in TYPO3 configuration that don't
  map to class properties
- TreeMapper instances are created through `TreeMapperFactory` for consistent
  configuration

This setup ensures robust handling of TYPO3's flexible configuration structure
while maintaining type safety.

### Custom TreeMapper Configuration

If you need custom TreeMapper behavior (e.g., custom value converters, different
validation rules), you can create your own factory that provides a customized
TreeMapper:

```php
<?php

declare(strict_types=1);

namespace Vendor\MyExtension\Mapper;

use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use mteu\TypedExtConf\Mapper\MapperFactory;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MapperFactory::class)]
final readonly class CustomMapperFactory implements MapperFactory
{
    public function create(): TreeMapper
    {
        return (new MapperBuilder())
            ->allowSuperfluousKeys()
            ->enableFlexibleCasting()  // Example: Enable flexible casting
            ->allowPermissiveTypes()   // Example: Allow permissive types
            ->mapper();
    }
}
```

> [!IMPORTANT]
> Alias your custom `MapperFactory` to allow DI to use your mapper instead of the shipped one, e.g. by using the
> [`#[AsAlias]`](https://symfony.com/doc/current/service_container/alias_private.html#aliasing) attribute.

## Advanced Features

### Default Value Strategies

Handle missing configuration gracefully with PHP parameter defaults:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class DefaultsConfiguration
{
    public function __construct(
        // PHP parameter default
        #[ExtConfProperty()]
        public string $environment = 'production',

        // PHP parameter default
        #[ExtConfProperty()]
        public int $maxMemoryMb = 100,

        // Required field without PHP default
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
        #[ExtConfProperty(path: 'providers.database.enabled')]
        public bool $databaseExampleEnabled = true,

        #[ExtConfProperty(path: 'providers.cache.threshold')]
        public int $cacheThreshold = 80,

        #[ExtConfProperty(path: 'notifications.email.recipients')]
        public string $emailRecipients = '',
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

        $provider = new TypedExtensionConfigurationProvider($extensionConfiguration, $mapper);
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

#### 4. Missing Extension Key

**Error**: `Configuration class must have an #[ExtensionConfig] attribute`

**Solution**: Add the required extension key in the attribute:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyConfiguration { /* ... */ }
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

Always provide sensible PHP parameter defaults for optional configuration values:

```php
public function __construct(
    #[ExtConfProperty()] // Good: sensible PHP default
    public int $maxItems = 10,

    #[ExtConfProperty()] // Avoid: no PHP default, might cause issues
    public int $timeout,

    #[ExtConfProperty(required: true)] // Note: Requiring without default value will throw an Exception
    public bool $requiredButNotSet,
) {}
```

### 3. Use Specific Types

Be specific about your types to catch configuration errors early:

```php
public function __construct(
    #[ExtConfProperty()] // Good: specific int type
    public int $cacheLifetime = 3600,

    #[ExtConfProperty()] // Avoid: mixed type
    public mixed $someValue = 'value',
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
    #[ExtConfProperty()]
    public int $maxRetries = 3,

    #[ExtConfProperty()]
    public int $timeoutMs = 5000,
) {}
```

### 6. Validate Configuration

Consider adding validation methods to your configuration classes:

```php
#[ExtensionConfig(extensionKey: 'my_extension')]
final readonly class MyConfiguration
{
    public function __construct(
        #[ExtConfProperty()]
        public int $cacheThreshold = 80,
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
        #[ExtConfProperty()]
        public LogLevel $logLevel = LogLevel::INFO,
    ) {}
}
```
