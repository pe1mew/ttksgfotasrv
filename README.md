# The Things Kickstarter Gateway FOTA Server

A PHP-based Firmware Over-The-Air (FOTA) server for The Things Network Kickstarter Gateway, enabling custom firmware updates outside the default TTI infrastructure. This repository provides both a production-ready web server with an informational interface and a minimal development server for testing.

## Features

- **Production FOTA Server** - Full-featured web interface with gateway configuration instructions
- **Development Server** - Minimal PHP server for local firmware testing
- **Automatic URL Detection** - Server displays its own URL for easy gateway configuration
- **Standards Compliant** - Serves Intel HEX firmware files and SHA256 checksums as expected by gateway hardware
- **Informational Pages** - Comprehensive setup documentation accessible via browser
- **Zero Configuration** - Works out-of-the-box on any PHP-capable web server

## Repository Structure

```
ttksgfotasrv/
│
├── fotasrv/                    ← Production FOTA server
│   ├── index.php               ← Main server with web interface
│   ├── firmware.hex            ← Gateway firmware binary (not part of repository)
│   ├── checksums               ← SHA256 verification file (not part of repository)
│   └── readme.md               ← Deployment documentation
│
├── simplefota/                 ← Development/testing server
│   ├── fota.php                ← Minimal FOTA server script
│   ├── start-fota-server.sh    ← Quick start script
│   ├── firmware.hex            ← Gateway firmware binary (not part of repository)
│   ├── checksums               ← SHA256 verification file (not part of repository)

│
├── TTKSGFOTASpecification.md   ← FOTA protocol specification
├── README.md                   ← This file
├── LICENSE
├── license.md
├── changelog.md
├── contributing.md
└── code_of_conduct.md
```

## Getting Started

### For Production Deployment

Deploy the full FOTA server with web interface on your PHP-capable web server:

1. See **[fotasrv/readme.md](fotasrv/readme.md)** for complete deployment instructions
2. Upload files to your HTTPS-enabled web server
3. Configure your gateway to use your custom FOTA URL

### For Development & Testing

Run a local FOTA server for firmware testing:

1. See **[simplefota/readme.md](simplefota/readme.md)** for setup instructions
2. Start the PHP built-in server with your firmware files
3. Point your development gateway to your local server

### FOTA Protocol Specification

For technical details about the FOTA protocol implementation, see **[TTKSGFOTASpecification.md](TTKSGFOTASpecification.md)**.

## Prerequisites

- **PHP 7.4+** (PHP 8.0+ recommended)
- **Web server** with PHP support (Apache, nginx, or PHP built-in server)
- **HTTPS certificate** (production deployments only)
- **Gateway firmware files** from the [gateway repository](https://github.com/pe1mew/gateway/tree/upd-only)


## License

See the [license.md](license.md) file for full details.

**Software** (firmware and all code): Source-available, non-commercial. Free to use and modify for personal/non-commercial purposes; redistribution and commercial use are not permitted.

**Hardware design, documentation, and images**: Licensed under the Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License.

<a rel="license" href="https://creativecommons.org/licenses/by-nc-nd/4.0/"><img alt="Creative Commons License" style="border-width:0" src="https://i.creativecommons.org/l/by-nc-nd/4.0/88x31.png" /></a><br />Hardware design, documentation, and images are licensed under a <a rel="license" href="https://creativecommons.org/licenses/by-nc-nd/4.0/">Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License</a>.

## Disclaimer

This project is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.