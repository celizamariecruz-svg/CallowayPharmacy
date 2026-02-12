<?php
/**
 * Password Reset Utility
 * DELETE THIS FILE AFTER USE FOR SECURITY
 */

require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$users = [];

// Get existing users
$result = $conn->query("SELECT user_id, username, email, full_name, role_id FROM users ORDER BY user_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_password'])) {
        $user_id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];
        
        if (strlen($new_password) < 6) {
            $message = '<div class="error">Password must be at least 6 characters!</div>';
        } else {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hash, $user_id);
            
            if ($stmt->execute()) {
                $message = '<div class="success">✅ Password reset successfully! You can now login.</div>';
            } else {
                $message = '<div class="error">Error: ' . $conn->error . '</div>';
            }
        }
    }
    
    // Create new admin user
    if (isset($_POST['create_admin'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        
        if (empty($username) || empty($password)) {
            $message = '<div class="error">Username and password are required!</div>';
        } else {
            // Get admin role_id
            $role_result = $conn->query("SELECT role_id FROM roles WHERE role_name = 'admin' LIMIT 1");
            $role_id = 1;
            if ($role_result && $row = $role_result->fetch_assoc()) {
                $role_id = $row['role_id'];
            }
            
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssssi", $username, $email, $hash, $full_name, $role_id);
            
            if ($stmt->execute()) {
                $message = '<div class="success">✅ Admin user created! Username: ' . htmlspecialchars($username) . '</div>';
                // Refresh user list
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = '<div class="error">Error: ' . $conn->error . '</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Reset - Calloway Pharmacy</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f0f4f8; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #2563eb; text-align: center; }
        h2 { color: #333; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .success { color: #16a34a; background: #dcfce7; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .error { color: #dc2626; background: #fee2e2; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .warning { color: #d97706; background: #fef3c7; padding: 15px; border-radius: 8px; margin: 15px 0; }
        label { display: block; margin: 10px 0 5px; font-weight: 600; color: #333; }
        input, select { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        button { background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; width: 100%; }
        button:hover { background: #1d4ed8; }
        .user-list { background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .user-item { padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        .user-item:last-child { border-bottom: none; }
        .delete-warning { text-align: center; margin-top: 30px; padding: 15px; background: #fef2f2; border: 2px dashed #dc2626; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>🔐 Password Reset</h1>
    
    <?php echo $message; ?>
    
    <?php if (count($users) > 0): ?>
    <div class="card">
        <h2>Reset Existing User Password</h2>
        
        <div class="user-list">
            <strong>Existing Users:</strong>
            <?php foreach ($users as $user): ?>
            <div class="user-item">
                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                (<?php echo htmlspecialchars($user['email'] ?: 'No email'); ?>) - 
                <?php echo htmlspecialchars($user['full_name']); ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <form method="POST">
            <label>Select User:</label>
            <select name="user_id" required>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['user_id']; ?>">
                    <?php echo htmlspecialchars($user['username']); ?> - <?php echo htmlspecialchars($user['full_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <label>New Password:</label>
            <input type="password" name="new_password" required minlength="6" placeholder="Enter new password (min 6 characters)">
            
            <button type="submit" name="reset_password">Reset Password</button>
        </form>
    </div>
    <?php else: ?>
    <div class="warning">No users found in database. Create a new admin user below.</div>
    <?php endif; ?>
    
    <div class="card">
        <h2>Create New Admin User</h2>
        <form method="POST">
            <label>Username:</label>
            <input type="text" name="username" required placeholder="admin">
            
            <label>Email:</label>
            <input type="email" name="email" placeholder="admin@pharmacy.com">
            
            <label>Full Name:</label>
            <input type="text" name="full_name" placeholder="Administrator">
            
            <label>Password:</label>
            <input type="password" name="password" required minlength="6" placeholder="Enter password (min 6 characters)">
            
            <button type="submit" name="create_admin">Create Admin User</button>
        </form>
    </div>
    
    <div class="delete-warning">
        <strong>⚠️ SECURITY WARNING</strong><br>
        Delete this file after resetting your password!<br>
        <code>reset_password.php</code>
    </div>
</body>
</html>
