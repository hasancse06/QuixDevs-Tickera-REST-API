# Tickera source investigation

This addon is independent of Tickera. Research target: the unmodified downloaded `tickera-event-ticketing-system` directory, header/runtime version **3.6.0.8**. The runtime deliberately fails closed for other versions. No upstream PHP is copied into this addon. Paths below are relative to that downloaded plugin.

## Storage and capabilities

| Object | Registration / editor | Findings |
|---|---|---|
| Events | `tickera.php:4883–4929`, `includes/classes/class.events.php` | `tc_events`; title/content/thumbnail; `create_tc_events`, `edit_tc_events`, meta capability `edit_tc_event`, `publish_tc_events`, `edit_published_tc_events`, `edit_private_tc_events`. `map_meta_cap=true`. Use the registered CPT capability object so server filters are honored. |
| Tickets | `tickera.php:4963–5004`, `includes/classes/class.tickets.php` | `tc_tickets`; title/content; `create_tc_tickets`, `edit_tc_tickets`, meta capability `edit_tc_ticket`, `publish_tc_tickets`, published/private editing capabilities; `map_meta_cap=true`. |
| Categories | `tickera.php:4930–4962` | `event_category`. Tickera supplies nonstandard capability keys (`assign_categories`, etc.). WordPress's actual registered `assign_terms` capability defaults to `edit_posts`; use `get_taxonomy()->cap->assign_terms`, never assume `manage_options`. No term creation endpoint. |
| Templates | `tickera.php:5137`, `general-functions.php:4571–4630` | `tc_templates`; editor lists published templates. Addon further requires current-user ownership and `manage_ticket_templates_cap`. |
| Ownership | Native post records and `tickera.php` roles around 2020 / 2100 | Native `post_author`; Tickera grants capabilities to its roles/admin. This addon does not grant or change any role. Native broader editor/admin permissions do not bypass addon ownership. |

## Verified metadata mapping

| REST | Tickera storage | Conversion |
|---|---|---|
| Event `start_at` | `event_date_time` | Site-local `YYYY-MM-DD HH:MM` |
| Event `end_at` | `event_end_date_time` | Same |
| `location` | `event_location` | Sanitized plain text (focused addon subset) |
| `terms` | `event_terms` | `wp_kses_post` HTML |
| `logo_id` | `event_logo_file_url` | Authorized attachment ID converted to WordPress attachment URL; no arbitrary external URL input |
| `featured_image_id` | `_thumbnail_id` | Existing owned editable image attachment; 0 clears |
| Ticket `event_id` | `event_name` | Scalar event ID; server-enforced route association, immutable on PATCH |
| `price` | `price_per_ticket` | Decimal string normalized to two places; no float math in addon |
| `quantity` | `quantity_available` | Null becomes empty string (unlimited); 0 remains zero; otherwise nonnegative integer |
| `sale_start_at` | `_ticket_availability_from_date` | Site-local minute precision |
| `sale_end_at` | `_ticket_availability_to_date` | Same |
| Sale mode (derived) | `_ticket_availability` | `range` when both dates supplied; `open_ended` when both null |
| `template_id` | `ticket_template` | Published owned `tc_templates` ID |
| `status` / read-only `active` | Native `post_status` | Active means `publish`, verified in `internal-hooks.php:805–820, 888–894`; no independent active metadata |

`class.tickets.php` declares price and quantity fields and sales mode. `class.ticket.php:194–225` and `general-functions.php:976–990` confirm empty quantity is unlimited and zero is finite. Sold quantities are derived from ticket instances/orders and are never updated by the addon. Tickera itself uses floating-point math in sales code; this addon does not alter that code or promise financial precision for Tickera checkout. Its `internal-hooks.php` rounds totals/fees to two decimals (337, 364, 476–480). v1 intentionally accepts at most two price decimals, 0 through 99999999.99.

The bundled datepicker (`js/jquery.simple-dtpicker.js:14,1387`) uses `YYYY-MM-DD hh:mm` in local wall time. Addon input/output uses `YYYY-MM-DDTHH:MM`, explicitly interpreted in `wp_timezone()`. Dates with offsets/seconds are rejected on writes; nonexistent and repeated DST wall times are rejected. Existing dates that cannot be represented safely return null rather than silently changing their interpretation. On a repair PATCH, supply both valid dates. Site timezone changes are a WordPress administrative concern; the addon does not migrate stored wall times.

## Save lifecycle and event pages

`includes/addons/better-events/index.php:556–627` hooks `save_post`. It sets `event_presentation_page` to the event's own ID and calls the existing static `TC_Better_Events::create_event_api_key()` (629–697). The helper searches the serialized event selection, creates one `tc_api_keys` record if absent, and updates its name/status on later saves. The actual event CPT is its presentation page; no extra WordPress `page` is created by this editor. Tickera's installation wizard may separately create global cart/checkout pages, outside this API's scope.

`better-ticket-types/index.php:233–261` hooks `save_post`, parses admin `*_post_meta` form fields, applies `tickera_ticket_type_metas` and writes metadata. The event handler similarly applies `tickera_events_metas`. There is no safe typed standalone author API in these handlers: they consume global `$_POST`; the legacy `TC_Events::add_new_event()` forces publish and also consumes form globals. Calling either legacy method directly would defeat partial updates and draft authorization.

The addon uses `wp_insert_post()` / `wp_update_post()` with explicit `meta_input` **before** save hooks fire, allowing observers to see correct dates and relationships. It temporarily replaces `$_POST` with an empty array in a `try/finally` block, restores it afterward, and retains real Tickera save hooks and their metadata filters. This prevents accidental admin-field mass assignment and checkbox clearing during PATCH. It does not fake an admin nonce, call hooks twice, or call key creation separately. Tests confirm one key after creation and repeated edits, updated key title, unchanged key value, preserved display metadata, and recognition by `TC_Event::get_event_ticket_types()` / `TC_Ticket`.

`class.event.php:57–105` queries ticket postmeta `event_name`; no additional event-ticket index is required. Its deletion path cleans up related records, but v1 has no delete routes. The addon rejects a customized relationship-key filter to avoid creating data incompatible with native queries. Server-side extensions remain trusted code; filters that replace the verified persistence model require a separately tested adapter.

## Bridge and boundaries

Detect Bridge through `tickera_apply_filters('tickera_bridge_for_woocommerce_is_active', false)`, `tickera_is_woo`, known class `TC_WooCommerce_Bridge`, and known plugin/network activation path. The wrapper in `general-functions.php:64–98` also executes deprecated hook variants. Ticket requests return 409 when detected; event requests continue. Premium Bridge itself is not bundled and has not been integration-tested. No WooCommerce dependency or product writes are introduced.

Public native Tickera events can still appear on public event pages and Tickera's existing `wp/v2` routes. The strict owner policy applies to the eight routes under this addon's namespace; it does not change unrelated WordPress/Tickera APIs.

## External references

The dependency slug matches [Tickera's WordPress.org listing](https://wordpress.org/plugins/tickera-event-ticketing-system/). The header follows [WordPress plugin dependency requirements](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/). Native authentication follows [the WordPress REST authentication handbook](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/).
