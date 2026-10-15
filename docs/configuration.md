# Configuration

The addon has no shared key, global author override, options page or automatic role grants. Tickera → QuixDevs REST API (Tools fallback during onboarding) shows dependency status, API base URL and the bundled docs/rest-api.md guide.

Set WordPress Settings → General → Timezone to the event site's timezone. API event and sale dates are site-local minute strings without offsets; response timestamps ending in Z are UTC. Prices are decimal strings. Quantity null is unlimited; zero is finite zero. The response `active` flag means post status publish.

Follow [authentication.md](authentication.md) to grant the minimum Tickera primitives to selected users and issue separate user-specific Application Passwords. Publication, private edits, taxonomy assignment, images and templates require the additional capabilities documented there. Categories/templates/images must already exist; this API does not create them.

Use HTTPS in production and configure a trusted TLS proxy so WordPress recognizes HTTPS. Honor private/no-store responses at every reverse proxy and cache. MySQL/MariaDB must support GET_LOCK and RELEASE_LOCK; unavailable/contended write locks return 409 without an unlocked fallback. There is no schema change or persistent lock record.

No native WordPress or Tickera route is changed. Audit their independent exposure and dashboard permissions as part of site administration. See [security.md](security.md) for concurrency and partial-write limits.
