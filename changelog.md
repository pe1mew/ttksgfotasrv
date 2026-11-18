# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.2] — 2026-11-18

### Added
- `fotasrv/firmware.hex` and `fotasrv/checksums` — gateway firmware release 1.2.30 is now tracked in the repository so the production server can be deployed directly from it (SHA256 `9a3ed773ad6e33f52e22b56a0b56be6ecd820a4a4ac11df159d099d0239245ce`)

### Changed
- `.gitignore` — only the `simplefota/` development copies of `firmware.hex` and `checksums` are ignored

### Firmware 1.2.30
- UDP forwarder: `freq` is formatted with integer arithmetic so ChirpStack accepts uplinks (gateway issue #1); stale queued uplinks are discarded while the forwarder is disabled

---

## [1.0.1] — 2026-11-18

### Added
- `README.md` — "Active FOTA Server" section documenting the public instance at <https://rfsee.net/fotasrv/>, its endpoints, and how to point a gateway at it

### Changed
- `fotasrv/index.php` — configuration instructions now tell users to set the Account Server to `https://eu1.cloud.thethings.network/`

### Fixed
- `fotasrv/index.php` — alignment of the SD card directory tree on the information page
- `fotasrv/index.php` — removed the dead `mchp.js` script reference and `bootWebsite()` onload handler, which caused a 404 and a JavaScript error on every page load

---

## [1.0.0] — 2026-05-20

### Added
- `fotasrv/index.php` — FOTA server with firmware and checksum delivery
- `fotasrv/checksums` — SHA256 checksum file for firmware verification
- `simplefota/fota.php` — Simple FOTA server implementation
- `simplefota/start-fota-server.sh` — Shell script to start the simple FOTA server
- `TTKSGFOTASpecification.md` — FOTA protocol specification
- `README.md`, `license.md` — project documentation and licensing

---

## [0.0.1] — 2026-04-21

*Project initialised.*

### Added
- Initial repository structure
- `code_of_conduct.md`, `contributing.md` — community standards

