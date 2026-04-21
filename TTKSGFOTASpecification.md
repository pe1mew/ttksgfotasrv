# FOTA Server Specification

**Document scope:** Defines the HTTP interface, file formats, and TLS requirements for a server that delivers firmware-over-the-air (FOTA) updates to TTN Gateway hardware running the firmware in this repository. The specification is derived by static analysis of `app_ota.c`, `app_http_request.c`, `app_configuration.c`, and supporting files.

---

## 1. Background and trigger mechanism

The gateway checks for firmware updates on every boot and periodically during operation.

- **On boot:** After completing online configuration download, the application state machine enters `APP_STATE_DOWNLOAD_FIRMWARE` unconditionally and runs the full FOTA check before going operational.
- **While operational:** After `FIRMWARE_CHECK_TIMEOUT = 86400 seconds` (24 hours) the gateway calls `WatchDogReset()` to reboot itself, which re-enters the FOTA check on the next boot. The current implementation uses a full reboot rather than an in-place re-check.

The FOTA base URL (`firmware_url`) is provisioned by the TTN account server during the configuration download step (`GET /api/v2/gateways/{id}?filter=ttn`, JSON field `"firmware_url"`). It is stored in non-volatile flash (activation data sector). It can be overridden via the web UI FOTA override feature (Phase 7 of the UDP forwarder plan).
> At 20-4-2026 the FOTA URL served was: https://ttkg-fw.thethingsindustries.com/v1/beta or https://ttkg-fw.thethingsindustries.com/v1/stable/ 

---

## 2. FOTA URL structure

The `firmware_url` value is a standard HTTP or HTTPS URL of the form:

```
http[s]://hostname[:port]/base/path
```

The URL parser (`APP_HTTP_ParseUrl`) extracts:
- Scheme: `http` → plain TCP, default port 80; `https` → TLS, default port 443
- Host: hostname or IPv4 address string
- Port: from `:port` component if present, otherwise scheme default
- Path: everything after the first `/` following the host

The gateway appends two fixed resource names to the parsed path:

| Resource | Path appended |
|---|---|
| Checksum file | `/{path}/checksums` |
| Firmware binary | `/{path}/firmware.hex` |

**Example:** If `firmware_url` = `https://fw.example.com/ttn/v1` then:
- Checksum request: `GET /ttn/v1/checksums`
- Firmware request: `GET /ttn/v1/firmware.hex`

> At 20-4-2026 are active: 
> - https://ttkg-fw.thethingsindustries.com/v1/beta/checksums
> - https://ttkg-fw.thethingsindustries.com/v1/beta/firmware.hex 
> - https://ttkg-fw.thethingsindustries.com/v1/stable/checksums
> - https://ttkg-fw.thethingsindustries.com/v1/stable/firmware.hex

---

## 3. HTTP protocol requirements

All requests are HTTP/1.1 with the following fixed headers:

```
GET /{path}/{resource} HTTP/1.1
User-Agent: TTNGateway
Host: {hostname}:{port}
Connection: close

```

Key constraints derived from the client implementation:

- **HTTP version:** 1.1 (hardcoded).
- **Method:** GET only.
- **Connection:** `close` is sent on every request. The server **must** close the TCP connection after sending the complete response. The client uses connection close as the end-of-response signal.
- **No authentication:** No `Authorization` header is sent in FOTA requests. The server endpoint is unauthenticated at the HTTP layer (TLS provides transport security).
- **No chunked transfer encoding:** The client reads raw bytes from the socket; chunked encoding is not decoded. The server must send a plain body, not chunked.
- **Content-Length required:** The client parses `Content-Length` from the response header for both the checksum and firmware resources. Missing or zero Content-Length causes the FOTA to abort or skip.
- **Response buffer limit (checksums):** The checksum response is buffered entirely in a 6200-byte (`RESPONSE_BUFFER_SIZE`) buffer before parsing. The response must fit within this limit. In practice the body is 64 bytes plus a small HTTP header, well within the limit.
- **Chunk size (firmware):** Firmware is downloaded in up to 1024-byte reads from the TCP socket at a time. The server may send data at any rate; the client loops reading 1024-byte chunks until the connection closes.

---

## 4. Checksum resource (`checksums`)

### 4.1 Request

```
GET /{path}/checksums HTTP/1.1
User-Agent: TTNGateway
Host: {hostname}:{port}
Connection: close

```

### 4.2 Response — firmware update available

```
HTTP/1.1 200 OK
Content-Length: 64
Connection: close

{64-character lowercase or uppercase hex SHA256 of firmware.hex binary}
```

**Body format:** Exactly 64 ASCII hexadecimal characters (case-insensitive — both upper and lower case letters A-F are accepted by `AsciiToHexNibble`). The characters represent the 32-byte SHA256 digest of the raw firmware binary, big-endian, most-significant byte first.

Example body:
```
e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

The client reads exactly 64 characters and converts them to 32 binary bytes using `AsciiStringToHex`. Any content beyond the 64th character is ignored.

**Trailing whitespace / newline:** Ignored by the parser. A trailing `\n` or `\r\n` after the hash does not affect operation.

### 4.3 Response — no update available

Return any non-200 HTTP status code (e.g., `404 Not Found`). The client interprets any status other than 200 as "no firmware available" and transitions to `APP_OTA_DONE`, bypassing the firmware download entirely.

Alternatively, returning 200 with the SHA256 that matches the currently running firmware achieves the same result (see §6).

---

## 5. Firmware resource (`firmware.hex`)

This is an **Intel HEX format** text file — the standard output of the MPLAB X production build. Despite being a text format, the client stores it in serial flash byte-for-byte as received. The bootloader then parses the Intel HEX records from serial flash when applying the update, the same parser it uses for the SD card update path.

### 5.1 Request

```
GET /{path}/firmware.hex HTTP/1.1
User-Agent: TTNGateway
Host: {hostname}:{port}
Connection: close

```

### 5.2 Response

```
HTTP/1.1 200 OK
Content-Length: {exact byte count of binary image}
Connection: close

{raw binary firmware image}
```

**Content-Length** must equal the exact number of bytes in the binary body. The value is passed to `APP_SERIALFLASH_InitFOTA(content_length)` before any bytes are written to flash. At the end of download the firmware module verifies `bytes_written == content_length`; a mismatch causes the FOTA area to be erased and the update to be abandoned.

**Maximum firmware image size:** The firmware image is stored starting at flash address `FLASH_ADDRESS_FOTA_IMAGE = 0x4000` (16 KB). The upper boundary of the FOTA area is `FLASH_ADDRESS_USER_CONFIG_BASE = 0x7E0000` (after the Phase 1 and Phase 7 reservations in the UDP forwarder plan). Maximum supported image size:

```
0x7E0000 - 0x4000 = 0x7DC000 bytes = 8,048 KB
```

A firmware image larger than this value is rejected by `APP_SERIALFLASH_InitFOTA` (which calls `APP_SERIALFLASH_EraseFOTA` and aborts).

### 5.3 Response — no update available

Return any non-200 status. The client transitions to `APP_OTA_DONE`.

---

## 6. Update decision logic

The client applies a two-stage check to avoid unnecessary downloads:

```
Stage 1: Has this checksum been downloaded before?
  downloaded_sha256  ←  parse body of GET /checksums
  stored_fota_sha256 ←  read from flash FOTA data sector

  If downloaded_sha256 == stored_fota_sha256:
    → Already have this exact version in flash → DONE (no download)
  Else:
    → Erase FOTA area, store new checksum → proceed to stage 2

Stage 2: Is this a new version relative to the running firmware?
  current_fw_sha256  ←  read from flash firmware data sector (sector 0)

  If downloaded_sha256 == current_fw_sha256:
    → Running firmware already matches → DONE (no download)
  Else:
    → Download firmware.hex
```

**Implication for the server:** The server only needs to serve the current firmware version. The client handles version comparison against both the previously cached download and the running firmware. The server does not need to track gateway state.

---

## 7. Firmware integrity verification

After the complete firmware binary is received:

1. A SHA256 is computed incrementally as bytes are written to flash (using `CRYPT_SHA256_DataAdd` per chunk).
2. After the last byte: `CRYPT_SHA256_Finalize` produces a 32-byte digest.
3. This computed digest is compared with the checksum downloaded in stage 1 (`sha256_expected`).
4. If they **match**: the FOTA data sector is written with:
   - Magic bytes `0xDE 0xAD 0xBE 0xEF` at `FLASH_ADDRESS_FOTA_DATA_MAGIC_BYTES`
   - Image length as big-endian uint32 at `FLASH_ADDRESS_FOTA_DATA_IMAGE_LENGTH`
   - The gateway transitions to `APP_OTA_READY_FOR_REBOOT` and reboots.
5. If they **do not match**: the FOTA area is erased and the update is abandoned with `APP_OTA_STORAGE_ERROR`.

**The checksum in the `checksums` file must be the SHA256 of the raw binary content of `firmware.hex`, computed over every byte of the file.** It is verified against the binary as received over HTTP; the HTTP headers are not included in the hash.

---

## 8. TLS / HTTPS requirements

TLS is supported via mbedTLS (Harmony NET_PRES layer). When the `firmware_url` uses the `https://` scheme:

- The TCP socket TX buffer is enlarged to 16384 bytes and RX to 20000 bytes before the TLS handshake.
- SNI (Server Name Indication) is set to the hostname from the URL (`NET_PRES_EncGlue_SetSNIHostname`).
- The server certificate chain is validated against four embedded CA certificates.

### 8.1 Trusted CA certificates

The gateway trusts **only** the following root Certificate Authorities (hardcoded in `net_pres_cert_store.c`):

| CA | Common use |
|---|---|
| **ISRG Root X1** | Let's Encrypt issued certificates |
| **DigiCert Global Root G2** | DigiCert, many commercial issuers |
| **Amazon Root CA 1** | AWS services (CloudFront, API Gateway, S3, etc.) |
| **Starfield Services Root Certificate Authority – G2** | AWS CloudFront, Amazon S3 |

**Self-signed certificates are not accepted.** The FOTA server's TLS certificate must chain to one of the four CAs above.

### 8.2 Recommended certificate issuers

- **Let's Encrypt** (free, auto-renewing): chains to ISRG Root X1. Suitable for a dedicated FOTA server.
- **AWS CloudFront / S3**: chains to Amazon Root CA 1 or Starfield Services G2. Suitable for serving files from S3 via CloudFront.
- **DigiCert commercial certificates**: chain to DigiCert Global Root G2.

### 8.3 Plain HTTP

Plain `http://` URLs are also supported (no TLS). Suitable for testing in a controlled network environment. Not recommended for production due to lack of transport security.

---

## 9. Error behaviour summary

| Condition | Gateway behaviour |
|---|---|
| `checksums` returns non-200 | `APP_OTA_DONE` — treated as no update |
| `checksums` body cannot be parsed (empty) | SHA256 converts to all-zero bytes; compared normally |
| `firmware.hex` returns non-200 | `APP_OTA_DONE` — treated as no update |
| `Content-Length` absent or 0 | `APP_SERIALFLASH_InitFOTA(0)` — image_length 0, finalize will fail size check → erase + `APP_OTA_STORAGE_ERROR` |
| `Content-Length` > max image size | `APP_SERIALFLASH_EraseFOTA()` → erase + abort |
| Downloaded size ≠ Content-Length | Finalize fails size check → erase + `APP_OTA_STORAGE_ERROR` |
| SHA256 mismatch after download | `APP_SERIALFLASH_EraseFOTA()` → erase + `APP_OTA_STORAGE_ERROR` |
| DNS resolution failure (2 s timeout) | `APP_HTTP_REQUEST_ERROR` → `APP_OTA_ERROR` → `APP_STATE_OPERATIONAL` |
| TCP connection timeout (5 s) | `APP_HTTP_REQUEST_ERROR` → `APP_OTA_ERROR` → `APP_STATE_OPERATIONAL` |
| TLS negotiation failure | `APP_HTTP_REQUEST_ERROR` → `APP_OTA_ERROR` → `APP_STATE_OPERATIONAL` |

After `APP_OTA_ERROR` or `APP_OTA_STORAGE_ERROR` the gateway continues to the operational state. The next FOTA attempt occurs after the next reboot (triggered by the 24-hour watchdog reset).

---

## 10. Minimal server implementation

A minimal server serving two static files is sufficient. The following is the complete set of server responsibilities:

1. **Compute SHA256** of the firmware binary (`firmware.hex`).
2. **Serve `GET /checksums`** with HTTP 200, `Content-Length: 64`, body = 64-char lowercase hex SHA256 string, `Connection: close`.
3. **Serve `GET /firmware.hex`** with HTTP 200, `Content-Length: {file_size}`, body = raw binary, `Connection: close`.
4. **Return a non-200 response** for any request that should not trigger an update (e.g., when no new firmware exists, or for unrecognised paths).
5. If using HTTPS: **obtain a TLS certificate** from Let's Encrypt, DigiCert, or a CA that chains to one of the four trusted roots listed in §8.1.

A static file host (nginx, Apache, S3+CloudFront, GitHub Releases, etc.) can serve these two files directly. No server-side logic is needed beyond computing and publishing the SHA256 checksum file alongside the binary.

---

## 11. File layout example

For `firmware_url = https://fw.example.com/gateway/v1`:

```
https://fw.example.com/gateway/v1/checksums      ← 64-char hex SHA256 text file
https://fw.example.com/gateway/v1/firmware.hex   ← raw binary firmware image
```

Directory layout on the server:

```
/var/www/html/gateway/v1/
    checksums          (64 bytes of hex text + optional newline)
    firmware.hex       (raw binary, e.g. 512000 bytes)
```

Shell command to generate the checksums file (matches the build script `generate_hex_with_checksum.sh`):

```sh
sha256sum firmware.hex > checksums
```

This produces a line of the form `{64-char hex hash}  firmware.hex`. The FOTA client reads only the first 64 characters (the hash) and ignores the trailing filename. The SD card bootloader uses the same file, so **no separate checksum file is needed for FOTA vs SD card**.

---

## 12. Version identification

The running firmware stores its own SHA256 at flash sector 0 (`FLASH_ADDRESS_FIRMWARE_DATA`). This hash is written by the bootloader or a post-programming step and is available as `appGWActivationData.current_firmware_key`. The FOTA client compares the server-provided checksum against this value to decide whether the currently running firmware is already up to date.

The server does not need to know the gateway's running version. The client requests the server's current checksum unconditionally and performs the version comparison locally.

---

## 13. Single-file PHP server implementation

A minimal FOTA server can be implemented as a single PHP file. The file serves the two resources based on the request URI, sets all required headers, and streams the files without loading them into memory.

```php
<?php
$uri = $_SERVER['REQUEST_URI'];

if (str_ends_with($uri, '/checksums')) {
    $file = __DIR__ . '/checksums';
    if (!file_exists($file)) { http_response_code(404); exit; }
    header('Content-Type: text/plain');
    header('Content-Length: ' . filesize($file));
    header('Connection: close');
    readfile($file);

} elseif (str_ends_with($uri, '/firmware.hex')) {
    $file = __DIR__ . '/firmware.hex';
    if (!file_exists($file)) { http_response_code(404); exit; }
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($file));
    header('Connection: close');
    readfile($file);

} else {
    http_response_code(404);
}
```

Place `fota.php`, `firmware.hex`, and `checksums` in the same directory. The `firmware.hex` and `checksums` files are the direct output of `generate_hex_with_checksum.sh` — no conversion needed.

### 13.1 Development / testing (HTTP, no TLS)

Use the PHP built-in server — no Apache or nginx required:

```sh
php -S 0.0.0.0:80 fota.php
```

Set `firmware_url` to `http://{server-ip}/` via the gateway web UI FOTA override. **HTTP only — do not use in production.**

### 13.2 Production (HTTPS with nginx reverse proxy)

The PHP built-in server does not support TLS. For production, place nginx in front with a Let's Encrypt certificate (chains to ISRG Root X1, which is in the gateway's trusted CA store).

Minimal nginx site configuration:

```nginx
server {
    listen 443 ssl;
    server_name fw.example.com;

    ssl_certificate     /etc/letsencrypt/live/fw.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/fw.example.com/privkey.pem;

    location / {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/fota/fota.php;
        include fastcgi_params;
    }
}
```

Or with Apache, add a `.htaccess` in the same directory as `fota.php`:

```apache
RewriteEngine On
RewriteRule ^ fota.php [L]
```

### 13.3 Key implementation notes

- `Content-Length` is set before any output via `header()`. This prevents Apache/nginx from switching to chunked transfer encoding, which the gateway client cannot decode.
- `readfile()` streams the file directly to the output buffer without loading it into PHP memory — safe for large Intel HEX files.
- The `checksums` file (format: `{hash}  firmware.hex\n`) is served as-is. The gateway reads only the first 64 characters (the hash) and ignores the trailing filename.
- `str_ends_with()` requires PHP 8.0+. For PHP 7.x replace with `substr($uri, -strlen('/checksums')) === '/checksums'`.

---

## 14. Constraints and known limitations

| Constraint | Value | Source |
|---|---|---|
| Maximum URL length (firmware_url) | 255 chars | `APP_URL_Buffer[255]`, `app_ota.c:18` |
| Maximum firmware image size (Intel HEX file) | 8,048 KB | `FLASH_ADDRESS_USER_CONFIG_BASE − FLASH_ADDRESS_FOTA_IMAGE` |
| Checksums response buffer | 6,200 bytes | `RESPONSE_BUFFER_SIZE`, `app_http_request.h:9` |
| Firmware download chunk size | 1,024 bytes | `app_http_request.c:261` |
| DNS timeout | 2 seconds | `app_http_request.c:126` |
| TCP connection timeout | 5 seconds | `app_http_request.c:166` |
| TLS: trusted CAs | 4 (ISRG X1, DigiCert G2, Amazon CA1, Starfield G2) | `net_pres_cert_store.c` |
| HTTP version | 1.1 | hardcoded request headers |
| Transfer encoding | Plain (no chunked) | client reads raw socket bytes |
| Authentication | None | no Authorization header sent |
| FOTA check interval | 24 hours (via reboot) | `FIRMWARE_CHECK_TIMEOUT`, `app.c:19` |
