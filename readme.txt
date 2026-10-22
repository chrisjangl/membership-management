=== Membership Management ===
Contributors: digitally-cultured
Tags: membership management, CRM
Requires at least: 5.0
Stable tag: 1.4.0
Tested up to: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Self-hosted membership management for clubs, nonprofits, and associations — dues, renewals, and a member dashboard, without the SaaS fee.

== Description ==

Membership Management replaces expensive SaaS membership tools with a self-hosted system you fully own. Built for clubs, nonprofits, associations, and any organization that manages dues-paying members, it handles the full membership lifecycle — sign-up, payment, renewal, and expiration — without a monthly software fee or vendor lock-in.

Track every member's status, contact details, and payment history in one place. Accept one-time dues or recurring subscriptions through PayPal, or record offline payments like checks and wire transfers. An automated reminder system emails members before they lapse, and a self-service dashboard lets members renew, manage their subscription, or update their own information — no admin intervention required.

= Key Features =

* **Flexible renewal terms** — Choose the expiration model that matches how your organization actually runs: rolling anniversary terms, a fixed calendar-year cutoff, or an anchored renewal date. No code required.
* **Built-in payment processing** — Accept one-time dues or recurring subscriptions through PayPal, or track offline payments (checks, wire transfers, cash) manually.
* **Automated expiration reminders** — Configurable email notices at 30 days, 7 days, 1 day, and past-due keep members renewing on time, with built-in duplicate-send protection.
* **Member self-service dashboard** — Members log in to renew, manage their subscription, or update their own contact information without emailing an administrator.
* **Role-based access control** — Dedicated Member, Membership Manager, and Membership Administrator roles give staff exactly the access they need.
* **CSV import** — Bring an existing roster in from a spreadsheet in minutes.
* **MailChimp sync (premium)** — Keep your email list in sync automatically as members join, renew, or lapse.

= Who It's For =

Clubs, nonprofits, associations, and subscription communities that manage a roster of dues-paying members and want to own their membership data instead of renting it from a SaaS platform.

== FAQ ==
Q: How does the plugin track membership status?
A: The plugin provides a user-friendly interface within the WordPress dashboard to mark members as active or inactive based on your organization's criteria.
Q: Can members update their own information?
A: Yes. Members get a self-service dashboard where they can update their contact information, renew their membership, and manage their subscription without contacting an administrator.

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


