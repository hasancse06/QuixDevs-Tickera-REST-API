# QuixDevs Tickera REST API branding

Matched to the existing QuixDevs ProductClock banner: navy `#091827`, teal `#35e2cc`, white `#f4fbff`, muted blue `#b3c8d7`, the same layout, embedded QuixDevs wordmark and creator credit **Created by M A Hasan**. The ticket/code symbol is specific to this independent REST API addon and is not an official Tickera logo.

The supplied `quix-devs-logo.png` is preserved byte-for-byte as [quixdevs-logo.png](quixdevs-logo.png), at its original 164 × 45 dimensions with transparency. Both banner SVGs embed that exact PNG and have no external image dependency.

| Asset | Dimensions / purpose |
|---|---|
| [GitHub preview PNG](github-social-preview.png) | 1280 × 640; also displayed in the repository README |
| [Editable GitHub preview SVG](github-social-preview.svg) | Editable native composition |
| [Editable WordPress banner SVG](wordpress-banner.svg) | 772 × 250 viewBox |
| [WordPress banner](../../wordpress-org-assets/banner-772x250.png) | 772 × 250 |
| [WordPress banner at 2×](../../wordpress-org-assets/banner-1544x500.png) | 1544 × 500 |
| [Ticket/API mark SVG](tickera-api-mark.svg) | 270 × 270; reusable original product symbol |

Text: **Tickera REST API**; **Event & Ticket Type Management for Authors**; **Manage Events and Tickets Through REST API.** The social preview's workflow labels are **CREATE**, **VIEW**, **EDIT**. Typography follows ProductClock's Arial/Helvetica/sans-serif stack.

The artwork was adapted from editable SVG sources and exported deterministically with Sharp. It was not generated or altered with AI image generation. Both PNG layouts were visually inspected; dimensions, SVG XML and embedded logo integrity were verified.

To reproduce, install Sharp as a development tool and run `node tools/render-branding.mjs` from the addon directory. If Sharp is installed externally, set `QTRA_SHARP_MODULE` to its absolute module path. This artwork tool adds no plugin runtime dependency. No publishing or social-preview upload was performed.
