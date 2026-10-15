=== QuixDevs Tickera REST API ===
Contributors: hasanonline
Tags: tickera, rest api, events, ticket types, author permissions
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: tickera-event-ticketing-system
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure WordPress REST API for managing owned Tickera events and ticket types with authenticated author permissions.

== Description ==

QuixDevs Tickera REST API is a free, open-source extension for standalone Tickera. Authenticated users can create, list, retrieve and partially update their own events and ticket types. Strict event, ticket and parent-event ownership applies even to administrators using this API.

The eight GET/POST/PATCH operations use WordPress Application Passwords over HTTPS or cookie/REST nonce authentication, registered WordPress capabilities, explicit field allowlists and verified Tickera save hooks. Supported fields cover local event dates, location, terms, existing categories, owned images, decimal ticket prices, quantity, sale dates and owned templates. No capabilities are granted automatically.

Standalone Tickera 3.6.0.8 must be installed and active. Other Tickera versions fail closed pending verification. WooCommerce is unnecessary. WooCommerce Bridge ticket products, Stripe/payment gateways, checkout, purchasing, orders, attendees, refunds, check-in and deletion are outside scope. Detected Bridge mode blocks ticket operations; event operations remain available.

Header minimums are WordPress 6.8 and PHP 8.1. Local integration verification covered WordPress 6.8/7.1.3 with PHP 8.2.4/8.4.13 and Tickera 3.6.0.8; GitHub CI also passed PHP 8.1/8.2/8.3/8.4 with WordPress 6.8/7.1.3 and MySQL 8.0 at the recorded commit. See the GitHub compatibility/testing documentation for limits.

The installation ZIP includes docs/rest-api.md with the full contract. The GitHub repository also provides curl, JavaScript and PHP examples and an importable Postman collection. Composer is not needed on the server. MySQL/MariaDB connection advisory locks are required for writes; conflicts return 409. A late WordPress hook failure can partially persist, so inspect resources before retrying creation.

Repository and documentation: https://github.com/hasancse06/QuixDevs-Tickera-REST-API
Releases: https://github.com/hasancse06/QuixDevs-Tickera-REST-API/releases

Independent extension; not officially affiliated with Tickera. Publisher: QuixDevs, https://quixdevs.com/. Developed by Md Abu Hasan, https://hasan.online/ and https://github.com/hasancse06. Copyright 2026 QuixDevs. Licensed GPL-2.0-or-later.

== Installation ==

1. Download quixdevs-tickera-rest-api.zip from a published GitHub release's Assets. The latest-download link becomes available after a stable release contains that exact asset. GitHub's automatic Source code ZIP is not the recommended installer.
2. Open WordPress Admin and go to Plugins > Add New > Upload Plugin.
3. Choose the installation ZIP and click Install Now.
4. Ensure standalone Tickera 3.6.0.8 is installed and activated, then activate QuixDevs Tickera REST API. WordPress normally blocks addon activation while the declared dependency is inactive.
5. Set the site timezone, provision selected-user Tickera capabilities and configure HTTPS with per-user Application Passwords, or a logged-in cookie with a REST nonce.
6. Request /wp-json/quixdevs-tickera/v1/events as that user, then retrieve an event ID owned by the same user. See docs/rest-api.md and the GitHub authentication guide.

There is no addon settings page or shared API key. Tickera > QuixDevs REST API (Tools fallback during onboarding) shows dependency information and the API guide. If Tickera later disappears, the addon loads safely, shows an administrator notice and returns 503 for authenticated management requests.

== Frequently Asked Questions ==

= Which Tickera version is supported? =
Standalone Tickera 3.6.0.8. Other versions fail closed until verified. Premium Bridge compatibility is not claimed.

= Can administrators use the API to edit another author's event? =
No. Actual event authorship is mandatory. Tickets also require ownership of their parent event. Native dashboard and other API permissions retain their own policies.

= Are Author or Subscriber roles automatically granted access? =
No. An administrator must grant the required Tickera creation/editing primitives to selected accounts. Publication, private edits, category assignment, images and templates can require additional capabilities.

= Can I sell, delete or check in tickets through this API? =
No. It manages event records and ticket types only. It exposes no checkout, purchasing, payment, refund, attendee, check-in or deletion operations.

= Is this an official Tickera extension? =
No. This independent plugin is developed by Md Abu Hasan and published by QuixDevs.

== Changelog ==

= 1.0.0 =
* Initial eight-operation author-scoped event and standalone ticket-type REST API.
* Native authentication, strict ownership, field validation and verified Tickera metadata/save integration.
* Capability-aware collections, service-layer validation and related-write locks.
* Reproducible lightweight installation ZIP, documentation, examples and draft-only release tooling.

== Upgrade Notice ==

= 1.0.0 =
Initial release. Review tested versions, install standalone Tickera 3.6.0.8 and grant selected-author capabilities before use.
