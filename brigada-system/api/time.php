<?php
/**
 * api/time.php
 * Returns the exact server date/time in the configured timezone.
 * Used by the sidebar clock so it always matches the server.
 */
ob_start();
require_once '../config/database.php';
ob_clean();

header('Content-Type: application/json');

// Build the offset string for JS
$tz     = date_default_timezone_get();
$offset = (new DateTime('now', new DateTimeZone($tz)))->getOffset(); // seconds
$sign   = $offset >= 0 ? '+' : '-';
$abs    = abs($offset);
$hh     = str_pad(floor($abs / 3600), 2, '0', STR_PAD_LEFT);
$mm     = str_pad(($abs % 3600) / 60, 2, '0', STR_PAD_LEFT);

echo json_encode([
    'server_time'      => date('Y-m-d\TH:i:s'),   // e.g. 2026-04-19T14:30:00
    'timezone'         => $tz,
    'offset_string'    => "{$sign}{$hh}:{$mm}",
    'formatted'        => date('l, F j, Y g:i:s A'),
    'timestamp_ms'     => (int)(microtime(true) * 1000),
]);
?>
