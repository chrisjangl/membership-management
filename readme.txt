=== Membership Management ===
Contributors: digitally-cultured
Tags: membership management, CRM
Requires at least: 5.0
Stable tag: 1.4.0
Tested up to: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Empower your organization with our Membership Management Plugin for WordPress. Effortlessly maintain and track membership status, contact details, and more, providing professional organizations and non-profits with a streamlined solution.

=== Description ===
Unlock the full potential of your organization with our Membership Management Plugin. Designed for professional organizations and non-profits, this feature-rich tool allows you to easily manage and organize your membership list. Keep track of member status, contact information, and more, all within the familiar WordPress environment. As your organization grows, our plugin scales with you, offering future features like member self-service options for updating information and dues payment. Simplify your membership management today.

=== FAQ ===
Q: How does the plugin track membership status?
A: The plugin provides a user-friendly interface within the WordPress dashboard to mark members as active or inactive based on your organization's criteria.
Q: Can members update their own information?
A: While not available in the initial release, future updates will introduce member self-service features, allowing them to update their information conveniently.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/membership-management` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Members menu in your WordPress admin to start managing your membership.
4. Configure settings under Members > Settings to customize the plugin for your organization.


== Changelog ==

= 1.4.0 =
* add member status filter dropdown and query functionality
* add renewal method functionality and filter to member management
* add Page URLs section and Login Page setting to General tab in settings
* add email template preview functionality with AJAX support
* enhance settings page with tab navigation and dynamic form behavior
* add SVN deployment script and configuration for WordPress.org
* Fix: correct path to WordPress load file in email handler and notifications tests
* Fix: skip processing for missing posted data in metaboxes
* Fix: fix redirect loop if trying to access member dashboard with a non-member account
* Fix: exclude tests directory & Claude.md in distribution and zip scripts in package.json
* Fix: update tested up to WordPress 6.8
* Fix: automate wp-repo commit and tag pushing before GitHub release
* Update tested up to version in plugin files to 7.0
* Merge branch 'feature/v1.3.4/enhanced-filters' into v1.3.4-candidate
* Merge branch 'feature/v1.3.4/email-template-improvements' into v1.3.4-candidate
* Centralize dashboard and login URL retrieval in settings
* Enhanced dashaboard filters
* Update CLAUDE.md and add CONTRIBUTING.md for improved project guidance and Git workflow
* Merge branch 'fix/v1.3.4/ajax-settings-tabs' into development
* Add CLAUDE.md for project guidance and development notes
* Add test scripts for MailChimp integration, notifications, recurring billing, renewal logic, status system, and subscription flow


