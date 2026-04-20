<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-qrcode text-primary"></i> QR Code Scanner</h2>
        <div>
            <a href="attendance.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Attendance
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Scanner Section -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-camera"></i> Scan QR Code</h5>
                </div>
                <div class="card-body">
                    <!-- Camera Selection -->
                    <div class="mb-3">
                        <label class="form-label">Select Camera</label>
                        <select class="form-select" id="cameraSelect" onchange="onCameraChange()">
                            <option value="">Loading cameras...</option>
                        </select>
                        <small class="text-muted">If no cameras appear, please ensure camera permissions are granted</small>
                    </div>
                    
                    <!-- Scanner Area -->
                    <div class="scanner-container" style="position: relative; max-width: 500px; margin: 0 auto;">
                        <div id="reader" style="width: 100%; min-height: 300px; border-radius: 10px; overflow: hidden; background: #f0f0f0;">
                            <div style="display: flex; align-items: center; justify-content: center; height: 300px; color: #999;">
                                <div>
                                    <i class="fas fa-camera fa-3x mb-3"></i>
                                    <p>Camera preview will appear here</p>
                                    <p><small>Select a camera and click Start Scanner</small></p>
                                </div>
                            </div>
                        </div>
                        <div id="scanOverlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); border-radius: 10px; justify-content: center; align-items: center;">
                            <div class="text-center text-white">
                                <div class="spinner-border mb-3" role="status"></div>
                                <h5>Processing...</h5>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Scanner Controls -->
                    <div class="text-center mt-3">
                        <button class="btn btn-success" id="startScanBtn" onclick="startScanner()">
                            <i class="fas fa-play"></i> Start Scanner
                        </button>
                        <button class="btn btn-danger" id="stopScanBtn" onclick="stopScanner()" style="display: none;">
                            <i class="fas fa-stop"></i> Stop Scanner
                        </button>
                        <button class="btn btn-secondary" onclick="toggleFileUpload()">
                            <i class="fas fa-upload"></i> Upload QR Image
                        </button>
                    </div>
                    
                    <!-- Status Message -->
                    <div id="cameraStatus" class="mt-3" style="display: none;"></div>
                    
                    <!-- File Upload Option -->
                    <div id="fileUploadSection" style="display: none;" class="mt-3">
                        <hr>
                        <label class="form-label">Select QR code image file:</label>
                        <input type="file" class="form-control" id="qrFileInput" accept="image/*" onchange="scanUploadedImage(this)">
                    </div>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Instructions</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Allow camera access when prompted by your browser</li>
                        <li>Select your camera from the dropdown above</li>
                        <li>Click <strong>Start Scanner</strong> to begin</li>
                        <li>Position the QR code within the camera view</li>
                        <li>Hold steady until the scanner reads the code</li>
                        <li>If camera doesn't work, use the <strong>Upload QR Image</strong> option</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Scan Result Section -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Scan Result</h5>
                </div>
                <div class="card-body">
                    <div id="scanResult">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-qrcode fa-3x mb-3"></i>
                            <p>Scan a QR code to see the result here</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Manual Entry -->
            <div class="card mt-3">
                <div class="card-header bg-warning">
                    <h6 class="mb-0"><i class="fas fa-keyboard"></i> Manual Entry</h6>
                </div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="text" class="form-control" id="manualIdInput" 
                               placeholder="Enter Participant ID">
                        <button class="btn btn-primary" onclick="processManualEntry()">
                            <i class="fas fa-check"></i> Process
                        </button>
                    </div>
                    <small class="text-muted">Use this if QR code cannot be scanned</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HTML5 QR Code Library -->
<script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>

<script>
let html5QrCode = null;
let isScanning = false;
let availableCameras = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initCameraList();
    
    // Check if browser supports camera
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showCameraStatus('Your browser does not support camera access. Please use the Upload QR Image option.', 'warning');
        document.getElementById('cameraSelect').innerHTML = '<option value="">Camera not supported</option>';
    }
});

// Initialize camera list
function initCameraList() {
    const select = document.getElementById('cameraSelect');
    const statusDiv = document.getElementById('cameraStatus');
    
    // Request camera permission first
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => {
            // Permission granted, stop the stream
            stream.getTracks().forEach(track => track.stop());
            
            // Now get camera list
            Html5Qrcode.getCameras()
                .then(devices => {
                    availableCameras = devices;
                    
                    if (devices && devices.length > 0) {
                        let options = '';
                        devices.forEach((device, index) => {
                            const label = device.label || `Camera ${index + 1}`;
                            options += `<option value="${device.id}">${label}</option>`;
                        });
                        select.innerHTML = options;
                        
                        showCameraStatus(`${devices.length} camera(s) found. Select one and click Start Scanner.`, 'success');
                        
                        // Auto-select back camera if available
                        const backCamera = devices.find(d => d.label && d.label.toLowerCase().includes('back'));
                        if (backCamera) {
                            select.value = backCamera.id;
                        }
                    } else {
                        select.innerHTML = '<option value="">No cameras found</option>';
                        showCameraStatus('No cameras detected. Please connect a camera or use Upload QR Image.', 'warning');
                    }
                })
                .catch(err => {
                    console.error('Camera enumeration error:', err);
                    select.innerHTML = '<option value="">Error loading cameras</option>';
                    showCameraStatus('Could not load camera list. Please check permissions.', 'danger');
                });
        })
        .catch(err => {
            console.error('Camera permission error:', err);
            select.innerHTML = '<option value="">Camera access denied</option>';
            showCameraStatus('Camera access was denied. Please grant permission and refresh, or use Upload QR Image.', 'danger');
        });
}

// Show camera status
function showCameraStatus(message, type) {
    const statusDiv = document.getElementById('cameraStatus');
    const alertClass = {
        'success': 'alert-success',
        'warning': 'alert-warning',
        'danger': 'alert-danger'
    }[type] || 'alert-info';
    
    statusDiv.className = `alert ${alertClass}`;
    statusDiv.innerHTML = message;
    statusDiv.style.display = 'block';
}

// Handle camera change
function onCameraChange() {
    // If scanner is running, restart with new camera
    if (isScanning) {
        stopScanner();
        setTimeout(() => startScanner(), 500);
    }
}

// Start QR scanner
function startScanner() {
    const cameraId = document.getElementById('cameraSelect').value;
    
    if (!cameraId) {
        showCameraStatus('Please select a camera first', 'warning');
        return;
    }
    
    // Clear previous instance
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
        }).catch(() => {});
    }
    
    const readerElement = document.getElementById('reader');
    readerElement.innerHTML = ''; // Clear placeholder
    
    html5QrCode = new Html5Qrcode("reader");
    isScanning = true;
    
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        rememberLastUsedCamera: false
    };
    
    html5QrCode.start(
        cameraId,
        config,
        onScanSuccess,
        onScanError
    ).then(() => {
        document.getElementById('startScanBtn').style.display = 'none';
        document.getElementById('stopScanBtn').style.display = 'inline-block';
        showCameraStatus('Scanner active. Position QR code in view.', 'success');
    }).catch(err => {
        console.error('Scanner start error:', err);
        isScanning = false;
        
        let errorMsg = 'Failed to start scanner. ';
        if (err.message && err.message.includes('Permission')) {
            errorMsg += 'Camera permission denied.';
        } else if (err.message && err.message.includes('in use')) {
            errorMsg += 'Camera is already in use by another application.';
        } else {
            errorMsg += err.message || 'Unknown error';
        }
        
        showCameraStatus(errorMsg, 'danger');
        
        // Reset reader
        readerElement.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 300px; color: #999;">
                <div>
                    <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                    <p>Camera could not be started</p>
                    <p><small>Please try Upload QR Image instead</small></p>
                </div>
            </div>
        `;
    });
}

// Stop QR scanner
function stopScanner() {
    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
            isScanning = false;
            document.getElementById('startScanBtn').style.display = 'inline-block';
            document.getElementById('stopScanBtn').style.display = 'none';
            showCameraStatus('Scanner stopped', 'info');
            
            // Restore placeholder
            document.getElementById('reader').innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 300px; color: #999;">
                    <div>
                        <i class="fas fa-camera fa-3x mb-3"></i>
                        <p>Camera preview will appear here</p>
                        <p><small>Select a camera and click Start Scanner</small></p>
                    </div>
                </div>
            `;
        }).catch(err => {
            console.error('Scanner stop error:', err);
        });
    }
}

// Handle successful scan
function onScanSuccess(decodedText, decodedResult) {
    if (!isScanning) return;
    
    // Pause scanner
    if (html5QrCode) {
        html5QrCode.pause();
    }
    
    // Show processing overlay
    document.getElementById('scanOverlay').style.display = 'flex';
    
    // Process the scan
    processQRData(decodedText);
}

// Handle scan error
function onScanError(error) {
    // Silent fail - this is called continuously while scanning
    // Only log critical errors
    if (error && error.message && !error.message.includes('No QR code found')) {
        console.warn('Scan error:', error);
    }
}

// Process QR data
function processQRData(qrData) {
    let participantId = null;
    
    try {
        // Try to parse as JSON
        const parsed = JSON.parse(qrData);
        participantId = parsed.participant_id;
    } catch (e) {
        // If not JSON, check if it's just a number
        if (/^\d+$/.test(qrData)) {
            participantId = qrData;
        } else {
            // Try to extract ID
            const match = qrData.match(/participant[_-]?(\d+)/i);
            if (match) {
                participantId = match[1];
            }
        }
    }
    
    if (participantId) {
        processAttendance(participantId, qrData);
    } else {
        hideScanOverlay();
        displayErrorResult('Invalid QR code format. Could not find participant ID.');
        
        if (html5QrCode && isScanning) {
            html5QrCode.resume();
        }
    }
}

// Process attendance
function processAttendance(participantId, rawData = '') {
    fetch('api/attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            participant_identifier: participantId,
            qr_data: rawData
        })
    })
    .then(response => response.json())
    .then(data => {
        hideScanOverlay();
        
        if (data.success) {
            displaySuccessResult(data);
            
            // Resume scanning after success
            setTimeout(() => {
                if (html5QrCode && isScanning) {
                    html5QrCode.resume();
                }
            }, 2000);
        } else {
            displayErrorResult(data.message || 'Error processing attendance');
            
            setTimeout(() => {
                if (html5QrCode && isScanning) {
                    html5QrCode.resume();
                }
            }, 2000);
        }
    })
    .catch(error => {
        hideScanOverlay();
        displayErrorResult('Network error occurred');
        console.error('Error:', error);
        
        setTimeout(() => {
            if (html5QrCode && isScanning) {
                html5QrCode.resume();
            }
        }, 2000);
    });
}

// Display success result
function displaySuccessResult(data) {
    const actionIcon = data.action === 'sign_in' ? 'sign-in-alt' : 'sign-out-alt';
    const actionColor = data.action === 'sign_in' ? 'success' : 'info';
    const statusColor = data.status === 'Complete' ? 'success' : 'warning';
    
    let html = `
        <div class="alert alert-${actionColor}">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-${actionIcon} fa-2x me-3"></i>
                <div>
                    <h5 class="mb-0">${data.message}</h5>
                    <small>${new Date().toLocaleTimeString()}</small>
                </div>
            </div>
            <hr>
            <div class="participant-details">
                <h6>${escapeHtml(data.participant_name || 'Participant')}</h6>
    `;
    
    if (data.duration) {
        html += `<p class="mb-1"><strong>Duration:</strong> ${data.duration}</p>`;
    }
    
    if (data.status) {
        html += `<p class="mb-1">
            <strong>Status:</strong> 
            <span class="badge bg-${statusColor}">${data.status}</span>
        </p>`;
    }
    
    if (data.penalty_applied) {
        html += `<p class="mb-1 text-danger">
            <i class="fas fa-exclamation-circle"></i> 
            <strong>Penalty Applied:</strong> ₱200.00
        </p>`;
    }
    
    html += `</div></div>`;
    
    document.getElementById('scanResult').innerHTML = html;
}

// Display error result
function displayErrorResult(message) {
    document.getElementById('scanResult').innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error:</strong> ${escapeHtml(message)}
            <br><small>Please try again or use manual entry</small>
        </div>
    `;
}

// Hide scan overlay
function hideScanOverlay() {
    document.getElementById('scanOverlay').style.display = 'none';
}

// Toggle file upload
function toggleFileUpload() {
    const section = document.getElementById('fileUploadSection');
    section.style.display = section.style.display === 'none' ? 'block' : 'none';
    
    if (section.style.display === 'block' && isScanning) {
        stopScanner();
    }
}

let fileScanner = null;

// Scan uploaded image
function scanUploadedImage(input) {
    const file = input.files[0];
    
    if (!file) return;
    
    showCameraStatus('Scanning image...', 'info');
    
    if (!document.getElementById('file-reader')) {
        const fileReaderDiv = document.createElement('div');
        fileReaderDiv.id = 'file-reader';
        fileReaderDiv.style.display = 'none';
        document.body.appendChild(fileReaderDiv);
    }
    
    if (fileScanner) {
        try { fileScanner.clear(); } catch(e) {}
    }
    
    fileScanner = new Html5Qrcode("file-reader");
    
    fileScanner.scanFile(file, false)
        .then(decodedText => {
            showCameraStatus('QR code detected!', 'success');
            processQRData(decodedText);
            input.value = '';
            document.getElementById('fileUploadSection').style.display = 'none';
            try { fileScanner.clear(); } catch(e) {}
            fileScanner = null;
        })
        .catch(err => {
            console.error('File scan error:', err);
            showCameraStatus('Could not read QR code from image. Please try another image.', 'danger');
            input.value = '';
            try { fileScanner.clear(); } catch(e) {}
            fileScanner = null;
        });
}

// Process manual entry
function processManualEntry() {
    const input = document.getElementById('manualIdInput');
    const id = input.value.trim();
    
    if (!id) {
        alert('Please enter participant ID');
        return;
    }
    
    showCameraStatus('Processing manual entry...', 'info');
    processAttendance(id);
    input.value = '';
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (html5QrCode && isScanning) {
        html5QrCode.stop().catch(() => {});
    }
});
</script>

<style>
#reader {
    border: 3px solid #007bff;
    border-radius: 10px;
    overflow: hidden;
    background: #000;
}

#reader video {
    border-radius: 7px;
    width: 100%;
    height: auto;
}

.scanner-container {
    position: relative;
}

#scanOverlay {
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    #reader {
        max-width: 100%;
    }
}
</style>


<?php include 'includes/footer.php'; ?>