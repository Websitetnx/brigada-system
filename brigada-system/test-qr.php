<?php
// test-qr.php
require_once 'config/database.php';

echo "<h2>🧪 QR Code Generation Test</h2>";

// Test with different IDs
$test_ids = [1001, 1002, 1003];

foreach ($test_ids as $id) {
    echo "<div style='display:inline-block; margin:20px; text-align:center;'>";
    
    $result = generateQRCode($id);
    
    if ($result) {
        echo "<p style='color:green'>✓ Participant ID: {$id}</p>";
        echo "<img src='qrcodes/{$result}' style='border:2px solid #ccc; padding:10px; width:200px;'>";
    } else {
        echo "<p style='color:red'>✗ Failed for ID: {$id}</p>";
    }
    
    echo "</div>";
}

echo "<hr>";
echo "<h3>System Check:</h3>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . " " . (version_compare(phpversion(), '7.0', '>=') ? '✓' : '⚠️') . "</li>";
echo "<li>GD Library: " . (extension_loaded('gd') ? '✓ Enabled' : '✗ Not enabled') . "</li>";
echo "<li>QR Codes Directory: " . (is_writable('qrcodes') ? '✓ Writable' : '✗ Not writable') . "</li>";
echo "<li>allow_url_fopen: " . (ini_get('allow_url_fopen') ? '✓ Enabled' : '⚠️ Disabled (fallback will work)') . "</li>";
echo "</ul>";

if (!extension_loaded('gd')) {
    echo "<p style='color:orange'>⚠️ GD Library not enabled. QR codes will be downloaded from API.</p>";
}
?>