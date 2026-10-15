# Testing and release verification

Tests use **real installed WordPress**, actual users, the real REST server/registered routes and bundled Tickera hooks/classes, under PHPUnit 9. They do not mock ownership or persistence. Each test starts a database transaction and rolls it back, and flushes WordPress object caches. Use only an isolated MySQL/MariaDB database named exactly `quixdevs_tickera_test`. The bootstrap refuses other database names. Tickera's filesystem/session behavior is not transactional, so isolate the whole installation as well. No tests may target production.

## Reproduce locally

Install PHP, Composer, WP-CLI and a dedicated database. Work from the addon repository root. Replace disposable local database credentials below; never use production credentials.

```sh
composer install
export QUIXDEVS_WP_PATH=/tmp/quixdevs-wp
export WP_ENVIRONMENT_TYPE=local
mkdir -p "$QUIXDEVS_WP_PATH"
wp core download --version=7.1.3 --path="$QUIXDEVS_WP_PATH"
wp config create --dbname=quixdevs_tickera_test --dbuser=local_test_user --dbpass=local_test_password --dbhost=127.0.0.1 --path="$QUIXDEVS_WP_PATH"
wp db create --path="$QUIXDEVS_WP_PATH"
wp core install --url=http://localhost --title='QuixDevs QA' --admin_user=qa_admin --admin_password=disposable-test-only --admin_email=qa@example.test --skip-email --path="$QUIXDEVS_WP_PATH"
wp plugin install tickera-event-ticketing-system --version=3.6.0.8 --activate --path="$QUIXDEVS_WP_PATH"
ln -s "$PWD" "$QUIXDEVS_WP_PATH/wp-content/plugins/quixdevs-tickera-rest-api"
wp plugin activate quixdevs-tickera-rest-api --path="$QUIXDEVS_WP_PATH"
composer test
composer lint
find includes -name '*.php' -exec php -l {} \;
php -l quixdevs-tickera-rest-api.php
python3 tools/build-release.py
mkdir -p /tmp/quixdevs-package
unzip -q dist/quixdevs-tickera-rest-api.zip -d /tmp/quixdevs-package
wp plugin install plugin-check --activate --path="$QUIXDEVS_WP_PATH"
wp plugin check /tmp/quixdevs-package/quixdevs-tickera-rest-api --path="$QUIXDEVS_WP_PATH"
```

To use a downloaded Tickera fixture, copy it into the isolated installation instead of wp plugin install; keep original source read-only. The runtime requires exactly 3.6.0.8. The customer ZIP requires no Composer packages.

## Coverage

- Both A/B create their own events/tickets, can edit their own, and cannot read/edit/create under the other's resources.
- Private events/tickets, pagination, wrong CPTs, nonexistent/mixed-ownership parents, immutable associations and admin/editor ownership restrictions.
- Unauthenticated/underprivileged callers, all forbidden ownership/security/meta fields, author query overrides, publication restrictions and HTTPS gate.
- Actual Tickera presentation metadata, single event key across edits, unchanged private key, relationship recognition, finite zero/unlimited quantity and exact decimal storage.
- Required/invalid fields, malformed JSON, bad prices/quantities/sale ranges, partial updates, dates/timezone/DST, category/image/template permissions, safe persistence errors and no delete route.
- Missing-runtime/unsupported-version checks and filter-simulated Bridge state. Premium Bridge is not installed; simulation tests do not claim premium integration compatibility.

HTTP testing separately exercises per-user Application Password authentication, cookie authentication with valid/missing/invalid REST nonces, all eight endpoints and private cache headers. It uses disposable credentials outside the repository. Local HTTP testing does not claim TLS/proxy interoperability; production HTTPS is separately enforced/tested through server HTTPS state.

## WPCS and minimum PHP

Runtime files are checked against the complete WordPress ruleset. The filename sniff is deliberately excluded for the requested PSR-4 class layout; all other checks remain active. `manage_ticket_templates_cap` is declared to PHPCS as a verified Tickera custom capability. Composer development dependencies resolve against PHP 8.1.0. Local execution uses PHP 8.2.4 and 8.4.13. CI defines PHP 8.1/8.2/8.3/8.4 and WordPress 6.8/7.1.3; all eight jobs passed in the public Quality run at cf3c6bf; see final-release-audit.md for current evidence. Run composer analyse, composer audit --locked and composer test:release as well.

Upstream Tickera 3.6.0.8 uses dynamic properties and can emit PHP 8.2+ deprecation notices. The test bootstrap masks upstream deprecations; the addon runtime never changes error_reporting. Other errors/warnings still fail PHPUnit. No warning suppression is applied to WPCS or production Plugin Check.

## Manual checklist

1. Install the production ZIP on a separate fresh test site with Tickera active and confirm activation.
2. Confirm missing Tickera blocks activation through WordPress dependencies and forced/missing-runtime loading returns 503 without fatal errors.
3. Provision selected author capabilities and run the documented workflow/Postman requests.
4. In native Tickera editors, inspect event title, dates/location and ticket event selection, price/quantity; repeat after REST PATCH.
5. Verify the minimal information screen and published/draft behavior.
6. Review current WordPress.org checks and minimum-version remote CI before publishing.

Actual executed results, versions, screenshots and unresolved limits are recorded in [implementation-report.md](implementation-report.md) and [publication-readiness.md](publication-readiness.md).

## HTTP smoke script

`tests/http-smoke.py` restricts execution to localhost. It creates disposable event/ticket records and tests all eight operations plus real authentication. Provision a selected test author and Application Password in the isolated installation. Supply a protected JSON file outside the repository with `username`, `application_password`, `user_id`; supply a second protected file with a valid test session's logged-in cookie `name`, `value`, and matching `wp_rest` `nonce`. Never use production credentials. These files are deliberately not packaged.

```sh
export QUIXDEVS_HTTP_BASE=http://127.0.0.1:18867/wp-json/quixdevs-tickera/v1
export QUIXDEVS_HTTP_CREDENTIALS=/private/tmp/test-author-credentials.json
export QUIXDEVS_HTTP_COOKIE=/private/tmp/test-cookie-and-nonce.json
python3 tests/http-smoke.py
```

Run this against a local WordPress server with proper REST permalinks and WordPress's `local` environment. The test retains records because the API intentionally provides no deletion; dispose of the dedicated test installation/database when finished. It does not print credentials.

## Release hardening regressions

Additional actual-DB tests cover capability-filtered totals, direct service mass-assignment rejection, all eight route methods and second-connection write contention/lock release. Python stdlib tests exercise deterministic ZIP contents and the draft-release API state machine with simulated responses; they do not claim a real GitHub release has been created.
