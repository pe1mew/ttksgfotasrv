<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$basename = basename($uri);

if (substr($uri, -10) === '/checksums' || $uri === '/checksums' || $basename === 'checksums') {
    $file = __DIR__ . '/checksums';
    if (!file_exists($file)) { http_response_code(404); exit; }
    
    header('Content-Type: text/plain');
    header('Content-Length: ' . filesize($file));
    header('Connection: close');
    readfile($file);
    exit;

} elseif (substr($uri, -13) === '/firmware.hex' || $uri === '/firmware.hex' || $basename === 'firmware.hex') {
    $file = __DIR__ . '/firmware.hex';
    if (!file_exists($file)) { http_response_code(404); exit; }
    
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($file));
    header('Connection: close');
    readfile($file);
    exit;

} else {
    // Construct the current URL dynamically
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    if ($scriptPath === '/' || $scriptPath === '\\') {
        $scriptPath = '';
    }
    $currentUrl = $protocol . '://' . $host . $scriptPath . '/';
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8">
    <title>The Things Kickstarter gateway FOTA server</title>
    <style>

    html { height:100%; }
    body{height:100%;font-family:arial;background:#f2f2f2;color:#555}.container{width:650px;margin:5em auto;padding:3em;background:#fff;border:2px solid #eee;border-radius:5px}input{outline:0;padding:.5em;font:inherit;transition:border-color .2s;box-sizing:border-box}input:focus{border-color:#1a74b8}.container,input{border-radius:5px;border:2px solid #eee}label{display:block;margin-bottom:2em}label:last-child{margin-bottom:0}label input:not([type=checkbox]){width:100%}input[type=checkbox]{width:auto;vertical-align:middle}.label{font-weight:700;margin:0 0 .3em .3em}.inl{display:inline;margin-left:.5em}#adv{display:none;margin-top:1em}#a:checked~#adv{display:block}#a,#a+label{font-size:.8em;color:#999}h1{text-align:center;color:#1a74b8;margin:0 0 1.2em 0}h2{color:#1a74b8;margin:1.5em 0 0.8em 0;font-size:1.3em}h3{color:#555;margin:1em 0 0.5em 0;font-size:1.1em}ul{margin:0.5em 0 1em 1.5em}ul li{margin:0.3em 0}code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-family:monospace;color:#d14;font-size:0.9em}.save{background:#66d1bb;padding:1em 2em;color:#fff;border:none;font:inherit;border-radius:300px;cursor:pointer}.buttons{text-align:right;margin-top:1em}</style>
    <script src="./mchp.js" type="text/javascript"></script>
  </head>
  <body onload="bootWebsite()">
    <div class="container">
      <input type="hidden" name="wlan" id="wlan" value="infra">
      <center>
        <svg viewBox="-105 197 400 400" fill="#333" width="200">
          <path fill="#0071BC" d="M223.8 379c.7 8.6-2 16.7-7.5 22.9-5.9 6.6-14.7 10.2-24.6 10.2H-23.2c-8.7-.1-16.9-3.5-23-9.6-6.5-6.4-10-15-10.1-24.1 0-16.9 11.5-30.6 27.2-33.2 2.1-14.1 14.4-25.6 29.6-28 .9-5.6 3.6-11 7.9-15.4 6.2-6.4 14.6-10 23.2-10 11.1 0 20.8 6 26 15C63.7 285 83.7 269 107.4 269c25.9 0 47.5 19.2 51.1 44.2 13-9.7 30.3-8.8 43.3 3.4l-1.4-1.2c10.4 8.4 14 19.8 13.5 29.4-.3 5.7-2.2 10.7-5.6 14.8 3.3 1.1 6 3 8.6 5.6 3.6 3.6 6.1 8.1 6.8 13.1v.6c.1-.2.1-.1.1.1zm16.1-80.7c-7.6-10.3-18.9-17.9-31.3-21.1-2.8-.6-5.7 1.3-6.3 4-1.1 2.8.7 6.4 3.7 7 12.5 3.3 23.3 12.1 29 23.7 4.7 9.5 5.9 20.7 3.3 31-.7 2.9 1.4 6 4.4 6.4 2.7.8 5.7-.8 6.6-3.4 4.1-16.1.6-34.2-9.4-47.6zm-35.7-1.6c-4.3-.8-7.6 4.5-5.7 8.2.7 1.6 2.3 2.7 4 3.1 6.5 1.7 12.2 6.2 15.2 12.2 2.7 5.3 3.3 11.5 1.8 17.2-.8 2.9 1.3 6.1 4.3 6.6 2.7.8 5.9-.8 6.7-3.6 2.8-10.2.7-21.6-5.5-30.1-5-6.8-12.5-12-20.8-13.6z"/>
          <path d="M-47.3 506.8h-5.4v-23.7h5.4l9.5 14.8v-14.8h5.4v23.7h-5.4l-9.5-14.8v14.8zm56.5-19.1H1v4.6h7.8v4.8H1v4.8h8.2v4.8H-4.4V483H9.2v4.7zm26-4.7h16.7v4.8h-5.6v18.9h-5.4v-18.9h-5.6V483h-.1zm57.7 10.5l-3.8 13.3h-6l-6.7-23.7h5.9l4 16.4h.1l4.3-16.4h4l4.3 16.4h.4l4-16.4h6l-6.7 23.7h-6l-3.8-13.3zm52.3-10.9c6.6 0 12 5.4 12 12.2 0 7-5.1 12.3-12 12.3-7.1 0-12-5.4-12-12.3s5.4-12.2 12-12.2zm0 19.3c4.3 0 6.1-3.5 6.1-7.1 0-3.4-2-7.1-6.1-7.1-4.2 0-6.1 3.5-6.1 7.1 0 3.6 1.7 7.1 6.1 7.1zm47.3-18.9c4.4 0 7.8 2.4 7.8 7.1 0 3.5-2 6-4.9 6.8l8.2 9.9h-6.8l-7.2-9.4v9.4h-5.4v-23.7l8.3-.1zm-2.5 10.6c2.1 0 4.4-.1 4.4-3.2s-2.4-3.2-4.4-3.2h-.6v6.2h.6v.2zm53.2 13.2l-8.8-10.1v10.1H229v-23.7h5.4v9.8l8.2-9.8h6.6l-9.7 11.2 11 12.5h-7.3zm-299.4-76.9h27.7v7.9h-9.4v31.5h-9v-31.5h-9.4l.1-7.9zm41.9 39.5h-9v-39.5h9v15.8h12.7v-15.8h9v39.5h-9v-15.9h-12.7v15.9zm51.9-31.5H24.1v7.8h13v7.9h-13v7.9h13.6v7.9H15v-39.5h22.6v8zm17.3-8h27.7v7.9h-9.4v31.5h-9v-31.5h-9.4l.1-7.9zm41.9 39.5h-9v-39.5h9v15.8h12.7v-15.8h9v39.5h-9v-15.9H96.8v15.9zm38.4 0h-9v-39.5h9v39.5zm16.6 0h-9v-39.5h9l15.6 24.4h.1v-24.4h9v39.5h-9L151.9 445h-.1v24.4zm68-13.6c-2.2 10-10.5 14.3-18.2 14.3-10.6 0-19.2-9.2-19.2-20.4 0-11.4 8.2-20.4 19.2-20.4 7 0 11.7 2.6 15.2 6.7l-5.9 6.5c-2.3-3.7-5.3-5-8.8-5-5.5 0-10 5.5-10 12.3 0 6.7 4.5 12.1 10 12.1 4.3 0 8.6-2.8 8.6-7.6h-9v-7h18.2v8.5h-.1zm31.8-22.8l-3.7 7.2s-3.9-2.7-7.9-2.7c-3.1 0-4.6 1.3-4.6 3.5s3.8 3.8 8.2 5.7c4.4 1.8 9.3 5.6 9.3 10.8 0 9.4-7.2 12.7-14.9 12.7-9.3 0-14.8-5.3-14.8-5.3l4.5-7.6s5.3 4.4 9.7 4.4c2 0 5.7-.2 5.7-3.9 0-2.8-4.2-4.2-8.9-6.5-4.8-2.3-7.5-6-7.5-10.1 0-7.3 6.5-12.1 12.8-12.1 7 .1 12.1 3.9 12.1 3.9z"/>
        </svg>
      </center>
      <h1>The Things Kickstarter gateway FOTA server</h1>
      
      <h2>About this FOTA Server</h2>
      <p>This Firmware Over-The-Air (FOTA) server provides firmware updates for The Things Network Kickstarter Gateway devices. It enables automatic and manual firmware upgrades to ensure your gateway stays up-to-date with the latest features and security patches.</p>
      
      <p><The original gateway software retrieves its FOTA server URL from The Things Industries (TTI) account server during initial configuration. By default, the gateway will only check for firmware updates from the server specified by TTI.</p>
      <p>The firmware provided on this server includes a modified configuration that allows you to override the default FOTA server setting. This is necessary if you want to use a custom FOTA server instead of the TTI-managed one. Without this firmware modification, the gateway will continue to use only the FOTA server URL provided by the TTI account server.</p>
      
      <p>This server is specifically designed for:</p>
      <ul>
        <li><strong>The Things Gateway</strong> - Original Kickstarter edition LoRaWAN gateway</li>
        <li><strong>Model:</strong> TTKG (The Things Kickstarter Gateway)</li>
        <li><strong>Manufacturer:</strong> The Things Industries</li>
      </ul>
      
      <h3>Firmware Information</h3>
      <p>The firmware files available on this server include:</p>
      <ul>
        <li><strong>firmware.hex</strong> - Main gateway firmware binary in Intel HEX format</li>
        <li><strong>checksums</strong> - SHA256 checksums for firmware verification and integrity validation</li>
      </ul>
      <p><em>Always verify the checksum after downloading to ensure file integrity before updating your gateway.</em></p>
      
      <h2>Upgrade your Kickstarter gateway</h2>
      <p>To prepare a micro SD card for The Things Kickstarter Gateway firmware update, follow these steps:</p>
      <ol>
        <li>Download the <a href="checksums">checksums</a> file from this site</li>
        <li>Download the <a href="firmware.hex">firmware.hex</a> file from this site</li>
        <li>Copy both files to the root directory of a FAT32 formatted micro SD card</li>
        <li>Insert the SD card into your gateway and power it on to begin the firmware update</li>
      </ol>
      
      <h2>Configure Custom FOTA Server</h2>
      <p>After updating your gateway firmware, you need to configure it to use this custom FOTA server instead of the default TTI server:</p>
      <ol>
        <li>Connect to your gateway's configuration interface</li>
        <li>Navigate to the gateway settings page</li>
        <li>Click on <strong>"Show advanced options"</strong></li>
        <li>Check the box for <strong>"Override FOTA URL"</strong></li>
        <li>Enter the custom FOTA server URL: <code><?php echo htmlspecialchars($currentUrl); ?></code></li>
        <li>Save the settings</li>
      </ol>
      <p>Your gateway will now check this server for future firmware updates instead of the default TTI FOTA server.</p>
      
      <h2>More Information</h2>
      <p>For additional details and documentation:</p>
      <ul>
        <li>This FOTA Server: <a href="https://github.com/pe1mew/ttksgfotasrv" target="_blank">https://github.com/pe1mew/ttksgfotasrv</a></li>
        <li>Gateway Firmware served here: <a href="https://github.com/pe1mew/gateway/tree/upd-only" target="_blank">https://github.com/pe1mew/gateway/tree/upd-only</a></li>
      </ul>
      
      <h2>License</h2>
      <p>This FOTA server is provided under a <strong>Source-Available Non-Commercial License</strong>. You may use and modify for personal and non-commercial purposes only. Redistribution and commercial use are not permitted.</p>
      
      <h2>Disclaimer</h2>
      <p><em>This project is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.</em></p>
    </div>
  </body>
</html>
<?php
}
