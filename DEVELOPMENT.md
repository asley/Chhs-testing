# Gibbon Development Guide

This document provides comprehensive guidelines for developing with Gibbon SIS, including environment setup, build processes, testing, and module development.

---

## Table of Contents

1. [Development Environment Setup](#development-environment-setup)
2. [Front-End Build Process](#front-end-build-process)
3. [Automated Testing](#automated-testing)
4. [Module Development](#module-development)
5. [Git Workflow](#git-workflow)
6. [Resources](#resources)

---

## Development Environment Setup

### System Requirements

#### Current Installation
- **PHP Version**: 8.4.6
- **Composer Version**: 2.8.3
- **Node.js Version**: v24.10.0
- **npm Version**: 11.6.0
- **Git**: Installed and configured
- **Database**: MySQL (via MAMP)

### PHP Configuration

The following PHP settings have been configured for Gibbon:

```ini
# Location: /opt/homebrew/etc/php/8.4/php.ini

max_file_uploads = 60
max_input_vars = 5000
error_reporting = E_ALL & ~E_NOTICE
allow_url_fopen = On
```

### File Permissions

```bash
# All Gibbon files
chmod -R 755 /Applications/MAMP/htdocs/chhs-testing

# Uploads folder (web server write access)
chmod -R 775 /Applications/MAMP/htdocs/chhs-testing/uploads
```

### Directory Structure

```
chhs-testing/
├── cli/                    # Command-line scripts
├── lib/                    # Third-party libraries
├── modules/                # Gibbon modules
├── resources/              # Assets and templates
│   ├── assets/            # CSS, JS, fonts
│   ├── build/             # Webpack build configuration
│   ├── imports/           # Data import templates
│   └── templates/         # Twig templates
├── src/                   # PHP source code
├── tests/                 # Automated tests
├── uploads/               # User-uploaded files (excluded from Git)
├── vendor/                # Composer dependencies (excluded from Git)
├── config.php             # Database configuration (excluded from Git)
├── .gitignore            # Git ignore rules
└── composer.json         # PHP dependencies

```

---

## Front-End Build Process

### Overview

As of v18, Gibbon uses **Webpack** configured with **Tailwind CSS** via **Laravel Mix**. This provides:
- Utility-first CSS framework
- Modern JavaScript bundling
- Asset optimization
- Hot module replacement for development

### Setup Build Tools

#### 1. Navigate to Build Directory

```bash
cd /Applications/MAMP/htdocs/chhs-testing/resources/build
```

#### 2. Install Dependencies

```bash
npm install
```

This installs:
- Laravel Mix
- Tailwind CSS
- Webpack and related tools
- PostCSS plugins

### Build Commands

#### Production Build
```bash
npm run build
```
- Compiles and minifies CSS/JS
- Optimizes assets for production
- Outputs to `resources/assets/`

#### Development Build
```bash
npm run dev
```
- Compiles assets without minification
- Includes source maps for debugging
- Faster build times

#### Watch Mode
```bash
npm run watch
```
- Automatically rebuilds when files change
- Ideal for active development
- Monitors CSS and JS files

### File Locations

**Source Files:**
- CSS: `resources/build/css/`
- JavaScript: `resources/build/js/`
- Tailwind Config: `resources/build/tailwind.config.js`
- Webpack Config: `resources/build/webpack.mix.js`

**Output Files:**
- `resources/assets/css/core.css`
- `resources/assets/css/theme.css`
- `resources/assets/js/core.js`

### Tailwind CSS in Gibbon

Tailwind provides utility classes that cover most styling needs without rebuilding. Examples:

```html
<!-- Spacing -->
<div class="p-4 m-2">Padding and margin</div>

<!-- Layout -->
<div class="flex justify-between items-center">Flexbox layout</div>

<!-- Colors -->
<button class="bg-blue-500 text-white hover:bg-blue-600">Button</button>

<!-- Responsive -->
<div class="w-full md:w-1/2 lg:w-1/3">Responsive width</div>
```

**When to Rebuild:**
- Creating new custom components
- Modifying Tailwind configuration
- Adding custom CSS
- Updating core styles
- Refactoring existing components

---

## Automated Testing

### Overview

Gibbon uses two testing frameworks:
1. **PHPUnit** - Unit testing
2. **Codeception** - Integration testing

**Important:** Test coverage is not complete. Always perform manual testing in addition to automated tests.

### PHPUnit Setup

#### 1. Install Dependencies

PHPUnit is included in `composer.json`. Install via:

```bash
cd /Applications/MAMP/htdocs/chhs-testing
composer install
```

#### 2. Run PHPUnit Tests

```bash
cd tests
../vendor/bin/phpunit .
```

#### 3. PHPUnit Configuration

Configuration file: `tests/phpunit.xml`

**Running Specific Tests:**
```bash
# Single test file
../vendor/bin/phpunit path/to/TestFile.php

# Specific test method
../vendor/bin/phpunit --filter testMethodName

# Test suite
../vendor/bin/phpunit --testsuite UnitTests
```

### Codeception Setup

#### 1. Install Codeception

```bash
cd /Applications/MAMP/htdocs/chhs-testing
composer require codeception/codeception --dev
```

#### 2. Enable Codeception in Gibbon

Add to `config.php`:

```php
$testEnvironment = 'codeception';
```

**⚠️ Warning:** Only enable in development environment, never in production!

#### 3. Configure Database Connection

Codeception requires a test database. Configure in `tests/codeception.yml`:

```yaml
modules:
    config:
        Db:
            dsn: 'mysql:host=localhost;dbname=gibbon_test'
            user: 'root'
            password: 'password'
            dump: tests/_data/dump.sql
```

#### 4. Run Codeception Tests

```bash
cd tests
../vendor/bin/codecept run
```

**Available Commands:**
```bash
# Run all tests
../vendor/bin/codecept run

# Run specific suite
../vendor/bin/codecept run acceptance

# Run with detailed output
../vendor/bin/codecept run --steps

# Generate code coverage
../vendor/bin/codecept run --coverage
```

### Continuous Integration

**GitHub Actions** automatically runs tests on pull requests to the development branch.

Configuration file: `.github/workflows/tests.yml`

Tests run on:
- Push to `development` branch
- Pull requests targeting `development`
- Multiple PHP versions (7.4, 8.0, 8.1)

---

## Module Development

### Module Overview

Modules extend Gibbon functionality without modifying core code. Benefits:
- Upgrade-safe customizations
- Modular architecture
- Permission-based access control
- Easy installation/uninstallation

### Starter Module

Download the official starter module:
```bash
git clone https://github.com/GibbonEdu/module-starterModule.git
```

### Module Structure

```
moduleName/
├── CHANGEDB.php           # Database schema changes per version
├── CHANGELOG.txt          # Version history
├── LICENSE                # Module license
├── manifest.php           # Installation configuration
├── version.php            # Current code version
├── moduleFunctions.php    # Module-specific functions
├── css/
│   └── module.css        # Module styles
├── js/
│   └── module.js         # Module scripts
├── img/                  # Module images
├── i18n/                 # Translations
│   ├── en_GB/
│   │   └── LC_MESSAGES/
│   │       ├── ModuleName.po
│   │       └── ModuleName.mo
│   └── es_ES/
│       └── LC_MESSAGES/
├── src/
│   └── Domain/           # QueryableGateways (auto-loaded)
└── [action files].php    # Module pages

```

### Essential Files

#### 1. manifest.php

Defines module installation:

```php
<?php
// Module metadata
$name = 'Module Name';
$description = 'Description of what the module does';
$entryURL = 'index.php';
$type = 'Additional';
$category = 'Other';
$version = '1.0.00';
$author = 'Your Name';
$url = 'https://yourwebsite.com';

// Database tables
$moduleTables[0] = "CREATE TABLE moduleTable (
    moduleTableID INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    PRIMARY KEY (moduleTableID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Actions (pages)
$actionRows[0] = [
    'name' => 'View Page',
    'precedence' => '0',
    'category' => 'Main',
    'description' => 'Allows users to view the page',
    'URLList' => 'index.php',
    'entryURL' => 'index.php',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y',
    'defaultPermissionStudent' => 'Y',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N'
];

// Settings
$settingsRows[0] = [
    'name' => 'Setting Name',
    'nameDisplay' => 'Setting Display Name',
    'description' => 'Setting description',
    'value' => 'default value'
];

// Hooks
$array = [];
$array['sourceModuleName'] = $name;
$array['sourceModuleAction'] = 'View Page';
$array['sourceModuleInclude'] = 'hook_studentProfile.php';

$sql = "INSERT INTO gibbonHook SET
    name='Module Name',
    type='Student Profile',
    options='".serialize($array)."',
    gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='$name')";
```

#### 2. version.php

```php
<?php
// Module version number
$moduleVersion = '1.0.00';
```

#### 3. CHANGEDB.php

```php
<?php
// Database changes for upgrades
$sql = [];
$count = 0;

// Version 1.0.01
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "ALTER TABLE moduleTable ADD COLUMN newField VARCHAR(100);";
$count++;

// Version 1.0.02
$sql[$count][0] = '1.0.02';
$sql[$count][1] = "CREATE TABLE newTable (...);";
$count++;
```

#### 4. index.php (Sample Action)

```php
<?php
use Gibbon\Forms\Form;
use Gibbon\Services\Format;

// Check module access
if (!isActionAccessible($guid, $connection2, '/modules/Module Name/index.php')) {
    die(Format::alert(__('You do not have access to this action.')));
}

// Page logic
$page->breadcrumbs->add(__('Module Name'));

echo '<h2>' . __('Welcome to Module Name') . '</h2>';

// Create a form
$form = Form::create('moduleForm', $session->get('absoluteURL') . '/modules/Module Name/indexProcess.php');
$form->addRow()->addHeading(__('Form Heading'));
$row = $form->addRow();
$row->addLabel('name', __('Name'));
$row->addTextField('name')->required();
$form->addRow()->addSubmit();

echo $form->getOutput();
```

### Module Namespacing

Use proper namespaces for auto-loading:

```php
<?php
namespace Gibbon\Module\ModuleName\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class MyGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'moduleTable';

    public function selectByID($id)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->where('moduleTableID = :id');

        return $this->runSelect($query, ['id' => $id]);
    }
}
```

### Module Translation

#### 1. Directory Structure

```
i18n/
├── en_GB/
│   └── LC_MESSAGES/
│       ├── ModuleName.po
│       └── ModuleName.mo
└── es_ES/
    └── LC_MESSAGES/
        ├── ModuleName.po
        └── ModuleName.mo
```

#### 2. Mark Strings for Translation

In manifest.php:
```php
$actionRows[0]['description'] = __($guid, 'Allows a user to view the page.');
```

In module code:
```php
echo __($guid, 'Welcome Message', 'Module Name');
```

#### 3. Generate PO Files

Use xgettext to extract translatable strings:

```bash
#!/bin/bash
# xgettextGenerationCommands.sh

find . -name "*.php" -o -name "*.twig.html" | \
xgettext --files-from=- \
    --language=PHP \
    --keyword=__ \
    --keyword=__m \
    --from-code=UTF-8 \
    --output=i18n/en_GB/LC_MESSAGES/ModuleName.po
```

#### 4. Compile MO Files

```bash
msgfmt i18n/en_GB/LC_MESSAGES/ModuleName.po \
    -o i18n/en_GB/LC_MESSAGES/ModuleName.mo
```

### Module Hooks

Hooks allow modules to inject content into core pages.

**Available Hook Types:**
- Parent Dashboard
- Student Dashboard
- Staff Dashboard
- Public Homepage
- Student Profile
- Unit

#### Dashboard Hook Example

```php
// In manifest.php
$hooks[0] = "INSERT INTO gibbonHook SET
    name='My Module Dashboard',
    type='Student Dashboard',
    options='".serialize([
        'sourceModuleName' => 'My Module',
        'sourceModuleAction' => 'View Dashboard',
        'sourceModuleInclude' => 'hook_studentDashboard.php'
    ])."',
    gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='$name')";
```

#### Hook File (hook_studentDashboard.php)

```php
<?php
use Gibbon\Services\Format;

echo '<h2>' . __('My Module Dashboard') . '</h2>';

// Display module-specific student data
$data = ['gibbonPersonID' => $session->get('gibbonPersonID')];
$sql = "SELECT * FROM moduleTable WHERE gibbonPersonID = :gibbonPersonID";
$result = $connection2->prepare($sql);
$result->execute($data);

if ($result->rowCount() > 0) {
    echo '<ul>';
    while ($row = $result->fetch()) {
        echo '<li>' . Format::name('', $row['name'], '', '', '') . '</li>';
    }
    echo '</ul>';
} else {
    echo Format::alert(__('No data available.'), 'message');
}
```

#### Unit Hook Example

```php
$array = [
    'unitTable' => 'myModuleUnits',
    'unitIDField' => 'myModuleUnitID',
    'unitCourseIDField' => 'gibbonCourseID',
    'unitNameField' => 'name',
    'unitDescriptionField' => 'description',
    'classLinkTable' => 'myModuleUnitClass',
    'classLinkJoinField' => 'myModuleUnitID',
    'classLinkIDField' => 'gibbonCourseClassID',
    'classLinkStartDateField' => 'startDate'
];

$sql = "INSERT INTO gibbonHook SET
    name='My Module Units',
    type='Unit',
    options='".serialize($array)."',
    gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='$name')";
```

### Module Installation Process

1. Upload module to `/modules/` directory
2. Navigate to System Admin > Manage Modules
3. Click "Install" next to your module
4. Gibbon runs `manifest.php` to:
   - Create database tables
   - Register actions
   - Set default permissions
   - Insert settings
   - Create hooks

### Module Update Process

1. Increment version in `version.php`
2. Add upgrade SQL to `CHANGEDB.php`
3. Upload new files
4. Navigate to System Admin > Manage Modules
5. Click "Update" next to your module

### Best Practices

#### Security
```php
// Always validate input
$name = $_POST['name'] ?? '';
if (empty($name)) {
    die(Format::alert(__('Name is required.')));
}

// Use prepared statements
$data = ['name' => $name];
$sql = "INSERT INTO moduleTable SET name=:name";
$pdo->runInsert($sql, $data);

// Check permissions
if (!isActionAccessible($guid, $connection2, '/modules/Module/page.php')) {
    die(Format::alert(__('Access denied.')));
}
```

#### Database Queries
```php
// Use QueryableGateways instead of raw SQL
$gateway = $container->get(MyGateway::class);
$results = $gateway->selectByID($id);

// Use Query Builder
$query = $gateway
    ->newSelect()
    ->from('moduleTable')
    ->where('status = :status')
    ->orderBy(['dateCreated DESC']);

$result = $gateway->runSelect($query, ['status' => 'Active']);
```

#### Forms
```php
// Use Gibbon Form Builder
$form = Form::create('myForm', $action);

$row = $form->addRow();
$row->addLabel('email', __('Email'));
$row->addEmail('email')->required()->maxLength(50);

$row = $form->addRow();
$row->addLabel('status', __('Status'));
$row->addSelect('status')->fromArray(['Active', 'Inactive'])->required();

$form->addRow()->addSubmit();
echo $form->getOutput();
```

---

## Git Workflow

### Repository Information

**Remote Repository:** https://github.com/asley/Chhs-testing.git
**Default Branch:** main

### Daily Workflow

```bash
# 1. Check current status
git status

# 2. Pull latest changes
git pull origin main

# 3. Create feature branch
git checkout -b feature/my-new-feature

# 4. Make changes and stage files
git add .

# 5. Commit with descriptive message
git commit -m "Add new feature: description

- Detailed change 1
- Detailed change 2
- Detailed change 3"

# 6. Push to GitHub
git push origin feature/my-new-feature

# 7. Create pull request on GitHub
# 8. After merge, switch back to main
git checkout main
git pull origin main

# 9. Delete feature branch
git branch -d feature/my-new-feature
```

### Commit Message Convention

```
<type>: <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

**Example:**
```
feat: Add student attendance dashboard widget

- Created new widget component
- Integrated with attendance module
- Added permission checks
- Updated module manifest

Closes #123
```

### Branch Strategy

- `main` - Stable production code
- `development` - Integration branch
- `feature/*` - New features
- `bugfix/*` - Bug fixes
- `hotfix/*` - Emergency fixes

### Important Files in .gitignore

```gitignore
# Sensitive configuration
config.php

# User uploads
uploads/*
!uploads/.htaccess

# Dependencies
vendor/
node_modules/

# Cache
uploads/cache/

# System files
.DS_Store
*.log
```

---

## Resources

### Official Documentation
- **Gibbon Docs**: https://docs.gibbonedu.org
- **GitHub Repository**: https://github.com/GibbonEdu/core
- **Module Development**: https://docs.gibbonedu.org/developers/module-development

### Community
- **Forums**: https://ask.gibbonedu.org
- **Discord**: https://discord.gg/gibbon
- **Support Email**: support@gibbonedu.org

### Development Tools
- **Laravel Mix**: https://laravel-mix.com/docs
- **Tailwind CSS**: https://tailwindcss.com/docs
- **PHPUnit**: https://phpunit.de/documentation.html
- **Codeception**: https://codeception.com/docs

### Code Standards
- **PSR-12**: PHP coding standards
- **ESLint**: JavaScript linting
- **Prettier**: Code formatting

### Helpful Commands Reference

```bash
# Front-end Build
npm run build          # Production build
npm run dev           # Development build
npm run watch         # Watch mode

# Testing
../vendor/bin/phpunit .                    # Run PHPUnit
../vendor/bin/codecept run                 # Run Codeception
../vendor/bin/codecept run --steps         # Verbose output

# Composer
composer install                           # Install dependencies
composer update                            # Update dependencies
composer require package/name             # Add package

# Git
git status                                 # Check status
git log --oneline                         # View commit history
git diff                                  # View changes
git branch                                # List branches

# File Permissions
chmod -R 755 .                            # Set file permissions
chmod -R 775 uploads                      # Set upload permissions
```

---

## Environment Variables

Create a `.env` file for local configuration (not tracked in Git):

```env
# Database
DB_HOST=localhost
DB_NAME=gibbon
DB_USER=root
DB_PASSWORD=

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8888/chhs-testing

# Testing
TEST_DB_NAME=gibbon_test
CODECEPTION_ENABLED=true
```

---

## Troubleshooting

### Build Issues

**Problem:** `npm run build` fails
```bash
# Solution
cd resources/build
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Permission Issues

**Problem:** Cannot write to uploads folder
```bash
# Solution
chmod -R 775 uploads
# Ensure web server user has write access
chown -R _www:staff uploads  # Mac OS
```

### Database Issues

**Problem:** Codeception tests fail
```bash
# Solution
# 1. Verify test database exists
# 2. Check codeception.yml configuration
# 3. Ensure test environment is enabled in config.php
# 4. Run: ../vendor/bin/codecept build
```

### Module Installation Issues

**Problem:** Module won't install
- Check manifest.php syntax
- Verify database credentials
- Check PHP error logs
- Ensure proper file permissions

---

## Next Steps

1. **Explore the Codebase**: Familiarize yourself with Gibbon's structure
2. **Set Up IDE**: Configure VS Code or PhpStorm with PHP/Gibbon extensions
3. **Create Test Module**: Build a simple module using the starter template
4. **Run Tests**: Practice running PHPUnit and Codeception tests
5. **Contribute**: Join the community and contribute to Gibbon development

---

**Last Updated:** 2025-11-30
**Gibbon Version:** v27.0.00
**Author:** Development Team
