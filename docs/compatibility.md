# Compatibility

The header minimums are WordPress **6.8** and PHP **8.1**. Runtime syntax and dependencies target PHP 8.1; Composer resolves development tools with platform.php 8.1.0. Actual locally executed environments are recorded in [release-readiness-report.md](release-readiness-report.md) and verification-results.json. PHP 8.1 and 8.3 passed remote GitHub CI, alongside PHP 8.2/8.4, for WordPress 6.8/7.1.3 with MySQL 8.0 at commit cf3c6bf. This is remote evidence, not local execution; see the final release audit.

The supported standalone Tickera release is **3.6.0.8**, dependency slug `tickera-event-ticketing-system`, verified against its actual source and WordPress.org listing. Other versions return 503 until the persistence model is audited and tested. WordPress custom post types are tc_events and tc_tickets. Native event_category, tc_templates and Tickera's real save hooks are used; see [integration findings](tickera-integration.md).

MySQL/MariaDB connection advisory locks are required for writes. Local tests use MariaDB 10.4.28 with a second real connection to verify lock contention. CI used MySQL 8.0 in all eight successful remote matrix jobs. Locks fail closed with 409 when unavailable. No tables or migrations are added.

Standalone usage requires no WooCommerce. Premium Bridge is not installed/tested; known Bridge filters/activation state are simulated only to verify ticket-operation refusal. Multisite/network plugin detection is implemented but a multisite deployment has not been executed. Native mobile storage, external TLS proxies, production hosting and arbitrary third-party hook compatibility need deployment-specific verification.

Upstream Tickera uses PHP dynamic properties and can emit deprecation notices on PHP 8.2+. The test bootstrap excludes upstream E_DEPRECATED; addon runtime does not change error_reporting. Displaying PHP errors publicly should be disabled through ordinary production server configuration. No PHPStan baseline or broad WPCS/security exclusions are used.

The eight-operation contract is preserved from the original source. Release hardening adds service validation, primitive normalization, editable-status totals and nonblocking write serialization. These introduce a documented 409 for conflicting/unavailable locks and 403 for explicitly requesting a status the caller cannot edit.
