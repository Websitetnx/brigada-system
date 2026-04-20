<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$payment_id = $_GET['id'] ?? 0;

// Get payment details with participant info (fixed - removed created_by and users join)
$stmt = $db->prepare("
    SELECT 
        pay.*,
        CONCAT(p.first_name, ' ', p.last_name) as participant_name,
        p.role,
        p.grade_section,
        p.guardian_name,
        p.guardian_contact,
        p.contact_number as participant_contact,
        a.date as attendance_date,
        a.total_minutes as minutes_served
    FROM payments pay
    JOIN participants p ON pay.participant_id = p.id
    LEFT JOIN attendance a ON pay.attendance_id = a.id
    WHERE pay.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die('<div style="text-align:center; padding:50px; font-family:Arial;">
        <h2>Receipt Not Found</h2>
        <p>The payment record you are looking for does not exist.</p>
        <a href="payments.php">Back to Payments</a>
    </div>');
}

// Get school settings
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('school_name', 'school_address', 'contact_number')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $settings = [];
}

$school_name = $settings['school_name'] ?? 'Brigada Eskwela Elementary School';
$school_address = $settings['school_address'] ?? '123 Education St., Sample City';
$school_contact = $settings['contact_number'] ?? '(02) 1234-5678';

// Format amount in words
function numberToWords($number) {
    $words = [
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
        70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    ];
    
    if ($number < 21) return $words[$number];
    if ($number < 100) {
        $tens = floor($number / 10) * 10;
        $ones = $number % 10;
        return $words[$tens] . ($ones > 0 ? '-' . $words[$ones] : '');
    }
    if ($number < 1000) {
        $hundreds = floor($number / 100);
        $remainder = $number % 100;
        return $words[$hundreds] . ' Hundred' . ($remainder > 0 ? ' ' . numberToWords($remainder) : '');
    }
    if ($number < 1000000) {
        $thousands = floor($number / 1000);
        $remainder = $number % 1000;
        return numberToWords($thousands) . ' Thousand' . ($remainder > 0 ? ' ' . numberToWords($remainder) : '');
    }
    return $number;
}

$pesos = floor($payment['amount']);
$centavos = round(($payment['amount'] - $pesos) * 100);
$amount_in_words = numberToWords($pesos) . ' Pesos';
if ($centavos > 0) {
    $amount_in_words .= ' and ' . numberToWords($centavos) . ' Centavos';
}
$amount_in_words .= ' Only';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($payment['receipt_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #e8ecef; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: 'Courier New', Courier, monospace; }
        .receipt-container { max-width: 450px; width: 100%; }
        .receipt { background: white; padding: 25px 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); position: relative; border: 1px solid #ddd; }
        .receipt-header { text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px dashed #ccc; }
        .school-name { font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #2c3e50; margin-bottom: 3px; }
        .school-address { font-size: 11px; color: #666; margin-bottom: 2px; }
        .school-contact { font-size: 11px; color: #666; margin-bottom: 8px; }
        .receipt-title { font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #2c3e50; margin: 8px 0; padding: 4px; border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; }
        .receipt-number { font-size: 12px; color: #555; margin-top: 5px; }
        .receipt-body { margin-bottom: 20px; font-size: 13px; }
        .info-row { display: flex; padding: 4px 0; }
        .info-label { width: 100px; font-weight: 600; color: #555; }
        .info-value { flex: 1; color: #333; }
        .divider { border-top: 1px dashed #ccc; margin: 12px 0; }
        .amount-section { background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 12px 0; text-align: center; }
        .amount-label { font-size: 11px; color: #666; text-transform: uppercase; }
        .amount-value { font-size: 30px; font-weight: bold; color: #2c3e50; }
        .amount-words { font-size: 11px; color: #666; margin-top: 4px; text-transform: uppercase; }
        .payment-method { display: inline-block; padding: 2px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; background: #e3f2fd; color: #1976d2; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; }
        .status-Paid { background: #d4edda; color: #155724; }
        .status-Pending { background: #fff3cd; color: #856404; }
        .signature-section { margin-top: 25px; display: flex; justify-content: space-between; }
        .signature-line { text-align: center; width: 45%; }
        .signature-placeholder { height: 35px; }
        .signature-label { border-top: 1px solid #333; padding-top: 4px; font-size: 10px; color: #666; }
        .receipt-footer { margin-top: 20px; padding-top: 12px; border-top: 2px dashed #ccc; text-align: center; font-size: 10px; color: #888; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 60px; color: rgba(200,200,200,0.12); white-space: nowrap; pointer-events: none; text-transform: uppercase; font-weight: bold; z-index: 1; }
        .btn-group { display: flex; gap: 8px; margin-top: 15px; }
        .btn { flex: 1; padding: 10px; border-radius: 6px; font-weight: 600; font-family: 'Segoe UI', sans-serif; font-size: 13px; }
        .btn-primary { background: #2c3e50; border: none; color: white; }
        .btn-outline-secondary { border: 1px solid #6c757d; background: transparent; color: #6c757d; }
        @media print { body { background: white; padding: 0; } .receipt { box-shadow: none; border: 1px solid #000; } .no-print { display: none !important; } .watermark { display: none; } @page { margin: 0.3cm; } }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt">
            <?php if ($payment['status'] === 'Paid'): ?>
            <div class="watermark">PAID</div>
            <?php elseif ($payment['status'] === 'Pending'): ?>
            <div class="watermark">PENDING</div>
            <?php endif; ?>
            
            <div class="receipt-header">
                <div class="school-name"><?php echo htmlspecialchars($school_name); ?></div>
                <div class="school-address"><?php echo htmlspecialchars($school_address); ?></div>
                <div class="school-contact">Tel: <?php echo htmlspecialchars($school_contact); ?></div>
                <div class="receipt-title">OFFICIAL RECEIPT</div>
                <div class="receipt-number">Receipt #: <strong><?php echo htmlspecialchars($payment['receipt_number']); ?></strong></div>
            </div>
            
            <div class="receipt-body">
                <div class="info-row"><span class="info-label">Date:</span><span class="info-value"><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></span></div>
                <div class="info-row"><span class="info-label">Time:</span><span class="info-value"><?php echo date('g:i A', strtotime($payment['created_at'] ?? 'now')); ?></span></div>
                <div class="divider"></div>
                <div class="info-row"><span class="info-label">Received From:</span><span class="info-value"><strong><?php echo htmlspecialchars($payment['participant_name']); ?></strong></span></div>
                <div class="info-row"><span class="info-label">Role:</span><span class="info-value"><?php echo htmlspecialchars($payment['role']); ?></span></div>
                <?php if ($payment['grade_section']): ?>
                <div class="info-row"><span class="info-label">Grade/Section:</span><span class="info-value"><?php echo htmlspecialchars($payment['grade_section']); ?></span></div>
                <?php endif; ?>
                <?php if ($payment['guardian_name']): ?>
                <div class="info-row"><span class="info-label">Guardian:</span><span class="info-value"><?php echo htmlspecialchars($payment['guardian_name']); ?></span></div>
                <?php endif; ?>
                <?php if ($payment['attendance_date']): ?>
                <div class="info-row"><span class="info-label">Attendance:</span><span class="info-value"><?php echo date('M j, Y', strtotime($payment['attendance_date'])); ?> (<?php echo $payment['minutes_served']; ?> mins)</span></div>
                <?php endif; ?>
                <div class="divider"></div>
                
                <div class="amount-section">
                    <div class="amount-label">Amount Paid</div>
                    <div class="amount-value">₱<?php echo number_format($payment['amount'], 2); ?></div>
                    <div class="amount-words"><?php echo htmlspecialchars($amount_in_words); ?></div>
                </div>
                
                <div class="info-row"><span class="info-label">Method:</span><span class="info-value"><span class="payment-method"><?php echo htmlspecialchars($payment['payment_method']); ?></span></span></div>
                <?php if ($payment['reference_number']): ?>
                <div class="info-row"><span class="info-label">Reference #:</span><span class="info-value"><?php echo htmlspecialchars($payment['reference_number']); ?></span></div>
                <?php endif; ?>
                <div class="info-row"><span class="info-label">Status:</span><span class="info-value"><span class="status-badge status-<?php echo $payment['status']; ?>"><?php echo htmlspecialchars($payment['status']); ?></span></span></div>
                <?php if ($payment['notes']): ?>
                <div class="info-row"><span class="info-label">Notes:</span><span class="info-value"><em><?php echo htmlspecialchars($payment['notes']); ?></em></span></div>
                <?php endif; ?>
            </div>
            
            <div class="signature-section">
                <div class="signature-line"><div class="signature-placeholder"></div><div class="signature-label">Received By</div></div>
                <div class="signature-line"><div class="signature-placeholder"></div><div class="signature-label">Paid By</div></div>
            </div>
            
            <div class="receipt-footer">
                <p><strong>Thank you for your support!</strong></p>
                <p>Brigada Eskwela Committee</p>
                <p style="margin-top: 8px;">This receipt is system generated.</p>
                <p>Generated: <?php echo date('M j, Y g:i A'); ?></p>
            </div>
        </div>
        
        <div class="btn-group no-print">
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
            <button class="btn btn-outline-secondary" onclick="window.close()"><i class="fas fa-times me-2"></i>Close</button>
        </div>
    </div>
    
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1') setTimeout(() => window.print(), 300);
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') { e.preventDefault(); window.print(); }
            if (e.key === 'Escape') window.close();
        });
    </script>
</body>
</html>