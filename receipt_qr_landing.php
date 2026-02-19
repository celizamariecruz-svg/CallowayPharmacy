<?php
/**
 * Receipt QR Code Landing Page
 * When a customer scans the QR code on their POS receipt, they land here.
 * They choose: "I'm a Customer" or "I'm an Employee"
 *
 * Customer path → login.php?tab=register&reward_qr=CODE  (register & auto-earn points)
 * Employee path → loyalty_qr.php?scan=CODE               (staff adds points for customer)
 */

session_start();
require_once 'db_connection.php';

$qrCode = trim($_GET['code'] ?? '');
$error = '';
$qrInfo = null;

if (empty($qrCode)) {
    $error = 'No QR code provided. Please scan a valid receipt QR code.';
} else {
    // Validate QR code exists
    $stmt = $conn->prepare("SELECT qr_id, qr_code, points_value, is_redeemed, expires_at, created_at, sale_reference FROM reward_qr_codes WHERE qr_code = ? LIMIT 1");
    $stmt->bind_param("s", $qrCode);
    $stmt->execute();
    $qrInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$qrInfo) {
        $error = 'Invalid QR code. This code does not exist in our system.';
    } elseif ($qrInfo['is_redeemed']) {
        $error = 'This QR code has already been used. Each receipt QR code can only be redeemed once.';
    } elseif ($qrInfo['expires_at'] && strtotime($qrInfo['expires_at']) < time()) {
        $error = 'This QR code has expired. QR codes are valid for 30 days after purchase.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward QR Code - Calloway Pharmacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .landing-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-header {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        .card-header .logo {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
        }
        .card-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }
        .card-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .card-body {
            padding: 2rem;
        }
        .points-badge {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 16px;
            border: 2px solid #f59e0b;
        }
        .points-badge .points-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #d97706;
            line-height: 1;
        }
        .points-badge .points-label {
            font-size: 0.85rem;
            color: #92400e;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        .choice-label {
            text-align: center;
            font-size: 1.1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.25rem;
        }
        .choice-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .choice-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        .choice-btn:hover {
            border-color: #2563eb;
            background: #eff6ff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37,99,235,0.15);
        }
        .choice-btn .icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .choice-btn.customer .icon {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #2563eb;
        }
        .choice-btn.employee .icon {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #059669;
        }
        .choice-btn .text h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        .choice-btn .text p {
            font-size: 0.8rem;
            color: #6b7280;
            line-height: 1.3;
        }
        .error-card {
            text-align: center;
            padding: 2rem;
        }
        .error-card .error-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fef2f2;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }
        .error-card h2 {
            color: #dc2626;
            margin-bottom: 0.75rem;
            font-size: 1.2rem;
        }
        .error-card p {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        .error-card .home-link {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #2563eb;
            color: #fff;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .error-card .home-link:hover { background: #1d4ed8; }
        .receipt-ref {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.8rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="landing-card">
        <div class="card-header">
            <div class="logo">💊</div>
            <h1>Calloway Pharmacy</h1>
            <p>Loyalty Rewards Program</p>
        </div>

        <?php if ($error): ?>
            <div class="error-card">
                <div class="error-icon"><i class="fas fa-times-circle"></i></div>
                <h2>QR Code Issue</h2>
                <p><?php echo htmlspecialchars($error); ?></p>
                <a href="login.php" class="home-link">Go to Website</a>
            </div>
        <?php else: ?>
            <div class="card-body">
                <div class="points-badge">
                    <div class="points-number"><?php echo intval($qrInfo['points_value']); ?></div>
                    <div class="points-label">Loyalty Points Available</div>
                </div>

                <div class="choice-label">How would you like to claim your points?</div>

                <div class="choice-buttons">
                    <a href="login.php?tab=register&reward=<?php echo urlencode($qrCode); ?>" class="choice-btn customer">
                        <div class="icon"><i class="fas fa-user"></i></div>
                        <div class="text">
                            <h3>I'm a Customer</h3>
                            <p>Register or log in to earn points automatically on your account</p>
                        </div>
                    </a>

                    <a href="loyalty_qr.php?scan=<?php echo urlencode($qrCode); ?>" class="choice-btn employee">
                        <div class="icon"><i class="fas fa-user-tie"></i></div>
                        <div class="text">
                            <h3>I'm an Employee</h3>
                            <p>Add points to a customer's loyalty account (staff login required)</p>
                        </div>
                    </a>
                </div>

                <?php if (!empty($qrInfo['sale_reference'])): ?>
                    <div class="receipt-ref">
                        Receipt: <?php echo htmlspecialchars($qrInfo['sale_reference']); ?>
                        &bull; Expires: <?php echo date('M j, Y', strtotime($qrInfo['expires_at'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
