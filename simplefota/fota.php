<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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
