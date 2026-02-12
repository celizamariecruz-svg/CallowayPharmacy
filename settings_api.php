<?php
/**
 * Settings API
 * Handles fetching and updating system settings
 */

require_once 'db_connection.php';
require_once 'Auth.php';

header('Content-Type: application/json');

$auth = new Auth($conn);
$auth->requireAuth();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Helper to get setting value
function getSetting($conn, $key, $default = '')
{
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

// Helper to update setting
function updateSetting($conn, $key, $value, $category = 'general')
{
    // Check if exists
    $stmt = $conn->prepare("SELECT setting_id FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $updateStmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $updateStmt->bind_param("ss", $value, $key);
        return $updateStmt->execute();
    } else {
        $insertStmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, category) VALUES (?, ?, ?)");
        $insertStmt->bind_param("sss", $key, $value, $category);
        return $insertStmt->execute();
    }
}

try {
    switch ($action) {
        case 'get_settings':
            // Publicly accessible settings/authorized user settings

            $settings = [];

            // Fetch all settings
            $result = $conn->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            // Set defaults if missing
            $defaults = [
                'company_name' => 'Calloway Pharmacy',
                'tax_rate' => '12.00',
                'currency_symbol' => '₱',
                'low_stock_threshold' => '20',
                'expiry_alert_days' => '30',
                'receipt_footer' => 'Thank you for shopping with us!'
            ];

            foreach ($defaults as $key => $val) {
                if (!isset($settings[$key])) {
                    $settings[$key] = $val;
                }
            }

            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
            break;

        case 'update_settings':
            if (!$auth->hasPermission('settings.edit')) {
                throw new Exception('Permission denied');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $conn->begin_transaction();

            try {
                if (isset($data['store_name']))
                    updateSetting($conn, 'company_name', $data['store_name'], 'company');
                if (isset($data['store_address']))
                    updateSetting($conn, 'store_address', $data['store_address'], 'company');
                if (isset($data['store_phone']))
                    updateSetting($conn, 'store_phone', $data['store_phone'], 'company');
                if (isset($data['store_email']))
                    updateSetting($conn, 'store_email', $data['store_email'], 'company');

                if (isset($data['tax_rate']))
                    updateSetting($conn, 'tax_rate', $data['tax_rate'], 'tax');
                if (isset($data['currency']))
                    updateSetting($conn, 'currency_symbol', $data['currency'] === 'PHP' ? '₱' : '$', 'tax');
                if (isset($data['receipt_footer']))
                    updateSetting($conn, 'receipt_footer', $data['receipt_footer'], 'tax');

                if (isset($data['low_stock_threshold']))
                    updateSetting($conn, 'low_stock_threshold', $data['low_stock_threshold'], 'alerts');
                if (isset($data['expiry_alert']))
                    updateSetting($conn, 'expiry_alert_days', $data['expiry_alert'], 'alerts');

                $conn->commit();

                $auth->logActivity($_SESSION['user_id'], 'UPDATE', 'Settings', 'Updated system settings');

                echo json_encode([
                    'success' => true,
                    'message' => 'Settings updated successfully'
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>