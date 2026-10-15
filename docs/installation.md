# Installation

Use WordPress 6.8+ and PHP 8.1+ with standalone Tickera **3.6.0.8** active. This addon requires no Composer runtime or WooCommerce installation. Review the [actual tested environments](compatibility.md).

Build with `python3 tools/build-release.py`, or obtain the lightweight `quixdevs-tickera-rest-api.zip` from [GitHub Releases](https://github.com/hasancse06/QuixDevs-Tickera-REST-API/releases) once published. Verify its adjacent checksum with `shasum -a 256 -c quixdevs-tickera-rest-api.zip.sha256` from the download directory. In WordPress use Plugins → Add New → Upload Plugin, choose this ZIP and activate. The archive must install as `wp-content/plugins/quixdevs-tickera-rest-api/`, with its main PHP file directly inside that folder.

GitHub's automatic Source code ZIP is a development archive, not the recommended installation package. Do not install `vendor/`, `.work/`, tests or CI files on the public server.

WordPress normally blocks activation if the declared `tickera-event-ticketing-system` dependency is inactive. If the addon is forcibly loaded, or Tickera is later disabled/removed, the addon still loads without a fatal error; administrators see a dependency notice and authenticated management requests return 503. Unsupported Tickera versions also fail closed. Bridge mode blocks ticket operations with 409; event operations remain available.

Configure the site timezone, selected-user capabilities and HTTPS before using the API. There are no addon migrations or stored credentials. Test an upgrade on staging and back up WordPress first; the addon has no deletion API or destructive uninstall routine.

The [latest installation asset](https://github.com/hasancse06/QuixDevs-Tickera-REST-API/releases/latest/download/quixdevs-tickera-rest-api.zip) and [v1.0.0 release page](https://github.com/hasancse06/QuixDevs-Tickera-REST-API/releases/tag/v1.0.0) are prepared URLs. They returned 404 at the 2026-10-15 check; no public installation asset is claimed. Follow the numbered WordPress installation steps in the [README](../README.md#download--installation).
