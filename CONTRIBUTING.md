# Contributing to TYPO3 Typed Extension Configuration

Thank you for your interest in contributing to this project! This guide will
help you get started with development and ensure your contributions align with
the project's standards.

## 🚀 Quick Start

### 1. Fork and Clone

```bash
# Fork the repository on GitHub, then clone your fork
git clone https://github.com/your-username/typo3-typed-extconf.git
cd typo3-typed-extconf
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b bugfix/your-bugfix-name
```

## 📝 Development Workflow

###  Code Standards

Before submitting your changes, ensure code quality passes:

| Command | Description |
|---------|-------------|
| `composer test` | Run complete test suite |
| `composer lint` | Check code quality (all linters) |
| `composer fix` | Apply automatic code fixes |
| `composer sca` | Run static code analysis |

These will be run against in the pipeline, too.
### PHP Requirements

- **PHP Version**: 8.2+ (project uses PHP 8.4 for development)
- **TYPO3 Version**: v12.4+ (project uses TYPO3 v13 for development)
- **Code Style**: TYPO3 Coding Standards via PHP-CS-Fixer

### Development Guidelines

1. **Type Safety**: Strive for maximum type safety to achieve high PHPStan levels
2. **Architecture**: Prefer composition over inheritance
3. **Classes**: Mark classes as `final` and `readonly` when appropriate
4. **Documentation**: Use concise, informal English for method and class docs
5. **Dependencies**: Always verify library availability before using
6. **Security**: Never expose or log secrets/keys

### Testing Guidelines

- Write unit tests for all new functionality
- Follow existing test patterns and naming conventions
- Ensure tests are isolated and don't depend on external resources

### Commit Messages

Please follow TYPO3's commit message guidelines since our semantic versioning
relies on proper commit messages:

```
[TYPE] Brief description (max 52 chars)

Optional longer description explaining the why and how.
Wrap at 72 characters per line.
```

**Types:**
- `[FEATURE]` - New functionality
- `[BUGFIX]` - Bug fixes
- `[TASK]` - Maintenance, refactoring, code cleanup
- `[DOCS]` - Documentation changes
- `[SECURITY]` - Security fixes (Approach the maintainers before committing
security related patches, please. Consult our [Security Guide](SECURITY.md) first!)
- `[BREAKING]` - Breaking changes (always include this when changing public API)

**Examples:**
```
[FEATURE] Add direct dependency injection for configuration classes
[BUGFIX] Fix parameter ordering in autoconfiguration callback
[TASK] Remove redundant attribute default parameter
[DOCS] Update developer guide with new DI examples
```

## 🛠️ Development Commands Reference

### Essential Commands


| Command | Description |
|---------|-------------|
| `composer test` | Run complete test suite |
| `composer lint` | Check code quality (all linters) |
| `composer fix` | Apply automatic code fixes |
| `composer sca` | Run static code analysis |


### Testing Commands

| Command                             | Description                        |
|-------------------------------------|------------------------------------|
| `composer test:functional`          | Run functional tests only          |
| `composer test:unit`                | Run unit tests only                |
| `composer test:coverage:functional` | Run functional tests with coverage |
| `composer test:coverage:unit`       | Run unit tests with coverage       |
| `composer test:coverage`            | Full coverage report               |

### Quality Assurance Commands

| Command | Description |
|---------|-------------|
| `composer lint:php` | PHP-CS-Fixer dry-run |
| `composer lint:composer` | Validate composer files |
| `composer lint:editorconfig` | Check EditorConfig compliance |
| `composer lint:yaml` | Validate YAML files |
| `composer fix:php` | Apply PHP-CS-Fixer rules |
| `composer fix:composer` | Normalize composer.json |
| `composer fix:editorconfig` | Apply EditorConfig fixes |

### Static Analysis Commands

| Command | Description |
|---------|-------------|
| `composer sca:php` | PHPStan analysis |
| `composer sca:migrate` | Run code migrations |
| `composer sca:migrate:php` | TYPO3 Rector migrations |

## 🚦 Pull Request Process

### Before Submitting

1. **Run Quality Checks**:
   ```bash
   composer lint && composer sca && composer test
   ```

2. **Update Documentation**: Update relevant docs if you've changed APIs

3. **Add Tests**: Ensure new features have appropriate test coverage

4. **Check Backwards Compatibility**: Avoid breaking changes when possible

### Pull Request Guidelines

1. **Title**: Use a descriptive title explaining what the PR does
2. **Description**: Clearly describe what changes are made and why
3. **Testing**: Explain how you tested the changes
4. **Breaking Changes**: Clearly document any breaking changes

### Review Process

- All PRs require review from a maintainer
- Address feedback promptly and respectfully
- Keep PR scope focused - prefer smaller, targeted PRs
- Ensure CI passes before requesting review

## 🐛 Reporting Issues

When reporting bugs or requesting features:

1. **Search existing issues** first
2. **Use issue templates** when available
3. **Provide reproduction steps** for bugs
4. **Include relevant code samples** and error messages
5. **Specify environment details** (PHP/TYPO3 versions)

## 💡 Feature Requests

For new features:

1. **Discuss the idea** first by opening an issue
2. **Explain the use case** and why it's valuable
3. **Consider backwards compatibility**
4. **Be open to alternative approaches**

## 📚 Additional Resources

- [TYPO3 Documentation](https://docs.typo3.org/)
- [PHP-FIG Standards](https://www.php-fig.org/psr/)
- [PHPStan Documentation](https://phpstan.org/)
- [Developer Guide](Documentation/developer-guide.md)

## 🤝 Code of Conduct

Please be respectful and constructive in all interactions. This project adheres
to our [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected
to uphold this code.

---

**Questions?** Feel free to open an issue or reach out to the maintainers. We're
here to help! 💛
