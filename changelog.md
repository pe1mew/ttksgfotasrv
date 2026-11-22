# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.5] — 2026-11-22

### Changed
- `fotasrv/firmware.hex` and `fotasrv/checksums` — gateway firmware release 1.2.37 replaces 1.2.35 (SHA256 `16fc5d0fdd11b3c9c5bf6ffaefc54f98bf50b656412266613c87f59780e84fc1`)

### Firmware 1.2.37
- Seeds the pseudo-random generator from the hardware random number generator at boot (gateway issue #6): TCP source ports and sequence numbers are no longer the same after every reboot, which removes the stalled TLS handshakes seen during repeated reboots
- Version 1.2.36 was a diagnostic build and was not released

---

## [1.0.4] — 2026-11-20

### Changed
- `fotasrv/firmware.hex` and `fotasrv/checksums` — gateway firmware release 1.2.35 replaces 1.2.32 (SHA256 `8263bfe483808982552fa56111aff56761b6f831c8fa0e73d708ed3910264bf5`)

### Firmware 1.2.35
- Fixes the watchdog reset during LoRa module configuration (gateway issue #3): separate read and write driver handles for the LoRa UART
- Fixes the watchdog reset during TLS connect (gateway issue #5): non-blocking TLS handshake, 10 s timeout and retry in the HTTP and MQTT clients
- Reserves a 4 KB startup stack (heap 350000 → 346000 bytes); prints `*** WDT pending` diagnostics before a watchdog reset
- Versions 1.2.33 and 1.2.34 were test builds and were not released

---

## [1.0.3] — 2026-11-19

### Changed
- `fotasrv/firmware.hex` and `fotasrv/checksums` — gateway firmware release 1.2.32 replaces 1.2.30 (SHA256 `3f2b53181b771452ce84608e672d48d2c9f5647403a41557d5f7d5bae6a8b5c1`)

### Firmware 1.2.32
- Fixes the reboot on web UI access after long uptime and the boot loop with an HTTPS FOTA URL (gateway issue #2): FreeRTOS `heap_4` replaces the non-coalescing `heap_2`, and an MQTT publish timeout reconnects MQTT only instead of re-initialising the whole network stack and TLS session
- `MON:` console line reports largest free block, free block count and minimum free heap

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

