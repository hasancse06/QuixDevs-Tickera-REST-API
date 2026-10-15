# Licensing and attribution audit

QuixDevs Tickera REST API v1.0.0 is GPL-2.0-or-later, copyright 2026 QuixDevs. Developed by Md Abu Hasan, https://hasan.online/ and https://github.com/hasancse06. The full GPL v2 text is in LICENSE; the main header, readme.txt, README and Composer metadata explicitly permit later GPL versions.

The installation ZIP contains first-party addon PHP, docs/rest-api.md, readme.txt and LICENSE. It does not bundle WordPress, Tickera, Composer packages, downloaded binaries, fonts or third-party runtime code. It has no runtime dependency on vendor/autoload.php. WordPress and Tickera must be installed separately.

Repository branding adapts the user's supplied QuixDevs logo and existing QuixDevs ProductClock banner layout. The ticket/code mark is first-party editable SVG. The existing creator signature uses the ProductClock wording “Created by M A Hasan”; current project metadata gives the full developer name Md Abu Hasan. These brand assets are repository previews, not packaged runtime assets. No Tickera logo or claim of official affiliation is included. The user supplied the brand material; no independent trademark-rights verification is claimed.

Development dependencies retain their own licenses in composer.lock/vendor and are excluded from the ZIP: PHPUnit (BSD-3-Clause), WPCS/PHP_CodeSniffer (MIT/BSD-3-Clause), PHPStan (MIT), WordPress stubs/extensions (MIT), PHPCompatibility packages (LGPL-3.0-or-later). Composer is the authoritative dependency license inventory (`composer licenses`).

Optional development tools downloaded by install-tools.py are official actionlint and Gitleaks binaries (MIT), pinned by SHA-256. Neither their binaries nor third-party source is distributed inside the plugin ZIP. Release helper/packager use the Python standard library. GitHub Actions and WordPress Plugin Check run externally; their licenses remain with their respective projects.
