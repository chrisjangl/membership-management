# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

This is a WordPress Membership Management Plugin for managing organizational memberships. The plugin uses a hybrid approach with both Custom Post Types (CPT) for member data and WordPress Users for authentication.

## Build Commands

- `npm run css` - Compile SASS files to CSS (compressed)
- `npm run watch` - Watch SASS files and auto-compile on changes
- `npm run dist` - Create a tar.gz distribution package (legacy; prefer `build:dist`)
- `npm run zip` - Create a zip distribution package (legacy; prefer `build:dist`)
- `npm run build:dist` - Build a clean `dist/` copy using the include-list in `scripts/build-dist.js` (this is the one the release pipeline actually uses)
- `npm run prepare:wp-repo` - Build `dist/`, then sync it onto the `wp-repo` branch (see Git Workflow)
- `npm run validate:release` - Run WordPress.org compliance checks (readme.txt headers, version consistency, required files, dev-file leakage)
- `npm run release` / `version:patch` / `version:minor` / `version:major` - Bump version, update readme.txt, tag, and prep for deployment
- `npm run deploy:svn` - Push the `wp-repo` branch to the WordPress.org SVN trunk and create a version tag
- `npm run deploy:svn:assets` - Push `.wordpress-org/assets/` (icon, banner) to the SVN repo's `assets/` folder — see "WordPress.org Deployment" below

## Testing

Test scripts live in the `tests/` directory. Run them individually by loading them through WordPress (they require ABSPATH to be defined):

- `tests/test-status-system.php` - Status system validation
- `tests/test-renewal-logic.php` - Renewal logic
- `tests/test-subscription-flow.php` - Subscription flow
- `tests/test-notifications.php` - Notification system
- `tests/test-email-handler.php` - Email handler
- `tests/test-mailchimp.php` - Mailchimp integration
- `tests/test-recurring-billing.php` - Recurring billing

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
- `npm run dist` and `npm run zip` in package.json don't exclude `scripts/` — they should, to match what `build:dist` and the release process produce

### Member Object Construction
The `DCMM_Member` class supports construction by:
- CPT Post ID (for existing members)
- Email address (planned feature)
- Version-aware constructor that handles v0.1 vs v1.0+ data structures

## Git Workflow

### Branch Structure
- `stable` — release-only. Never commit feature work here directly. The release script manages all merges to this branch.
- `development` — integration branch. All feature branches for an upcoming version are merged here first.
- Feature branches — always branch from `development`, named `feat/vX.X.X/short-description`, `fix/vX.X.X/short-description`, or `developer/vX.X.X/short-description`.

### Flow for a New Feature
1. Branch from `development`: `git checkout -b feat/vX.X.X/your-feature development`
2. Do your work and commit using Conventional Commits (see below).
3. Merge back into `development` when complete.
4. Repeat for each feature targeting the same version.
5. When all features for the version are merged into `development`, merge `development` → `stable` and run the release script.

### What Not to Do
- Do not branch from `stable` for feature work.
- Do not create `release/vX.X.X` branches — these are a legacy pattern and the release script does not use them.
- Do not push directly to `stable` — the release script (`npm run release`) handles that.

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

## WordPress.org Deployment

### SVN Setup (one-time, per machine)
`scripts/deploy-svn.js` needs a local SVN checkout of the plugin. Copy `scripts/release-local.example.js` to `scripts/release-local.js` (gitignored) and set `svnLocalPath` to that checkout. Without this file, both `deploy:svn` and `deploy:svn:assets` fail fast with a clear error rather than doing anything partial.

### Two Different "assets" Directories — Don't Confuse Them
- **`assets/images/`** — ships *inside* the plugin. Currently holds `dashicon.svg`, the admin-menu icon, loaded as a base64 SVG data URI in `includes/register-post-type.php` (the `dcmm-member` CPT's `menu_icon`, which is the plugin's one live top-level admin menu — the second menu registration in `admin-settings.php` is dead code, its `add_action` is commented out). `build-dist.js` explicitly includes `assets/images/**/*`.
- **`.wordpress-org/assets/`** — never ships with the plugin. Holds the WordPress.org *repo listing* graphics: `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`. These filenames are meaningful — WordPress.org auto-detects them by exact name. This directory is git-tracked (source of truth) but explicitly excluded from `dist/`, the `wp-repo` branch, and any zip — see "Keeping Exclude Lists in Sync" below.

### Deploying Repo Assets (icon, banner)
Icon/banner updates are independent of code releases — no version bump, no tag. Run:
```bash
npm run deploy:svn:assets
```
This reads from `.wordpress-org/assets/` in the current working tree (not the `wp-repo` branch export), rsyncs into the SVN checkout's root-level `assets/` folder (a sibling of `trunk/`, not `assets/images/`), shows you the `svn status` diff, waits for Enter, then commits. The rsync intentionally has no `--delete` — the SVN `assets/` folder may hold files (e.g. `screenshot-*.png`) that were never tracked in git, and a blind mirror would delete them.

Regular code deploys (`npm run deploy:svn`) never touch the SVN `assets/` folder at all — that sync only happens via `deploy:svn:assets`.

### Keeping Exclude Lists in Sync
There is no single source of truth for "what doesn't ship" — it's duplicated across several places for historical reasons. If you ever add a new top-level directory that shouldn't reach WordPress.org (like `.wordpress-org/` itself), it needs to be added in all of these:
- `scripts/release-config.js` — `buildExcludePatterns`, `zipExcludePatterns`, `svnExcludePatterns`
- `scripts/build-dist.js` — its own local `excludePatterns` (belt-and-suspenders; `build-dist.js` actually works off an *include*-list, so anything not explicitly included is already excluded by default)
- `scripts/validate-release.js` — `validateNoDevFiles()`'s `devFiles` list
- `scripts/release.js` — `getZipExcludePatterns()`
- `package.json` — the legacy `dist` and `zip` scripts' inline exclude flags

### readme.txt Header Levels (gotcha)
WordPress.org's readme parser only recognizes section headers with **two** equals signs (`== Description ==`), not three. `=== `is reserved for the plugin name on line 1. This file previously had `=== Description ===` and `=== FAQ ===` (three equals), which likely meant those sections weren't rendering on the actual plugin page — fixed. `scripts/release.js`'s own `ensureReadmeSections()` already validates against the correct two-equals format, so trust that over hand-edited headers.