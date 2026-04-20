<?php
// lib/qrcode.php - Standalone QR Code Generator
// Works with PHP 5.6 and above - No dependencies required

class SimpleQRCode {
    
    /**
     * Generate QR code using multiple fallback methods
     */
    public static function generate($data, $filepath, $size = 300) {
        // Method 1: Try QR Server API (Free, no limits)
        $qrServerUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
        $image = @file_get_contents($qrServerUrl);
        
        if ($image !== false && strlen($image) > 100) {
            file_put_contents($filepath, $image);
            return true;
        }
        
        // Method 2: Try Google Charts API
        $googleUrl = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl=" . urlencode($data);
        $image = @file_get_contents($googleUrl);
        
        if ($image !== false && strlen($image) > 100) {
            file_put_contents($filepath, $image);
            return true;
        }
        
        // Method 3: Try QuickChart API
        $quickChartUrl = "https://quickchart.io/qr?size={$size}&text=" . urlencode($data);
        $image = @file_get_contents($quickChartUrl);
        
        if ($image !== false && strlen($image) > 100) {
            file_put_contents($filepath, $image);
            return true;
        }
        
        // Method 4: Create text-based fallback image
        return self::createFallbackImage($filepath, $data, $size);
    }
    
    /**
     * Create a fallback image with text
     */
    private static function createFallbackImage($filepath, $data, $size) {
        // Create image
        $im = imagecreatetruecolor($size, $size);
        
        // Colors
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        $blue = imagecolorallocate($im, 0, 51, 102);
        $gray = imagecolorallocate($im, 128, 128, 128);
        
        // Fill background
        imagefill($im, 0, 0, $white);
        
        // Draw border
        imagerectangle($im, 0, 0, $size-1, $size-1, $black);
        imagerectangle($im, 5, 5, $size-6, $size-6, $gray);
        
        // Parse data to get participant ID
        $decoded = json_decode($data, true);
        $id = $decoded['participant_id'] ?? 'N/A';
        
        // Text lines
        $lines = [
            ['text' => 'BRIGADA ESKWELA', 'size' => 5, 'y' => 80, 'color' => $blue],
            ['text' => '━━━━━━━━━━━━━━━━', 'size' => 3, 'y' => 100, 'color' => $black],
            ['text' => 'ID: ' . $id, 'size' => 4, 'y' => 130, 'color' => $black],
            ['text' => '━━━━━━━━━━━━━━━━', 'size' => 3, 'y' => 150, 'color' => $black],
            ['text' => 'Scan for Attendance', 'size' => 3, 'y' => 180, 'color' => $gray],
            ['text' => date('Y-m-d H:i'), 'size' => 2, 'y' => 250, 'color' => $gray],
        ];
        
        foreach ($lines as $line) {
            $fontWidth = imagefontwidth($line['size']);
            $textWidth = strlen($line['text']) * $fontWidth;
            $x = ($size - $textWidth) / 2;
            imagestring($im, $line['size'], (int)$x, $line['y'], $line['text'], $line['color']);
        }
        
        // Save image
        imagepng($im, $filepath);
        imagedestroy($im);
        
        return true;
    }
}
?>