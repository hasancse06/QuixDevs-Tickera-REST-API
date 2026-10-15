# QuixDevs Tickera REST API v1.0.0

Initial free/open-source release of the independent QuixDevs addon for standalone Tickera 3.6.0.8. Eight authenticated REST operations let authors create, list, view and patch their own events and ticket types. Strict ticket/parent ownership also applies to administrators.

Includes native WordPress authentication, explicit field schemas, capability-aware totals, service-layer validation, related-write locks, safe dependency notices and verified Tickera save hooks. Supports event local dates, locations/terms/categories/images and ticket decimal pricing, quantity, sale range and owned templates. No purchase/order/attendee/check-in/deletion or Bridge-product APIs are included.

Install **quixdevs-tickera-rest-api.zip** and verify its adjacent SHA-256 file. Composer is unnecessary on the server. Requirements are WordPress 6.8+, PHP 8.1+, supported MySQL/MariaDB advisory locks and standalone Tickera 3.6.0.8. Review [compatibility](compatibility.md) and the [release-readiness report](release-readiness-report.md) for locally executed environments and unexecuted matrix jobs before publication. Other Tickera versions fail closed.

Provision selected-author capabilities and use HTTPS plus per-user Application Passwords, or cookie/REST nonce authentication. A 409 may indicate write contention or unsupported ticket mode. WordPress hooks are not transactional; inspect after a late failure before retrying POST.

GPL-2.0-or-later. Publisher: QuixDevs, https://quixdevs.com/. Developed by Md Abu Hasan, https://hasan.online/. No official Tickera affiliation is implied.

Maintainer: this document is prepared for a draft release. Review all checks and publish manually only after public version history and minimum-version CI have been verified.

## Release links

- [Download installation ZIP](https://github.com/hasancse06/QuixDevs-Tickera-REST-API/releases/latest/download/quixdevs-tickera-rest-api.zip)
- [All releases](https://github.com/hasancse06/QuixDevs-Tickera-REST-API/releases)
- [v1.0.0 release page](https://github.com/hasancse06/QuixDevs-Tickera-REST-API/releases/tag/v1.0.0)

The version page and latest asset returned 404 on 2026-10-15. These links describe the intended public release; they do not claim it is already published. Use the exact ZIP asset rather than the automatic Source code ZIP.
