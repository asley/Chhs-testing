# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a fork of Gibbon - a flexible, open source school management platform. The codebase is running Gibbon v30.0.00 with PHP 8.0+, using Slim Framework, Twig templating, and Tailwind CSS.

## Development Commands

### PHP Dependencies
```bash
composer install
```

### Frontend Assets
```bash
# Navigate to build directory first
cd resources/build
npm install

# Development build (unminified, with sourcemaps)
npm run dev

# Production build (minified, optimized)
npm run build

# Watch mode (rebuilds on file changes)
npm run watch
```

### Testing
```bash
# Run all tests
composer test

# Run specific test suites
composer test:phpunit           # Unit tests only
composer test:codeception       # Acceptance tests
composer test:phpstan          # Static analysis
composer test:codesniffer      # PSR-2 style check

# Run single unit test
cd tests
../vendor/bin/phpunit path/to/TestFile.php

# Run Codeception with debug
composer test:codeceptiondebug
```

## Architecture Overview

### Core Application Flow
- **Entry Point**: `index.php` → bootstraps via `gibbon.php`
- **Bootstrap**: Sets up DI container (League Container), autoloading, database connection, session
- **Routing**: Legacy file-based routing (modules/ModuleName/file.php) with modern Slim integration
- **Service Providers**: Located in `src/Services/` - handle core services, views, and authentication
- **Templates**: Twig templates in `resources/templates/` with legacy PHP views still present

### Directory Architecture
- `src/` - PSR-4 namespaced core code (`Gibbon\` namespace)
  - `Auth/` - Authentication and authorization
  - `Domain/` - Domain models and gateways (repositories)
  - `Services/` - Application services
  - `Forms/` - Form builder components
  - `Tables/` - Data table components
  - `UI/` - UI utilities and page rendering
  - `Session/` - Session management
  - `Database/` - Database layer and query builders
- `modules/` - Feature modules (self-contained functionality areas)
- `lib/` - Vendor-style third-party libraries
- `resources/` - Frontend assets and templates
  - `assets/` - Compiled CSS/JS output (Git-tracked)
  - `build/` - Source files for Webpack (css/, js/, tailwind.config.js - **NOTE: Currently empty, check if files exist before modifying**)
  - `templates/` - Twig template files
  - `imports/` - Data import templates
- `uploads/` - User-uploaded files (Git-ignored)
- `vendor/` - Composer dependencies (Git-ignored)
- `tests/` - PHPUnit unit tests and Codeception acceptance tests

### Key Patterns
- **Gateways**: Domain gateways in `src/Domain/` act as repositories for data access
- **Dependency Injection**: Use `$container->get(ClassName::class)` to retrieve services
- **Legacy Helpers**: `functions.php` contains global helper functions (avoid adding new globals)
- **Forms**: Use `src/Forms/` form builder rather than raw HTML
- **Tables**: Use `src/Tables/` for data tables with sorting/filtering

## Module Development

Modules are self-contained features in the `modules/` directory. Each module:
- Has a `manifest.php` describing actions, permissions, and dependencies
- Can include controllers, views, and module-specific logic
- Should follow PSR-2/PSR-12 coding standards
- May have `moduleFunctions.php` for module-specific helpers

## Code Style & Conventions

### PHP
- **Standard**: PSR-2/PSR-12 with 4-space indentation
- **Classes**: StudlyCase (e.g., `UserGateway`)
- **Methods/Properties**: camelCase (e.g., `getByID()`)
- **Constants**: UPPER_SNAKE_CASE
- **Type Hints**: Required for new code (PHP 8.0+ features allowed)
- **Namespaces**: Follow PSR-4 autoloading under `Gibbon\`

### Twig Templates
- Follow Twig default conventions
- Use translation keys consistent with `i18n/`
- Escape output to prevent XSS

### CSS/JavaScript
- **Tailwind CSS**: Utility-first approach (e.g., `class="flex items-center p-4"`)
- Rebuild assets when modifying custom components or Tailwind config
- Keep accessibility in mind (ARIA labels, focus states)

## Database & Queries

- **Query Builder**: Uses Aura.SqlQuery (via `src/Database/`)
- **Gateways**: Domain gateways handle all DB interactions
- **Migrations**: Schema changes go in `CHANGEDB.php`
- **Never commit**: `config.php` (contains DB credentials)

## Security Requirements

- **Input Validation**: Use existing validation utilities in `src/Data/`
- **Output Escaping**: Always escape in Twig templates
- **Authorization**: Check permissions before actions (use existing auth patterns)
- **CSRF Protection**: Use built-in token handling (`src/Session/TokenHandler.php`)
- **File Uploads**: Validate type/size, store in `uploads/` (not executable)
- **Never commit**: Secrets, API keys, `config.php`

## Testing Strategy

- **Unit Tests**: PHPUnit in `tests/unit/` - test services, gateways, utilities
- **Acceptance Tests**: Codeception in `tests/acceptance/` - test user workflows
- **Mock External Calls**: No real network requests in tests
- **Deterministic**: Seed fixtures, assert on explicit outputs
- **Coverage Gaps**: Test coverage is incomplete - always manual test alongside automated tests

## Git Workflow

### Commit Messages
- Short, imperative mood
- Optional scope prefix (e.g., `docs: Add diagram`, `Fix Calendar module for v30`)

### Pull Requests
- Include summary and linked issue
- Provide test plan and steps to reproduce
- Add screenshots/GIFs for UI changes
- Note schema changes, migrations, or config requirements
- Include rollback notes for risky changes

## AI Assistant Integration

This repository includes role-specific guidance in `agents/`:
- `agents/backend.md` - PHP development guidance
- `agents/frontend.md` - Vue/JS/Twig guidance
- `agents/testing.md` - Testing patterns
- `agents/security.md` - Security audit guidance

See `AGENTS.md` for detailed AI assistant workflows and prompt patterns.

## Common Gotchas

- **Webpack Build**: The `resources/build/` directory may be empty - verify files exist before assuming Webpack setup
- **Asset Rebuilding**: Most Tailwind utility changes don't require rebuilds, but custom components do
- **Messenger Module**: Has a circular dependency with `index.php` via `moduleFunctions.php` (known issue)
- **Caching**: Check `$caching` config value - affects when system settings reload
- **File Permissions**: `uploads/` needs web server write access (775)
- **PHP Extensions**: Requires ext-curl, ext-intl, ext-mbstring, ext-gettext, ext-PDO
