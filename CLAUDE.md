# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

This is a WordPress Membership Management Plugin for managing organizational memberships. The plugin uses a hybrid approach with both Custom Post Types (CPT) for member data and WordPress Users for authentication.

## Build Commands

- `npm run css` - Compile SASS files to CSS (compressed)
- `npm run watch` - Watch SASS files and auto-compile on changes
- `npm run dist` - Create a tar.gz distribution package
- `npm run zip` - Create a zip distribution package

## Testing Commands

- `npm run test` - Run all automated tests
- `npm run test:unit` - Run only unit tests  
- `npm run test:integration` - Run only integration tests
- `npm run test:coverage` - Run tests with coverage report
- `php run-tests.php` - Direct test runner (alternative to npm commands)

## Core Architecture

### Member Management System
The plugin centers around the `DCMM_Member` class which extends `WP_User` and manages member data through:
- **CPT Storage**: Member metadata stored in `dcmm-member` custom post type
- **User Integration**: WordPress user accounts for member authentication
- **Version Migration**: Supports data migration from v0.1 to v1.0+ architecture

### Key Classes and Files

#### Core Member Class
- `includes/class-member.php` - Main `DCMM_Member` class that handles member CRUD operations
- `includes/register-post-type.php` - Registers the `dcmm-member` custom post type
- `includes/class-member-metaboxes.php` - Admin interface metaboxes for member editing

#### Payment Gateway System
The plugin implements a gateway pattern for payment processing:
- `includes/gateways/interface-payment-gateway.php` - Payment gateway interface
- `includes/gateways/class-abstract-gateway.php` - Abstract base class for gateways  
- `includes/gateways/class-gateway-manager.php` - Gateway factory and manager
- `includes/gateways/class-gateway-paypal.php` - PayPal gateway implementation
- `includes/gateways.php` - Gateway system initialization

#### Admin and Frontend
- `includes/dcmm-admin.php` - Admin interface logic
- `includes/my-account.php` - Member dashboard frontend
- `includes/settings.php` - Plugin settings page (namespace: `DCMM_Settings`)
- `includes/admin-settings.php` - Additional admin settings functionality

#### Utilities
- `includes/importer.php` - Member data import functionality
- `includes/functions-user-role.php` - User role management functions

### Plugin Structure
- Main plugin file: `membership.php`
- Initialization: `includes/init.php` loads all components
- Constants: `DCMM_VERSION`, `DCMM_PATH`, `DCMM_URL`
- Meta prefix: `dcmm_` for all custom fields

### Member Data Schema
Key meta fields used by the `DCMM_Member` class:
- `dcmm_status` - Membership status
- `dcmm_wp_user_id` - Associated WordPress user ID
- `dcmm_membership_start_date` - Membership start date
- `dcmm_last_dues_payment` - Last dues payment date
- Contact fields: `dcmm_first_name`, `dcmm_last_name`, `dcmm_email`, `dcmm_phone`
- Address fields: `dcmm_street1`, `dcmm_street2`, `dcmm_city`, `dcmm_state`, `dcmm_zip`

### Current Development Areas
Based on git branch `feature/v1.1.0/payments`, the plugin is actively developing:
- Payment gateway integration
- Member renewal functionality
- Member self-service dashboard improvements
- Membership activity logging

## Development Notes

### Namespacing
- Gateway classes use `DCMM\Gateways` namespace
- Settings use `DCMM_Settings` namespace
- User functions use `DCMM_Users` namespace

### TODO Items in Codebase
- Dynamic gateway loading based on settings
- Implement member status checking methods
- Clean up mixed WP User and CPT ID handling

### Member Object Construction
The `DCMM_Member` class supports construction by:
- CPT Post ID (for existing members)
- Email address (planned feature)
- Version-aware constructor that handles v0.1 vs v1.0+ data structures

## Release & Changelog Process

### Commit Message Strategy
Use Conventional Commits format for all commits:
```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Types:** feat, fix, docs, style, refactor, test, chore
**Scopes:** Member, Gateway, Admin, Settings, UX, etc.

### Changelog Generation Process
1. **During Development**: Keep offline notes for major features
2. **Pre-release**: Extract commits using `git log --pretty=format:"%s" --grep="feat\|fix\|BREAKING"`
3. **Curate**: Group related commits and rewrite for end-users
4. **Categorize**: Organize into sections:
   - 🚀 New Features
   - 🔧 Improvements
   - 🐛 Bug Fixes
   - ⚠️ Breaking Changes
5. **Manual Polish**: Ensure changelog is user-friendly and comprehensive

### Changelog Structure Template
```markdown
## [vX.X.X] - YYYY-MM-DD

### 🚀 New Features
- **Feature Name** - Brief description of user benefit

### 🔧 Improvements  
- **Area** - What was improved and why

### 🐛 Bug Fixes
- Fixed specific issue description

### ⚠️ Breaking Changes
- Database schema changes, API changes, etc.
```

### Git Commands for Changelog
```bash
# Extract feature commits
git log --oneline --grep="feat" --since="last-release-date"

# Extract all relevant commits
git log --pretty=format:"%s" --grep="feat\|fix\|BREAKING" --since="last-release-date"

# Get commit count for version
git rev-list --count HEAD ^last-release-tag
```