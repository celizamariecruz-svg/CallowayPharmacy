<?php
/**
 * Logout Handler
 * Logs out current user, clears server session, and clears client-side data
 */

require_once 'db_connection.php';
require_once 'Auth.php';

// Initialize Auth class
$auth = new Auth($conn);

// Capture role before logout for redirect logic
$wasCustomer = (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'customer');

// Logout user (clears all session data)
$auth->logout();

// Determine redirect target
$loginPage = 'login.php';
?>
<!DOCTYPE html>
<html>
<head><title>Logging out...</title></head>
<body>
<script>
// Clear customer cart/wishlist from localStorage to prevent session bleed
try {
  localStorage.removeItem('calloway_cart');
  localStorage.removeItem('calloway_wishlist');
  localStorage.removeItem('callowayCart');
  localStorage.removeItem('callowayWishlist');
} catch(e) {}
window.location.href = '<?php echo $loginPage; ?>';
</script>
<noscript>
<meta http-equiv="refresh" content="0;url=<?php echo $loginPage; ?>">
</noscript>
</body>
</html>
<?php exit; ?>
