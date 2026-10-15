# REST API reference

Base URL: `https://example.com/wp-json/quixdevs-tickera/v1`. Substitute your own site, including any WordPress subdirectory. Plain-permalink sites may use the URL returned by `rest_url()` (for example `https://example.com/?rest_route=/quixdevs-tickera/v1/events`). All examples are synthetic; author 15 owns event 123 and ticket 456.

## Common authentication, headers and policy

Every operation requires an authenticated WordPress user. Use a **user-specific Application Password over HTTPS** (`Authorization: Basic base64(username:application_password)`), or an existing WordPress login cookie with `X-WP-Nonce` from `wp_create_nonce('wp_rest')`. Never submit a user ID as authentication. See [WordPress REST authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) and the selected-author setup below. The GitHub project adds deployment/mobile-client guidance.

Send `Accept: application/json`. POST/PATCH also require `Content-Type: application/json` and a nonempty JSON **object**. All production/staging writes require WordPress `is_ssl()`; local/development installations may use HTTP for testing. TLS reverse proxies must configure WordPress to recognize HTTPS through a trusted server configuration.

Ownership is mandatory even for administrators/editors: events must have the caller's actual `post_author`; tickets must belong to the caller **and** link to an editable event owned by the caller. Resource reads require the registered CPT's editing capability, including published/private editing primitives where applicable. Wrong post types, other owners, nonexistent/orphaned resources and excluded statuses return a generic 404. An owned resource lacking edit permission returns 403. No deletion operation exists.

Response bodies contain only the fields documented here. JSON strings are data, not HTML-escaped markup; clients must escape plain text and render allowed rich HTML safely. Headers include `Cache-Control: private, no-store`; configure upstream caches to honor it. Do not cache private responses across identities.

## Event write fields

| Field | JSON type | Required on POST | Rules / default |
|---|---|---|---|
| `title` | string | Yes | 1–200 characters before sanitization; must retain nonblank plain text |
| `description` | string | No | Up to 100000 characters; WordPress allowed post HTML; default empty |
| `status` | string | No | `draft` (default), `pending`, `publish`, `private`; publish/private require `publish_tc_events` |
| `start_at` | string | Yes | Valid site-local `YYYY-MM-DDTHH:MM`, year >=1970; no seconds or offset |
| `end_at` | string | Yes | Same; end >= start; comparisons use the combined stored and supplied values |
| `location` | string | No | Plain text up to 2000 characters; default empty |
| `terms` | string | No | Allowed post HTML up to 100000 characters; default empty |
| `category_ids` | integer array | No | Unique positive existing `event_category` term IDs, max 100; actual taxonomy `assign_terms` capability required; `[]` clears |
| `featured_image_id` | integer | No | Existing owned editable image attachment; 0 clears; default 0 |
| `logo_id` | integer | No | Same attachment authorization; maps to Tickera's logo URL; 0 clears; default 0 |

PATCH fields are all optional but at least one field is required. Only supplied fields change. Start/end stay in the WordPress site timezone; response `timezone` names that timezone. Nonexistent or repeated DST times are rejected. Older dates that cannot be represented safely read as null; send both valid dates to repair them.

## Ticket write fields

| Field | JSON type | Required on POST | Rules / default |
|---|---|---|---|
| `title` | string | Yes | Same text/title rules as events |
| `description` | string | No | Allowed post HTML, max 100000 characters; default empty |
| `status` | string | No | `draft` (default), `pending`, `publish`, `private`; publish/private require `publish_tc_tickets` |
| `price` | string | Yes | Nonnegative decimal, up to 8 integer digits and 2 fractional digits; no sign, exponent, grouping, currency symbol or unnecessary leading zeros. Examples: `"0"`, `"0.1"`, `"50.00"`; returned as two decimal places for API-created data |
| `quantity` | integer or null | No | 0–2147483647; null (default) is unlimited; **0 means zero available**, never unlimited. Total configured quantity, not remaining unsold stock. Sold counts are untouched |
| `sale_start_at` | string or null | No | Site-local minute date; default null; both sale dates must exist or both be null |
| `sale_end_at` | string or null | No | End >= start; PATCH may change one date when combined existing dates remain valid; clear with both null |
| `template_id` | integer | No | Positive published `tc_templates` ID owned by caller; `manage_ticket_templates_cap` required. Omission preserves selection; 0 is not a supported write. An unassigned response has 0 |
| `event_id` | integer | No, POST only | If present, must equal the authorized path event ID. Association is immutable on PATCH; omit this field there |

Every ticket operation returns 409 when Bridge/WooCommerce mode is detected or the relationship schema is customized. Premium Bridge products are unsupported. No currency field, order data, attendee data, sold counters, check-in state or arbitrary metadata is accepted.

## Response-only fields

Both objects add integer `id` and `author_id`, boolean `active` (true exactly when status is publish), string `timezone`, and UTC RFC3339 `created_at` / `updated_at` with `Z`. Event responses also include all event fields. Ticket responses include all ticket fields and immutable integer `event_id`. Template IDs and image IDs are 0 when unassigned. Event logo URLs that cannot resolve to a WordPress attachment return `logo_id: 0`; the underlying URL is preserved until explicitly edited.

Do not send response-only fields back as a write body. `author_id`, `post_author`, `user_id`, `created_by`, `active`, `id`, `meta`, `meta_input` and all unknown fields are rejected, including otherwise innocuous unknown fields. Query overrides such as `author`, `offset`, `meta_query`, `_fields`, `_embed` and `context` are unsupported. Detail/write endpoints have no query parameters.

## List query parameters

Both list endpoints support the same bounded parameters; no other query parameters are accepted.

| Parameter | Type | Default | Accepted values |
|---|---|---|---|
| `page` | integer | 1 | 1–100000; beyond final page yields an empty array |
| `per_page` | integer | 20 | 1–100 |
| `status` | string | all supported statuses | One of draft, pending, publish, private |
| `search` | string | empty | Sanitized plain text, up to 200 characters |
| `orderby` | string | date | date, modified, title, ID |
| `order` | string | DESC | ASC or DESC, uppercase |

Lists return a JSON array and `X-WP-Total` / `X-WP-TotalPages` headers. Counts are the server-filtered owned query count; a subsequent per-item capability/ownership recheck can omit records, so a page can contain fewer than `per_page` accessible items. No unbounded query or client-controlled author filter is used.

## Errors and status codes

WordPress errors have `{ "code": "...", "message": "...", "data": { "status": N } }`. Schema errors may also include `data.params` / `data.details`; use those to attach validation messages to form fields. Do not rely on translated English message strings; inspect code/status.

| Status | Meaning / example codes |
|---|---|
| 200 | Successful read or PATCH |
| 201 | Created; `Location` points at the detail endpoint |
| 400 | `rest_invalid_json`, `rest_missing_callback_param`, `rest_invalid_param`, `quixdevs_invalid_request` (unsupported field, association mismatch, invalid body) |
| 401 | `quixdevs_authentication_required`, or a core authentication error |
| 403 | `quixdevs_forbidden`, `quixdevs_https_required`, attachment/category/template authorization, `rest_cookie_invalid_nonce` |
| 404 | `quixdevs_not_found` or `rest_no_route` |
| 409 | `quixdevs_ticket_mode_unsupported` or `quixdevs_resource_busy` for conflicting/unavailable write locks |
| 422 | `quixdevs_invalid_request` for combined date/business rules |
| 500 | `quixdevs_persistence_failed`; inspect resource before retrying |
| 503 | `quixdevs_tickera_missing` / `quixdevs_tickera_unsupported` |

WordPress post/meta and taxonomy writes are not transactional; a late failure can leave a saved post or some metadata. A 500 response asks you to retrieve/list before retrying. POST has no idempotency key, so blind retries can create duplicates. The addon does not disclose SQL errors, stack traces, or another author's content in its errors.

## 1. List your events

Purpose: list your events.

**GET** `https://example.com/wp-json/quixdevs-tickera/v1/events`

Authentication: the common user-specific Application Password or cookie + REST nonce mechanism above.

Headers: `Accept: application/json`. No request body.

URL parameters: none.

Query parameters: the six list parameters in the list-query table above; all optional.

Request JSON fields: none. Required body fields: none. Optional body fields: none. Read bodies are rejected.

Ownership/capabilities: Only caller-owned editable events; registered `edit_tc_events` capability required.

Successful response: **200 OK**.

Example headers: `X-WP-Total: 1`, `X-WP-TotalPages: 1`.

```json
[
  {
    "id": 123,
    "title": "QuixDevs Conference",
    "description": "<p>Meet our community.</p>",
    "status": "draft",
    "active": false,
    "author_id": 15,
    "timezone": "Asia/Dhaka",
    "created_at": "2026-10-14T16:00:00Z",
    "updated_at": "2026-10-14T16:00:00Z",
    "start_at": "2027-01-14T09:00",
    "end_at": "2027-01-14T18:00",
    "location": "Dhaka",
    "terms": "<p>Admission terms.</p>",
    "category_ids": [],
    "featured_image_id": 0,
    "logo_id": 0
  }
]
```

Error response examples (each is a separate response):

```json
{
  "code": "quixdevs_authentication_required",
  "message": "Authentication is required.",
  "data": {
    "status": 401
  }
}
```

```json
{
  "code": "quixdevs_forbidden",
  "message": "You do not have the required capability.",
  "data": {
    "status": 403
  }
}
```

HTTP status codes: 200, 400, 401, 403, 404, 503. Core authentication/WordPress filters may return their own documented errors.

Side effects: No database writes.

Notes/limitations: bounded pagination, no author override. Public event pages retain native Tickera behavior; this endpoint remains authenticated and owner-scoped.

## 2. Create an event

Purpose: create an event.

**POST** `https://example.com/wp-json/quixdevs-tickera/v1/events`

Authentication: the common user-specific Application Password or cookie + REST nonce mechanism above.

Headers: `Accept: application/json`; `Content-Type: application/json` is required.

URL parameters: none.

Query parameters: none; unsupported parameters are rejected.

Request JSON fields: `title`, `description`, `status`, `start_at`, `end_at`, `location`, `terms`, `category_ids`, `featured_image_id`, `logo_id`. Field types, accepted values and validation are exactly those in the event write-field table.

Required: `title`, `start_at`, `end_at`. Optional: `description`, `status`, `location`, `terms`, `category_ids`, `featured_image_id`, `logo_id`.

Example request:

```json
{
  "title": "QuixDevs Conference",
  "description": "<p>Meet our community.</p>",
  "start_at": "2027-01-14T09:00",
  "end_at": "2027-01-14T18:00",
  "location": "Dhaka",
  "terms": "<p>Admission terms.</p>"
}
```

Ownership/capabilities: Requires `create_tc_events`; author is forced to the caller. Grant editing capabilities for subsequent access. Publish/private requires the publish capability.

Successful response: **201 Created**; `Location: https://example.com/wp-json/quixdevs-tickera/v1/events/123`.

```json
{
  "id": 123,
  "title": "QuixDevs Conference",
  "description": "<p>Meet our community.</p>",
  "status": "draft",
  "active": false,
  "author_id": 15,
  "timezone": "Asia/Dhaka",
  "created_at": "2026-10-14T16:00:00Z",
  "updated_at": "2026-10-14T16:00:00Z",
  "start_at": "2027-01-14T09:00",
  "end_at": "2027-01-14T18:00",
  "location": "Dhaka",
  "terms": "<p>Admission terms.</p>",
  "category_ids": [],
  "featured_image_id": 0,
  "logo_id": 0
}
```

Error response examples (each is a separate response):

```json
{
  "code": "quixdevs_authentication_required",
  "message": "Authentication is required.",
  "data": {
    "status": 401
  }
}
```

```json
{
  "code": "quixdevs_forbidden",
  "message": "You do not have the required capability.",
  "data": {
    "status": 403
  }
}
```

```json
{
  "code": "quixdevs_invalid_request",
  "message": "A valid end date must be on or after the start date.",
  "data": {
    "status": 422
  }
}
```

HTTP status codes: 201, 400, 401, 403, 404, 422, 500, 503. Core authentication/WordPress filters may return their own documented errors.

Side effects: Creates tc_events; the real Tickera save hook links its presentation page to itself and maintains one check-in API key. No key is returned.

Notes/limitations: Strict owner policy also applies to administrators. Public event pages retain native Tickera behavior; this endpoint remains authenticated and owner-scoped.

## 3. Retrieve your event

Purpose: retrieve your event.

**GET** `https://example.com/wp-json/quixdevs-tickera/v1/events/{id}`

Authentication: the common user-specific Application Password or cookie + REST nonce mechanism above.

Headers: `Accept: application/json`. No request body.

URL parameters: `id` is a positive integer event ID. Wrong type/ownership is hidden with 404.

Query parameters: none; unsupported parameters are rejected.

Request JSON fields: none. Required body fields: none. Optional body fields: none. Read bodies are rejected.

Ownership/capabilities: Own the actual tc_events record and have its registered edit capability.

Successful response: **200 OK**.

```json
{
  "id": 123,
  "title": "QuixDevs Conference",
  "description": "<p>Meet our community.</p>",
  "status": "draft",
  "active": false,
  "author_id": 15,
  "timezone": "Asia/Dhaka",
  "created_at": "2026-10-14T16:00:00Z",
  "updated_at": "2026-10-14T16:00:00Z",
  "start_at": "2027-01-14T09:00",
  "end_at": "2027-01-14T18:00",
  "location": "Dhaka",
  "terms": "<p>Admission terms.</p>",
  "category_ids": [],
  "featured_image_id": 0,
  "logo_id": 0
}
```

Error response examples (each is a separate response):

```json
{
  "code": "quixdevs_authentication_required",
  "message": "Authentication is required.",
  "data": {
    "status": 401
  }
}
```

```json
{
  "code": "quixdevs_not_found",
  "message": "Resource not found.",
  "data": {
    "status": 404
  }
}
```

HTTP status codes: 200, 400, 401, 403, 404, 503. Core authentication/WordPress filters may return their own documented errors.

Side effects: No database writes.

Notes/limitations: Strict owner policy also applies to administrators. Public event pages retain native Tickera behavior; this endpoint remains authenticated and owner-scoped.

## 4. Partially edit your event

Purpose: partially edit your event.

**PATCH** `https://example.com/wp-json/quixdevs-tickera/v1/events/{id}`

Authentication: the common user-specific Application Password or cookie + REST nonce mechanism above.

Headers: `Accept: application/json`; `Content-Type: application/json` is required.

URL parameters: `id` is a positive integer event ID. Wrong type/ownership is hidden with 404.

Query parameters: none; unsupported parameters are rejected.

Request JSON fields: `title`, `description`, `status`, `start_at`, `end_at`, `location`, `terms`, `category_ids`, `featured_image_id`, `logo_id`. Field types, accepted values and validation are exactly those in the event write-field table.

Required: at least one listed writable field; no individual field is required on PATCH. Optional: `title`, `description`, `status`, `start_at`, `end_at`, `location`, `terms`, `category_ids`, `featured_image_id`, `logo_id`.

Example request:

```json
{
  "location": "Updated venue"
}
```

Ownership/capabilities: Own and be able to edit the event; supplied publish/private status additionally requires publication permission. Ownership is rechecked before saving.

Successful response: **200 OK**.

```json
{
  "id": 123,
  "title": "QuixDevs Conference",
  "description": "<p>Meet our community.</p>",
  "status": "draft",
  "active": false,
  "author_id": 15,
  "timezone": "Asia/Dhaka",
  "created_at": "2026-10-14T16:00:00Z",
  "updated_at": "2026-10-14T16:00:00Z",
  "start_at": "2027-01-14T09:00",
  "end_at": "2027-01-14T18:00",
  "location": "Updated venue",
  "terms": "<p>Admission terms.</p>",
  "category_ids": [],
  "featured_image_id": 0,
  "logo_id": 0
}
```

Error response examples (each is a separate response):

```json
{
  "code": "quixdevs_authentication_required",
  "message": "Authentication is required.",
  "data": {
    "status": 401
  }
}
```

```json
{
  "code": "quixdevs_not_found",
  "message": "Resource not found.",
  "data": {
    "status": 404
  }
}
```

```json
{
  "code": "quixdevs_invalid_request",
  "message": "A valid end date must be on or after the start date.",
  "data": {
    "status": 422
  }
}
```

HTTP status codes: 200, 400, 401, 403, 404, 409, 422, 500, 503. Core authentication/WordPress filters may return their own documented errors.

Side effects: Only supplied post/meta fields change. Tickera updates its key title/status, preserving the existing key; unrelated display metadata remains intact.

Notes/limitations: Strict owner policy also applies to administrators. Public event pages retain native Tickera behavior; this endpoint remains authenticated and owner-scoped.

## 5. List tickets for your event

Purpose: list tickets for your event.

**GET** `https://example.com/wp-json/quixdevs-tickera/v1/events/{id}/tickets`

Authentication: the common user-specific Application Password or cookie + REST nonce mechanism above.

Headers: `Accept: application/json`. No request body.

URL parameters: `id` is a positive integer event ID. Wrong type/ownership is hidden with 404.

Query parameters: the six list parameters in the list-query table above; all optional.

Request JSON fields: none. Required body fields: none. Optional body fields: none. Read bodies are rejected.

Ownership/capabilities: Own and be able to edit parent event; `edit_tc_tickets` required. Every returned ticket must belong to caller and have the same owned parent.

Successful response: **200 OK**.

Example headers: `X-WP-Total: 1`, `X-WP-TotalPages: 1`.

```json
[
  {
    "id": 456,
    "title": "VIP",
    "description": "<p>VIP admission.</p>",
    "status": "draft",
    "active": false,
    "author_id": 15,
    "timezone": "Asia/Dhaka",
    "created_at": "2026-10-14T16:01:00Z",
    "updated_at": "2026-10-14T16:01:00Z",
    "event_id": 123,
    "price": "50.00",
    "quantity": 100,
    "sale_start_at": null,
    "sale_end_at": null,
    "template_id": 0
  }
]
```

Error response examples (each is a separate response):

```json
{
  "code": "quixdevs_authentication_required",
  "message": "Authentication is required.",
  "data": {
    "status": 401
  }
}
```

```json
{
  "code": "quixdevs_not_found",
  "message": "Resource not found.",
  "data": {
    "status": 404
  }
}
```

```json
{
  "code": "quixdevs_ticket_mode_unsupported",
  "message": "Standalone ticket operations are unavailable in Bridge/WooCommerce mode or with a customized relationship field. Event operations remain available.",
  "data": {
    "status": 409
  }
}
```

HTTP status codes: 200, 400, 401, 403, 404, 409, 503. Core authentication/WordPress filters may return their own documented errors.

Side effects: No database writes.

Notes/limitations: bounded pagination, no author override. Standalone ticket types only; Bridge products and ticket reassignment are unsupported.

## 6. Create a ticket type

Purpose: create a ticket type.

**POST** `https://example.com/wp-json/quixdevs-tickera/v1/events/{id}/tickets`

Authentication: the common user-specific Application Password or cookie + REST nonce mechanism above.

Headers: `Accept: application/json`; `Content-Type: application/json` is required.

URL parameters: `id` is a positive integer event ID. Wrong type/ownership is hidden with 404.

Query parameters: none; unsupported parameters are rejected.

Request JSON fields: `title`, `description`, `status`, `price`, `quantity`, `sale_start_at`, `sale_end_at`, `template_id`, `event_id`. Field types, accepted values and validation are exactly those in the ticket write-field table.

Required: `title`, `price`. Optional: `description`, `status`, `quantity`, `sale_start_at`, `sale_end_at`, `template_id`, `event_id`.

Example request:

```json
{
  "title": "VIP",
  "description": "<p>VIP admission.</p>",
  "price": "50.00",
  "quantity": 100
}
```

Ownership/capabilities: Own editable parent event plus `create_tc_tickets`. Author and event link are server-enforced. Optional event_id must match the path.

Successful response: **201 Created**; `Location: https://example.com/wp-json/quixdevs-tickera/v1/tickets/456`.

```json
{
  "id": 456,
  "title": "VIP",
  "description": "<p>VIP admission.</p>",
  "status": "draft",
  "active": false,
  "author_id": 15,
  "timezone": "Asia/Dhaka",
  "created_at": "2026-10-14T16:01:00Z",
  "updated_at": "2026-10-14T16:01:00Z",
  "event_id": 123,
  "price": "50.00",
  "quantity": 100,
  "sale_start_at": null,
  "sale_end_at": null,
  "template_id": 0
}
```

Error response examples (each is a separate response):

```json
{
  "code": "quixdevs_authentication_required",
  "message": "Authentication is required.",
  "data": {
    "status": 401
  }
}
```

```json
{
  "code": "quixdevs_not_found",
  "message": "Resource not found.",
  "data": {
    "status": 404
  }
}
```

```json
{
  "code": "quixdevs_invalid_request",
  "message": "A valid end date must be on or after the start date.",
  "data": {
    "status": 422
  }
}
```

```json
{
  "code": "quixdevs_ticket_mode_unsupported",
  "message": "Standalone ticket operations are unavailable in Bridge/WooCommerce mode or with a customized relationship field. Event operations remain available.",
  "data": {
    "status": 409
  }
}
```

HTTP status codes: 201, 400, 401, 403, 404, 409, 422, 500, 503. Core authentication/WordPress filters may return their own documented errors.

Side effects: Creates standalone tc_tickets, sets verified event_name relationship, price, quantity/sale defaults and supplied configuration. No sales/attendee records are created.

Notes/limitations: Strict owner policy also applies to administrators. Standalone ticket types only; Bridge products and ticket reassignment are unsupported.

## 7. Retrieve your ticket type

Purpose: retrieve your ticket type.

**GET** `https://example.com/wp-json/quixdevs-tickera/v1/tickets/{id}`

Authentication: the common user-specific Application Password or cookie + REST nonce mechanism above.

Headers: `Accept: application/json`. No request body.

URL parameters: `id` is a positive integer ticket type ID. Wrong type/ownership is hidden with 404.

Query parameters: none; unsupported parameters are rejected.

Request JSON fields: none. Required body fields: none. Optional body fields: none. Read bodies are rejected.

Ownership/capabilities: Own and be able to edit both the tc_tickets record and its actual associated tc_events record.

Successful response: **200 OK**.

```json
{
  "id": 456,
  "title": "VIP",
  "description": "<p>VIP admission.</p>",
  "status": "draft",
  "active": false,
  "author_id": 15,
  "timezone": "Asia/Dhaka",
  "created_at": "2026-10-14T16:01:00Z",
  "updated_at": "2026-10-14T16:01:00Z",
  "event_id": 123,
  "price": "50.00",
  "quantity": 100,
  "sale_start_at": null,
  "sale_end_at": null,
  "template_id": 0
}
```

Error response examples (each is a separate response):

```json
{
  "code": "quixdevs_authentication_required",
  "message": "Authentication is required.",
  "data": {
    "status": 401
  }
}
```

```json
{
  "code": "quixdevs_not_found",
  "message": "Resource not found.",
  "data": {
    "status": 404
  }
}
```

```json
{
  "code": "quixdevs_ticket_mode_unsupported",
  "message": "Standalone ticket operations are unavailable in Bridge/WooCommerce mode or with a customized relationship field. Event operations remain available.",
  "data": {
    "status": 409
  }
}
```

HTTP status codes: 200, 400, 401, 403, 404, 409, 503. Core authentication/WordPress filters may return their own documented errors.

Side effects: No database writes.

Notes/limitations: Strict owner policy also applies to administrators. Standalone ticket types only; Bridge products and ticket reassignment are unsupported.

## 8. Partially edit your ticket type

Purpose: partially edit your ticket type.

**PATCH** `https://example.com/wp-json/quixdevs-tickera/v1/tickets/{id}`

Authentication: the common user-specific Application Password or cookie + REST nonce mechanism above.

Headers: `Accept: application/json`; `Content-Type: application/json` is required.

URL parameters: `id` is a positive integer ticket type ID. Wrong type/ownership is hidden with 404.

Query parameters: none; unsupported parameters are rejected.

Request JSON fields: `title`, `description`, `status`, `price`, `quantity`, `sale_start_at`, `sale_end_at`, `template_id`. Field types, accepted values and validation are exactly those in the ticket write-field table.

Required: at least one listed writable field; no individual field is required on PATCH. Optional: `title`, `description`, `status`, `price`, `quantity`, `sale_start_at`, `sale_end_at`, `template_id`.

Example request:

```json
{
  "price": "60.00",
  "quantity": 120
}
```

Ownership/capabilities: Own and be able to edit ticket and parent event; publication permission for publish/private. Association and author cannot change.

Successful response: **200 OK**.

```json
{
  "id": 456,
  "title": "VIP",
  "description": "<p>VIP admission.</p>",
  "status": "draft",
  "active": false,
  "author_id": 15,
  "timezone": "Asia/Dhaka",
  "created_at": "2026-10-14T16:01:00Z",
  "updated_at": "2026-10-14T16:01:00Z",
  "event_id": 123,
  "price": "60.00",
  "quantity": 120,
  "sale_start_at": null,
  "sale_end_at": null,
  "template_id": 0
}
```

Error response examples (each is a separate response):

```json
{
  "code": "quixdevs_authentication_required",
  "message": "Authentication is required.",
  "data": {
    "status": 401
  }
}
```

```json
{
  "code": "quixdevs_not_found",
  "message": "Resource not found.",
  "data": {
    "status": 404
  }
}
```

```json
{
  "code": "quixdevs_invalid_request",
  "message": "A valid end date must be on or after the start date.",
  "data": {
    "status": 422
  }
}
```

```json
{
  "code": "quixdevs_ticket_mode_unsupported",
  "message": "Standalone ticket operations are unavailable in Bridge/WooCommerce mode or with a customized relationship field. Event operations remain available.",
  "data": {
    "status": 409
  }
}
```

HTTP status codes: 200, 400, 401, 403, 404, 409, 422, 500, 503. Core authentication/WordPress filters may return their own documented errors.

Side effects: Only supplied fields change. Sold counts, orders, generated tickets and attendee/check-in data remain untouched.

Notes/limitations: Strict owner policy also applies to administrators. Standalone ticket types only; Bridge products and ticket reassignment are unsupported.


## Concurrent writes

Related writes through this addon use nonblocking MySQL/MariaDB connection locks. A conflict or unavailable lock returns 409 (`quixdevs_resource_busy`); retrieve current state before retrying. Native admin screens and other plugins do not participate. External writes can still race; there is no transaction, ETag or idempotency key. A late hook failure can leave partial changes; inspect before retrying POST.

Collections exclude owned published/private records when their status editing capability is absent. Explicitly requesting an uneditable status returns 403. Totals describe the authorized query at that instant; concurrent external changes can affect subsequent pages.

## Selected-author setup

No capabilities are granted automatically. An administrator can provision a selected account using WP-CLI:

```sh
for cap in create_tc_events edit_tc_events create_tc_tickets edit_tc_tickets; do
  wp user add-cap selected_author "$cap"
done
```

For publication grant publish_tc_events/edit_published_tc_events and publish_tc_tickets/edit_published_tc_tickets as needed. Owned private edits additionally need edit_private_tc_events/edit_private_tc_tickets. Use standard WordPress Application Passwords over HTTPS or a login cookie plus wp_rest nonce. Do not grant edit-others privileges for this API. Category, attachment and template assignment require the additional registered capabilities described above.
