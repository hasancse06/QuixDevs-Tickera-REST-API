# Authentication and author permissions

WordPress verifies identity; this addon uses `get_current_user_id()` and does not create passwords, JWTs, shared API keys or alternative login routes.

## Trusted backend / Postman

Use HTTPS. In WordPress **Users → Profile → Application Passwords**, create a separate password for the selected event author. Copy it once into the backend's secret manager or Postman local secret variable. Send HTTP Basic authentication with the **WordPress username**, not the application name. Revoke the password when the client is retired; issue different passwords per device/integration. The credential inherits that user's WordPress permissions, not just this addon. Avoid granting unrelated capabilities to API-only accounts.

See [WordPress authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) and [Application Password management](https://developer.wordpress.org/advanced-administration/security/application-passwords/).

## WordPress web application

Use the user's normal WordPress login session. Have trusted server code provide `rest_url('quixdevs-tickera/v1')` and `wp_create_nonce('wp_rest')` to the authenticated page via `wp_add_inline_script()` / `wp_localize_script()`. The browser sends credentials and `X-WP-Nonce`. Nonce refresh/re-login is needed after expiry. A REST nonce alone does not authenticate a logged-out user. Missing nonce means the request is treated as unauthenticated; invalid nonce returns 403.

For separate origins, configure a reviewed origin allowlist/session architecture at the host. This addon introduces no permissive CORS configuration. Do not put shared administrator credentials in browser bundles.

## Ionic / Flutter / native mobile

A WordPress-hosted web application can use cookie + nonce as above. A native app needs each user's own authentication context: provision a per-user Application Password securely and ask that user to enter it, then use OS Keychain/Keystore through a maintained secure-storage implementation. Never distribute an administrator Application Password, save credentials to localStorage, log them, commit them, include them in query strings, or embed them in the binary. Use TLS and clear the in-memory credential on logout. Revoke lost-device credentials. A backend can securely broker authenticated requests, but must preserve per-user identities; a shared administrator credential defeats this API's intended ownership model. The addon does not implement mobile login/provisioning or a custom token system.

## Selected-user capability provisioning

No capabilities are granted automatically. Tickera's existing administrator/role behavior is unchanged. An administrator may grant primitives to **specific trusted accounts**, rather than changing Subscriber/Author roles globally. Example WP-CLI commands (substitute the selected user's login):

```sh
for cap in create_tc_events edit_tc_events create_tc_tickets edit_tc_tickets; do
  wp user add-cap selected_author "$cap"
done
```

This allows draft creation and editing owned drafts. To let the account publish and later edit published records:

```sh
for cap in publish_tc_events edit_published_tc_events publish_tc_tickets edit_published_tc_tickets; do
  wp user add-cap selected_author "$cap"
done
```

For owned private records, grant `edit_private_tc_events` / `edit_private_tc_tickets` as needed; creating private status also requires the corresponding publish primitive. These are verified in Tickera 3.6.0.8. The policy uses the **registered CPT capability object**, so a deliberate server-side capability customization is honored. WordPress's `edit_tc_event` / `edit_tc_ticket` meta capabilities are checked with a post ID and map to the appropriate primitives; do not grant those meta capabilities as a substitute for primitives.

Ticket creation also requires editing its parent event. Category assignment checks the registered taxonomy's `assign_terms` capability; in this bundle it resolves to WordPress `edit_posts` because Tickera passes nonstandard taxonomy capability keys. Carefully assess other effects before granting `edit_posts`. Prefer a reviewed site-level taxonomy-capability customization for API-only users; the addon neither changes the taxonomy nor creates terms.

Image assignments require the caller to own and edit the attachment; WordPress attachment editing commonly maps to `edit_posts`. Template assignments require a published template owned by the caller and `manage_ticket_templates_cap`. No template/upload endpoints are included.

Dashboard visibility (`manage_events_cap`, `manage_ticket_types_cap`) is separate from author API access. Do not grant broad `manage_options` or edit-others capabilities merely to enable this API. Administrators still cannot use these endpoints to manage another author.

## HTTPS and reverse proxies

Production and staging writes require `is_ssl()` to be true. A TLS-terminating proxy must set the HTTPS server state in a **trusted** web-server/WordPress configuration; never trust arbitrary client forwarding headers. This addon adds no transport bypass filter. `WP_ENVIRONMENT_TYPE=local` or `development` permits local HTTP testing; do not use those values on production.
