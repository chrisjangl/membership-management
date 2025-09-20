=== Membership Management ===
Contributors: digitally-cultured
Tags: membership management, CRM
Requires at least: 5.0
Stable tag: 1.2.0
Tested up to: 6.5
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

= 1.2.0 =
* :sparkles: Add auto-sync option for member data <-> WordPress users
* :sparkles: Recurring billing via PayPal
* simplify membership status system and enhance expiration logic
* implement offline payment recording for membership renewals   - Add Gateway_Offline  class following  existing payment  gateway pattern   - Enhance renewal metabox with expandable payment form (amount, method, reference, date, notes)   - Integrate offline payments with admin-post system using AJAX submission   - Store payment details in dcmm_last_payment meta with full audit trail   - Update Action Log metabox to display both general and payment logs chronologically   - Add offline payment settings to Payment Gateways tab (enable/disable, methods, reference requirements)   - Fix settings form conflicts between General and Payment Gateway tabs   - Add Member->exists() method for proper validation   - Include optional email receipt functionality controlled by admin
* implement 30-day renewal window system with grace period
* :sparkles: Send email when member signs up or renews
* :sparkles: Connect PayPal gateway with User renewal
* :tada: Introduce Payment Gateways
* :sparkles: Add logging for membership activites (renewal, cancellation)
* :sparkles: Renew/cancel membership from the Edit Member (single) screen
* :sparkles: Allow Members to renew their membership from their Member Dashboard
* :sparkles: Introduce Member Dashboard (WIP)
* :sparkles: re-introduce WP User, linking it to the CPT
* :sparkles: Introduce Settings page
* Create way to import member data via .csv
* Fix: :bug: Renewing membership didn't take the grace period into account
* Fix: "Create WP User" button not working on Edit Member screen
* Fix: :bug: Improvements to the WP User creation flow from a Member
* Fix: :sparkles: Store Member ID on the WP User meta
* Fix: :bug: Fix warnings during import & Edit screen when there's no address for a member
* Fix: importer wasn't properly storing member's data
* Fix: zip dist script was including .git folder
* Fix: namespace has to be first line of file
* Create plan to migrate settings to use WordPress Settings API properly
* 404 "author pages" for members
* :lipstick: add loading state to "Record Payment & Subscribe" button
* Chrisjangl/membership-management into feature/v1.1.1/build-scripts
* Version bump -> 1.1.1
* Version bump -> 1.1.1
* :technologist: Create a WP plugin repo deployment experience
* Assets/css/ -> css/, etc.
* :lipstick: Clean up renewal messaging/availability on Member Dashboard
* :lipstick: Show loading state when clicking "Create WP User Account"
* :construction_worker: Add to exclusion list in the distribution script
* Don't show number of days remaining in admin list
* Implement MailChimp integration with premium features architecture
* :lipstick: Change Settings page to use tabbed interface
* Implement automated membership expiration email system
* Add membership expiration date calculation system
* Clean up TODOs
* Connect member renewal with (dummy) PayPal gateway
* :art: Renamed styles & JS; changed build process (node-sass -> Dart SASS CLI), created .nvmrc for consistency in future
* Merge branch 'feature/v1.1.0/member-login' into release/v1.1.0
* SVN release issues
* Merge pull request #5 from chrisjangl/release/v1.0.0
* Stable tag bump -> 1.0.0
* Chrisjangl/membership-management into release/v1.0.0
* Merge branch 'feature/v1.0.0/plugin-info' into release/v1.0.0
* Merge branch 'feature/v1.0.0/info-columns' into release/v1.0.0
* Changes to Dashicon for menu & readme
* Add email & phone to columns
* Add address to columns
* Add "Name" & "Status" columns to the all members screen, removing extraneous columns
* Merge pull request #1 from chrisjangl/feature/v1.0.0/importer
* (temporarily?) change member import to not create WP user
* .gitignore DS_Store
* Add bulk import for members
* Create a re-usable way to save the meta for a Member CPT
* All info gets stored on the CPT; no automatic creation on WP User
* Version bump to 1.0.0
* Bump tested up to version
* Change the file name of the zip in distribution script
* Add dcmm_ prefix
* Change our post type to dcmm-member
* Escape generated variables
* Add option to zip plugin folder instead of archive in build scripts
* Create dist script
* Move JS packages to main plugin directory
* Version & stable tag bump -> 0.1.1
* Remove importer feature from this release
* Merge branch 'feature/v0.1.0/importer' into release/v0.1.1
* Change meta data prefices (?), make them dynamic and re-use the member contact info
* Change prefix to dcmm_
* Escape variables when echo'ing
* Do not allow direct access to files
* Sanitive incoming data
* Replace PHP short tags with `echo`
* Clean up My Account page
* Create way to import member data via .csv
* License
* My Accunt
* Store meta about the member on the WP User, not the CPT post
* .gitignore node modules
* Delete class-contact (no longer how we're doing things around here)
* Create WP user when creating CPT
* Initialize repo & start scaffolding


