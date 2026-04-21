# Production FOTA Server

A production-ready FOTA (Firmware Over-The-Air) server for The Things Kickstarter Gateway with an informational web interface and secure firmware delivery.

## Contents

- **index.php** - Main server script that:
  - Serves firmware files (`firmware.hex`) and checksums
  - Displays an informational HTML page with setup instructions
  - Automatically detects and displays the server URL
  - Provides comprehensive documentation about FOTA configuration

- **firmware.hex** - Gateway firmware binary in Intel HEX format
- **checksums** - SHA256 checksums for firmware verification

## Deployment on PHP Web Server

### Prerequisites

- Web server with PHP 7.4+ support (Apache, nginx, etc.)
- HTTPS/TLS certificate configured (required for production)
- Write permissions for the web server user (optional, for future logging features)

### Installation Steps

1. **Upload files to your web server:**
   ```
   Upload the entire fotasrv/ directory contents to your web server.
   Example location: /var/www/html/fotasrv/
   ```

2. **Set file permissions:**
   ```sh
   chmod 644 index.php checksums firmware.hex
   ```

3. **Verify PHP configuration:**
   Ensure your web server is configured to serve `.php` files.
   
   For Apache, verify `mod_php` or `php-fpm` is enabled.
   For nginx, verify `php-fpm` is configured.

4. **Configure directory index (if needed):**
   
   **For Apache:** Create or modify `.htaccess`:
   ```apache
   DirectoryIndex index.php
   ```
   
   **For nginx:** Add to server block:
   ```nginx
   index index.php;
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

5. **Test the deployment:**
   ```sh
   # Test the informational page
   curl https://yourdomain.com/fotasrv/
   
   # Test checksums endpoint
   curl https://yourdomain.com/fotasrv/checksums
   
   # Test firmware endpoint
   curl -I https://yourdomain.com/fotasrv/firmware.hex
   ```

### Updating Firmware

To update the firmware served by this FOTA server:

1. Generate new firmware files using the gateway repository's build process
2. Upload the new `firmware.hex` and `checksums` files
3. Ensure file permissions remain correct (644)
4. No server restart required - changes are immediate

### Gateway Configuration

Instruct gateway users to:

1. Access their gateway's web interface
2. Navigate to settings and enable "Show advanced options"
3. Check "Override FOTA URL"
4. Enter your FOTA server URL: `https://yourdomain.com/fotasrv/`
5. Save settings

The gateway will then check your server for firmware updates.

## Security Considerations

- **Always use HTTPS** in production to protect firmware integrity during transmission
- Regularly update the firmware.hex and checksums files
- Verify checksums match before uploading new firmware
- Consider implementing rate limiting to prevent abuse
- Monitor server logs for unusual access patterns

## Troubleshooting

**404 errors for firmware files:**
- Verify file names match exactly: `firmware.hex` and `checksums`
- Check file permissions are readable by the web server
- Ensure files are in the same directory as `index.php`

**Page displays but downloads fail:**
- Check web server error logs for PHP errors
- Verify PHP has permission to read the firmware files
- Test file access directly via shell: `php index.php`

**Gateway cannot connect:**
- Verify HTTPS is properly configured with valid certificate
- Check firewall rules allow inbound HTTPS traffic
- Ensure the URL in gateway settings matches your server's domain

## More Information

- FOTA Server Repository: https://github.com/pe1mew/ttksgfotasrv
- Gateway Firmware: https://github.com/pe1mew/gateway/tree/upd-only
- For development/testing, see `../simplefota/` directory