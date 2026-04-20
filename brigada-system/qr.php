<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$participant_id = $_GET['id'] ?? 0;

// Get participant details
$stmt = $db->prepare("
    SELECT p.*, 
           COALESCE(SUM(a.total_minutes), 0) as total_minutes_served,
           ROUND(COALESCE(SUM(a.total_minutes), 0) / 60, 1) as total_hours_served
    FROM participants p
    LEFT JOIN attendance a ON p.id = a.participant_id
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->execute([$participant_id]);
$participant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$participant) {
    die('Participant not found');
}

// Generate QR code data
$qr_data = json_encode([
    'participant_id' => $participant_id,
    'name' => $participant['first_name'] . ' ' . $participant['last_name'],
    'role' => $participant['role'],
    'type' => 'brigada_attendance',
    'version' => '1.0'
]);

// Check if QR code image already exists
$qr_image_path = __DIR__ . '/qrcodes/participant_' . $participant_id . '.png';
$qr_image_exists = file_exists($qr_image_path);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- QR Code Generator Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .qr-container {
            max-width: 450px;
            width: 100%;
        }
        
        .qr-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid #e0e0e0;
        }
        
        .school-header {
            margin-bottom: 20px;
        }
        
        .school-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 30px;
        }
        
        .school-header h3 {
            color: #333;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .school-header p {
            color: #666;
            font-size: 14px;
            margin-bottom: 0;
        }
        
        .qr-code-wrapper {
            background: white;
            padding: 20px;
            border-radius: 15px;
            display: inline-block;
            margin: 15px 0;
            border: 3px dashed #667eea;
        }
        
        #qrcode {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 250px;
        }
        
        #qrcode canvas,
        #qrcode img {
            max-width: 100%;
            height: auto;
        }
        
        .participant-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            width: 100px;
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .badge-role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-Student { background: #4CAF50; color: white; }
        .badge-Parent { background: #2196F3; color: white; }
        .badge-Teacher { background: #FF9800; color: white; }
        .badge-Volunteer { background: #9C27B0; color: white; }
        .badge-Guardian { background: #607D8B; color: white; }
        
        .instruction {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: #28a745;
            border: none;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            background: transparent;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
        }
        
        .alert-message {
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            display: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .footer-note {
            margin-top: 20px;
            color: #999;
            font-size: 12px;
        }
        
        @media print {
            body { background: white; padding: 0; }
            .qr-card { box-shadow: none; border: 2px solid #000; }
            .no-print { display: none !important; }
            .qr-code-wrapper { border: 2px solid #000; }
        }
        
        @media (max-width: 480px) {
            .qr-card { padding: 20px; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 4px; }
            .btn-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="qr-container">
        <div class="qr-card">
            
            <!-- School Header -->
            <div class="school-header">
                <div class="school-logo">
                    <i class="fas fa-school"></i>
                </div>
                <h3>Brigada Eskwela</h3>
                <p>Monitoring System - Official QR Code</p>
                <p><small>School Year 2024-2025</small></p>
            </div>
            
            <!-- Status Message -->
            <div id="statusMessage" class="alert-message"></div>
            
            <!-- QR Code Display -->
            <div class="qr-code-wrapper">
                <div id="qrcode">
                    <div style="padding: 20px;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Generating QR Code...</span>
                        </div>
                        <p class="mt-2">Generating QR Code...</p>
                    </div>
                </div>
            </div>
            
            <!-- Participant Information -->
            <div class="participant-info">
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-user me-2"></i>Name:</span>
                    <span class="info-value">
                        <strong><?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?></strong>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-id-card me-2"></i>ID:</span>
                    <span class="info-value"><?php echo str_pad($participant_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-tag me-2"></i>Role:</span>
                    <span class="info-value">
                        <span class="badge-role badge-<?php echo htmlspecialchars($participant['role']); ?>">
                            <?php echo htmlspecialchars($participant['role']); ?>
                        </span>
                    </span>
                </div>
                
                <?php if ($participant['grade_section']): ?>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-graduation-cap me-2"></i>Grade/Section:</span>
                    <span class="info-value"><?php echo htmlspecialchars($participant['grade_section']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($participant['guardian_name']): ?>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-user-shield me-2"></i>Guardian:</span>
                    <span class="info-value"><?php echo htmlspecialchars($participant['guardian_name']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-clock me-2"></i>Hours Served:</span>
                    <span class="info-value">
                        <strong><?php echo $participant['total_hours_served']; ?> hours</strong>
                        (<?php echo $participant['total_minutes_served']; ?> minutes)
                    </span>
                </div>
            </div>
            
            <!-- Instruction -->
            <div class="instruction no-print">
                <i class="fas fa-info-circle"></i>
                <strong>How to use:</strong><br>
                Present this QR code at the attendance station. 
                Staff will scan this code to record your sign in/out time.
            </div>
            
            <!-- Action Buttons -->
            <div class="btn-group no-print">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print QR Code
                </button>
                <button class="btn btn-success" id="downloadBtn" onclick="downloadQRCode()">
                    <i class="fas fa-download me-2"></i>Download
                </button>
                <button class="btn btn-outline-secondary" onclick="window.close()">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
            
            <!-- Footer -->
            <div class="footer-note">
                <i class="far fa-clock"></i> Generated: <?php echo date('F j, Y g:i A'); ?><br>
                This QR code is unique to this participant. Do not share.
            </div>
            
        </div>
    </div>
    
    <script>
        // QR Code Data
        const qrData = <?php echo json_encode($qr_data); ?>;
        const participantId = <?php echo $participant_id; ?>;
        let qrCodeGenerated = false;
        let qrCanvas = null;
        let qrImageUrl = null;
        
        // Check if server-side QR code exists
        const serverQRExists = <?php echo $qr_image_exists ? 'true' : 'false'; ?>;
        
        // Show status message
        function showMessage(message, type) {
            const msgDiv = document.getElementById('statusMessage');
            msgDiv.className = 'alert-message alert-' + type;
            msgDiv.textContent = message;
            msgDiv.style.display = 'block';
            
            setTimeout(() => {
                msgDiv.style.display = 'none';
            }, 3000);
        }
        
        // Generate QR Code when page loads
        window.onload = function() {
            generateQRCode();
        };
        
        function generateQRCode() {
            const qrcodeElement = document.getElementById('qrcode');
            
            // If server-side QR exists, load it first as backup
            if (serverQRExists) {
                const img = new Image();
                img.src = '../qrcodes/participant_' + participantId + '.png';
                img.onload = function() {
                    qrImageUrl = img.src;
                };
            }
            
            // Generate QR Code using library
            try {
                QRCode.toCanvas(JSON.stringify(qrData), {
                    width: 280,
                    margin: 2,
                    color: {
                        dark: '#000000',
                        light: '#ffffff'
                    },
                    errorCorrectionLevel: 'H'
                }, function(error, canvas) {
                    if (error) {
                        console.error('QR Code generation error:', error);
                        showFallbackQR();
                    } else {
                        qrcodeElement.innerHTML = '';
                        qrcodeElement.appendChild(canvas);
                        qrCanvas = canvas;
                        qrCodeGenerated = true;
                        
                        // Also create an image URL from canvas
                        try {
                            qrImageUrl = canvas.toDataURL('image/png');
                        } catch(e) {
                            console.warn('Could not create data URL:', e);
                        }
                        
                        showMessage('QR Code generated successfully!', 'success');
                    }
                });
            } catch(e) {
                console.error('QR Code error:', e);
                showFallbackQR();
            }
        }
        
        // Fallback method using API
        function showFallbackQR() {
            const qrcodeElement = document.getElementById('qrcode');
            const dataString = JSON.stringify(qrData);
            
            // Use QR Server API
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' + encodeURIComponent(dataString);
            
            img.onload = function() {
                qrcodeElement.innerHTML = '';
                qrcodeElement.appendChild(img);
                qrImageUrl = img.src;
                qrCodeGenerated = true;
                showMessage('QR Code ready!', 'success');
            };
            
            img.onerror = function() {
                // Try Google Charts API
                const img2 = new Image();
                img2.src = 'https://chart.googleapis.com/chart?chs=280x280&cht=qr&chl=' + encodeURIComponent(dataString);
                
                img2.onload = function() {
                    qrcodeElement.innerHTML = '';
                    qrcodeElement.appendChild(img2);
                    qrImageUrl = img2.src;
                    qrCodeGenerated = true;
                    showMessage('QR Code ready!', 'success');
                };
                
                img2.onerror = function() {
                    // Ultimate fallback - show text
                    qrcodeElement.innerHTML = `
                        <div style="padding: 20px; text-align: center; border: 2px solid #ccc; border-radius: 10px;">
                            <i class="fas fa-exclamation-triangle" style="color: orange; font-size: 30px;"></i>
                            <p style="margin-top: 10px;"><strong>QR Code Not Available</strong></p>
                            <p style="font-size: 12px;">Participant ID: ${qrData.participant_id}</p>
                            <p style="font-size: 12px;">Please use manual entry</p>
                        </div>
                    `;
                    qrCodeGenerated = false;
                    showMessage('Could not generate QR code. Please try again.', 'danger');
                };
            };
        }
        
        // Download QR Code
        function downloadQRCode() {
            const participantName = '<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $participant['first_name'] . '_' . $participant['last_name']); ?>';
            const filename = `QR_${participantId}_${participantName}.png`;
            
            // Method 1: Try from canvas
            if (qrCanvas) {
                try {
                    const link = document.createElement('a');
                    link.download = filename;
                    link.href = qrCanvas.toDataURL('image/png');
                    link.click();
                    showMessage('QR Code downloaded!', 'success');
                    return;
                } catch(e) {
                    console.warn('Canvas download failed:', e);
                }
            }
            
            // Method 2: Try from image URL
            if (qrImageUrl) {
                // Check if it's a data URL or remote URL
                if (qrImageUrl.startsWith('data:image')) {
                    const link = document.createElement('a');
                    link.download = filename;
                    link.href = qrImageUrl;
                    link.click();
                    showMessage('QR Code downloaded!', 'success');
                    return;
                } else {
                    // For remote URLs, fetch and convert
                    fetch(qrImageUrl)
                        .then(response => response.blob())
                        .then(blob => {
                            const url = URL.createObjectURL(blob);
                            const link = document.createElement('a');
                            link.download = filename;
                            link.href = url;
                            link.click();
                            URL.revokeObjectURL(url);
                            showMessage('QR Code downloaded!', 'success');
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            fallbackDownload(filename);
                        });
                    return;
                }
            }
            
            // Method 3: Check if server-side file exists
            if (serverQRExists) {
                const link = document.createElement('a');
                link.download = filename;
                link.href = '../qrcodes/participant_' + participantId + '.png';
                link.click();
                showMessage('QR Code downloaded!', 'success');
                return;
            }
            
            // Method 4: Try to capture from DOM
            const qrcodeDiv = document.getElementById('qrcode');
            const canvas = qrcodeDiv.querySelector('canvas');
            const img = qrcodeDiv.querySelector('img');
            
            if (canvas) {
                try {
                    const link = document.createElement('a');
                    link.download = filename;
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                    showMessage('QR Code downloaded!', 'success');
                    return;
                } catch(e) {}
            }
            
            if (img && img.src) {
                if (img.src.startsWith('data:image')) {
                    const link = document.createElement('a');
                    link.download = filename;
                    link.href = img.src;
                    link.click();
                    showMessage('QR Code downloaded!', 'success');
                    return;
                }
            }
            
            // All methods failed
            fallbackDownload(filename);
        }
        
        function fallbackDownload(filename) {
            // Last resort: Generate a simple text-based QR representation
            const canvas = document.createElement('canvas');
            canvas.width = 280;
            canvas.height = 280;
            const ctx = canvas.getContext('2d');
            
            // Draw background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, 280, 280);
            
            // Draw border
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 2;
            ctx.strokeRect(5, 5, 270, 270);
            
            // Add text
            ctx.fillStyle = '#000000';
            ctx.font = 'bold 16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('BRIGADA ESKWELA', 140, 120);
            
            ctx.font = '14px Arial';
            ctx.fillText('Participant ID: ' + participantId, 140, 150);
            ctx.fillText('Scan for Attendance', 140, 180);
            
            // Download
            try {
                const link = document.createElement('a');
                link.download = filename;
                link.href = canvas.toDataURL('image/png');
                link.click();
                showMessage('QR Code card downloaded!', 'success');
            } catch(e) {
                showMessage('Could not download QR code. Please use Print instead.', 'danger');
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                downloadQRCode();
            }
            
            if (e.key === 'Escape') {
                window.close();
            }
        });
        
        // Auto-print if URL has print parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1') {
            setTimeout(() => {
                if (qrCodeGenerated) {
                    window.print();
                } else {
                    setTimeout(() => window.print(), 1000);
                }
            }, 500);
        }
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>