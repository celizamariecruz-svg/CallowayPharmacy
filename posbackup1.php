<?php
// calloway_pos_full.php
// Single-file improved POS for Calloway Pharmacy
// - Features: DB-backed products, stock validation, quantity controls, search, barcode input,
//   save transaction to DB (sales + sale_items), receipt view, payment modal (cash/card/mixed),
//   AJAX endpoints, prepared statements, simple CSRF token, printable receipt.
// - Requires: db_connection.php that sets $conn = new mysqli(...)

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Basic CSRF token for AJAX posts
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_token'];

require_once 'db_connection.php'; // must set $conn (mysqli)

// Helpers
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function json_response($data){ header('Content-Type: application/json'); echo json_encode($data); exit; }

// Function to render cart HTML
function renderCartHtml($cart, $products, $total, $cartCount) {
    $html = '<h2>🛍️ Your Cart</h2>';
    if (empty($cart)) {
        $html .= '<p>Cart is empty. Add some items! 🛒</p>';
    } else {
        foreach ($cart as $id => $qty) {
            $id = (int)$id; $qty = (int)$qty;
            $matches = array_values(array_filter($products, fn($p) => $p['id'] == $id));
            $product = $matches[0] ?? null;
            if (!$product) continue; // Skip if product not found
            $lineTotal = $product['price'] * $qty;
            $html .= '<div class="pos-cart-item">';
            $html .= '<span>' . h($product['icon']) . ' ' . h($product['name']) . ' x' . $qty . ' - $' . number_format($lineTotal, 2) . '</span>';
            $html .= '<form method="post" style="display:inline;" onsubmit="return false;">';
            $html .= '<input type="hidden" name="product_id" value="' . $id . '">';
            $html .= '<button type="button" data-action="remove" onclick="postFormAjax(this)">Remove</button>';
            $html .= '</form>';
            $html .= '</div>';
        }
        $html .= '<p class="pos-total">Total: $' . number_format($total, 2) . '</p>';
        $html .= '<form method="post" onsubmit="return false;">';
        $html .= '<button type="button" data-action="checkout" onclick="postFormAjax(this)">Checkout</button>';
        $html .= '</form>';
    }
    return $html;
}

// Load products from DB (all products)
$products = [];
$db_error = '';
$stmt = $conn->prepare("SELECT product_id AS id, name, COALESCE(category, 'General') AS category, price, stock_quantity, location FROM products ORDER BY name");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        // ensure types are normalized
        $r['id'] = (int)$r['id'];
        $r['price'] = (float)$r['price'];
        $r['stock_quantity'] = isset($r['stock_quantity']) ? (int)$r['stock_quantity'] : 0;
        $r['category'] = $r['category'] ?? 'General'; // ensure category exists
        $r['icon'] = '💊'; // default icon, can be customized per product in DB
        $products[] = $r;
    }
    $stmt->close();
} else {
    $db_error = "DB Error: " . $conn->error;
}

// If still no products, show sample data for testing
if (empty($products)) {
    $products = [
        ['id' => 1, 'name' => 'Aspirin', 'price' => 5.99, 'icon' => '💊', 'category' => 'General', 'stock_quantity' => 10, 'location' => 'Shelf A', 'barcode' => '123456'],
        ['id' => 2, 'name' => 'Ibuprofen', 'price' => 4.49, 'icon' => '💊', 'category' => 'General', 'stock_quantity' => 15, 'location' => 'Shelf A', 'barcode' => '123457'],
    ];
}

// index products by id for fast lookup
$productIndex = [];
foreach ($products as $p) $productIndex[$p['id']] = $p;

// categories
$categories = array_values(array_unique(array_map(fn($p)=>$p['category'],$products)));
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

// selected category and filtered products for legacy template sections
$selectedCategory = $_GET['category'] ?? 'All';
$filteredProducts = $selectedCategory === 'All' ? $products : array_filter($products, fn($p) => ($p['category'] ?? '') === $selectedCategory);

// handle AJAX endpoints (add, remove, update-qty, checkout, search, barcode)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    // check CSRF
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) json_response(['ok'=>false,'error'=>'Invalid CSRF token']);

    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        // validate product & stock
        if (!isset($productIndex[$pid])) return json_response(['ok'=>false,'error'=>'Product not found']);
        $product = $productIndex[$pid];
        if ($product['stock_quantity'] < $qty) {
            return json_response(['ok'=>false,'error'=>'Insufficient stock', 'stock'=>$product['stock_quantity']]);
        }
        $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
        // Recalculate totals
        $total = 0.0; $cartCount = 0;
        foreach ($_SESSION['cart'] as $id => $q) {
            $id = (int)$id; $q = (int)$q;
            if (isset($productIndex[$id])) {
                $total += $productIndex[$id]['price'] * $q;
                $cartCount += $q;
            }
        }
        $cartHtml = renderCartHtml($_SESSION['cart'], $products, $total, $cartCount);
        return json_response(['ok'=>true,'message'=>'Added','cart_html'=>$cartHtml]);
    }

    if ($action === 'update_qty') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(0, (int)($_POST['qty'] ?? 0));
        if (!isset($productIndex[$pid])) return json_response(['ok'=>false,'error'=>'Product not found']);
        $product = $productIndex[$pid];
        if ($qty > $product['stock_quantity']) return json_response(['ok'=>false,'error'=>'Insufficient stock','stock'=>$product['stock_quantity']]);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$pid]);
        } else {
            $_SESSION['cart'][$pid] = $qty;
        }
        // Recalculate totals
        $total = 0.0; $cartCount = 0;
        foreach ($_SESSION['cart'] as $id => $q) {
            $id = (int)$id; $q = (int)$q;
            if (isset($productIndex[$id])) {
                $total += $productIndex[$id]['price'] * $q;
                $cartCount += $q;
            }
        }
        $cartHtml = renderCartHtml($_SESSION['cart'], $products, $total, $cartCount);
        return json_response(['ok'=>true,'message'=>'Quantity updated','cart_html'=>$cartHtml]);
    }

    if ($action === 'remove') {
        $pid = (int)($_POST['product_id'] ?? 0);
        if (isset($_SESSION['cart'][$pid])) unset($_SESSION['cart'][$pid]);
        // Recalculate totals
        $total = 0.0; $cartCount = 0;
        foreach ($_SESSION['cart'] as $id => $q) {
            $id = (int)$id; $q = (int)$q;
            if (isset($productIndex[$id])) {
                $total += $productIndex[$id]['price'] * $q;
                $cartCount += $q;
            }
        }
        $cartHtml = renderCartHtml($_SESSION['cart'], $products, $total, $cartCount);
        return json_response(['ok'=>true,'message'=>'Removed','cart_html'=>$cartHtml]);
    }

    if ($action === 'search') {
        $q = trim($_POST['q'] ?? '');
        $result = [];
        if ($q !== '') {
            $qLow = mb_strtolower($q);
            foreach ($products as $p) {
                if (str_contains(mb_strtolower($p['name']), $qLow) || str_contains(mb_strtolower($p['category'] ?? ''), $qLow)) $result[] = $p;
            }
        }
        return json_response(['ok'=>true,'results'=>$result]);
    }

    if ($action === 'barcode') {
        $code = trim($_POST['code'] ?? '');
        // try to find product by barcode
        $stmt = $conn->prepare("SELECT product_id AS id, name, category, price, stock_quantity FROM products WHERE barcode = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $code); $stmt->execute(); $res = $stmt->get_result(); $p = $res->fetch_assoc(); $stmt->close();
            if ($p) {
                // normalized types
                $p['id'] = (int)$p['id']; $p['price'] = (float)$p['price']; $p['stock_quantity'] = (int)$p['stock_quantity'];
                return json_response(['ok'=>true,'product'=>$p]);
            }
        }
        return json_response(['ok'=>false,'error'=>'Barcode not found']);
    }

    if ($action === 'checkout') {
        // expected fields: payment_method, paid_amount (float), cashier (string)
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $paid_amount = (float)($_POST['paid_amount'] ?? 0);
        $cashier = substr(trim($_POST['cashier'] ?? 'POS'), 0, 100);

        // validate cart not empty
        if (empty($_SESSION['cart'])) return json_response(['ok'=>false,'error'=>'Cart is empty']);

        // re-load product stocks to validate again and compute totals (prevent race conditions)
        $conn->begin_transaction();
        try {
            // lock relevant rows
            $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
            $types = str_repeat('i', count($_SESSION['cart']));
            $ids = array_map('intval', array_keys($_SESSION['cart']));

            // fetch latest stock and price for those ids
            $stmt = $conn->prepare("SELECT product_id, name, price, stock_quantity FROM products WHERE product_id IN ($placeholders) FOR UPDATE");
            if (!$stmt) throw new Exception('DB prepare error');
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            $fresh = [];
            while ($r = $res->fetch_assoc()) $fresh[(int)$r['product_id']] = $r;
            $stmt->close();

            // validate stock & compute totals
            $total = 0.0;
            foreach ($_SESSION['cart'] as $pid => $qty) {
                $pid = (int)$pid; $qty = (int)$qty;
                if (!isset($fresh[$pid])) throw new Exception("Product $pid not found");
                if ($fresh[$pid]['stock_quantity'] < $qty) throw new Exception("Insufficient stock for {$fresh[$pid]['name']}");
                $total += ((float)$fresh[$pid]['price']) * $qty;
            }

            // check payment validity
            if ($paid_amount < 0) throw new Exception('Invalid paid amount');

            $change = max(0.0, $paid_amount - $total);

            // insert sale
            $sale_ref = 'SALE-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
            $stmt = $conn->prepare("INSERT INTO sales (sale_reference, total, payment_method, paid_amount, change_amount, cashier) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception('DB prepare error insert sale');
            $stmt->bind_param('sdidss', $sale_ref, $total, $payment_method, $paid_amount, $change, $cashier);
            $stmt->execute();
            $sale_id = $stmt->insert_id;
            $stmt->close();

            // insert sale_items and deduct stock
            $stmtIns = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtUpd = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
            if (!$stmtIns || !$stmtUpd) throw new Exception('DB prepare error insert items/update stock');

            foreach ($_SESSION['cart'] as $pid => $qty) {
                $p = $fresh[(int)$pid];
                $unit_price = (float)$p['price'];
                $line_total = $unit_price * (int)$qty;
                $name = $p['name'];
                $stmtIns->bind_param('iisdid', $sale_id, $pid, $name, $unit_price, $qty, $line_total);
                $stmtIns->execute();

                $stmtUpd->bind_param('ii', $qty, $pid);
                $stmtUpd->execute();
            }

            // commit
            $conn->commit();

            // clear cart
            $_SESSION['cart'] = [];

            // return success with sale id and reference
            return json_response(['ok'=>true,'message'=>'Checkout successful','sale_id'=>$sale_id,'sale_ref'=>$sale_ref,'total'=>number_format($total,2),'checkout'=>true]);
        } catch (Exception $e) {
            $conn->rollback();
            return json_response(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }

    // unknown action
    return json_response(['ok'=>false,'error'=>'Unknown action']);
}

// Non-AJAX POST handling (for backward compatibility) - e.g., direct add via form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    // check CSRF for non-AJAX too
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Invalid CSRF token';
    } else {
        // simple add (qty = 1)
        if (isset($_POST['add']) && isset($_POST['product_id'])) {
            $pid = (int)$_POST['product_id'];
            if (isset($productIndex[$pid])) {
                $p = $productIndex[$pid];
                if ($p['stock_quantity'] > 0) {
                    $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
                } else {
                    $message = 'Out of stock';
                }
            }
        }
        if (isset($_POST['remove']) && isset($_POST['product_id'])) {
            $pid = (int)$_POST['product_id'];
            unset($_SESSION['cart'][$pid]);
        }
    }
}

// totals for rendering
$total = 0.0; $cartCount = 0;
foreach ($_SESSION['cart'] as $id => $qty) {
    $id = (int)$id; $qty = (int)$qty;
    if (isset($productIndex[$id])) {
        $total += $productIndex[$id]['price'] * $qty;
        $cartCount += $qty;
    }
}

?>
            <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calloway Pharmacy POS - Categorized & Boxy</title>
    <link rel="stylesheet" href="styles.css"> <!-- Your merged CSS -->
    <style>
        /* Enhanced POS styles for box layout and categories */
        .pos-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .pos-categories {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .pos-category-tab {
            padding: 0.75rem 1.5rem;
            background: var(--card-bg);
            border: 2px solid var(--input-border);
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: var(--text-color);
        }

        .pos-category-tab.active, .pos-category-tab:hover {
            background: var(--primary-color);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .pos-products {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            padding: 1rem;
        }

        .pos-product-box {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px var(--shadow-color);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            transform: translateY(30px) scale(0.9);
            animation: posBoxReveal 0.6s ease-out forwards;
        }

        .pos-product-box:nth-child(1) { animation-delay: 0.1s; }
        .pos-product-box:nth-child(2) { animation-delay: 0.2s; }
        .pos-product-box:nth-child(3) { animation-delay: 0.3s; }
        .pos-product-box:nth-child(4) { animation-delay: 0.4s; }
        .pos-product-box:nth-child(5) { animation-delay: 0.5s; }
        .pos-product-box:nth-child(6) { animation-delay: 0.6s; }
        .pos-product-box:nth-child(7) { animation-delay: 0.7s; }
        .pos-product-box:nth-child(8) { animation-delay: 0.8s; }

        @keyframes posBoxReveal {
            0% { opacity: 0; transform: translateY(30px) scale(0.9); }
            50% { opacity: 1; transform: translateY(-5px) scale(1.05); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .pos-product-box:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 12px 24px var(--shadow-color);
        }

        .pos-product-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .pos-product-name {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .pos-product-price {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .pos-product-box button {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
        }

        /* Cart enhancements */
        .pos-cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--hover-bg);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            animation: posSlideInRight 0.4s ease-out;
        }

        /* Confetti and other animations remain from previous */
        .confetti-piece {
            animation: fall 3s linear infinite;
        }

        .pos-total {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-shadow: 0 0 10px var(--primary-color);
            animation: glow 1s ease-in-out infinite alternate;
        }

        /* Responsive for boxes */
        @media (max-width: 768px) {
            .pos-products {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Your IMS header -->
    <header>
        <a href="index.html" class="back-button">← Back</a>
        <h1>Calloway Pharmacy IMS</h1>
        <div class="dropdown">
            <button class="dropdown-toggle">Menu ▼</button>
            <div class="dropdown-menu">
                <a href="#" class="dropdown-item">Dashboard</a>
                <a href="#" class="dropdown-item">Inventory</a>
                <div class="divider"></div>
                <a href="#" class="dropdown-item">Logout</a>
            </div>
        </div>
    </header>

    <main>
        <!-- POS Section with Categories and Box Layout -->
        <div class="pos-container pos-fade-in">
            <?php if ($db_error): ?>
                <div style="background:#fee;padding:1rem;color:#c00;margin:1rem;border-radius:8px;"><strong>⚠️ <?php echo h($db_error); ?></strong></div>
            <?php endif; ?>
            <!-- Category Tabs -->
            <div class="pos-categories">
                <div class="pos-category-tab <?php echo $selectedCategory === 'All' ? 'active' : ''; ?>" onclick="filterCategory('All')">All</div>
                <?php foreach ($categories as $cat): $enc = json_encode($cat); ?>
                    <div class="pos-category-tab <?php echo $selectedCategory === $cat ? 'active' : ''; ?>" onclick='filterCategory(<?php echo $enc; ?>)'><?php echo htmlspecialchars($cat); ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Products in Box Grid -->
            <div class="pos-products">
                <?php foreach ($filteredProducts as $index => $product): ?>
                    <div class="pos-product-box" style="animation-delay: <?php echo ($index + 1) * 0.1; ?>s;">
                        <div class="pos-product-icon"><?php echo htmlspecialchars($product['icon']); ?></div>
                        <div class="pos-product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="pos-product-price">$<?php echo number_format($product['price'], 2); ?></div>
                        <form method="post" style="display:inline;" onsubmit="return false;">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="button" data-action="add" onclick="postFormAjax(this)">Add to Cart</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Cart Sidebar -->
            <div class="pos-cart" id="cart">
                <?php echo renderCartHtml($_SESSION['cart'], $products, $total, $cartCount); ?>
                <?php if (isset($message)): ?>
                    <p class="pos-message"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Confetti -->
        <div class="confetti" id="confetti" style="display: none;"></div>
    </main>

    <!-- Your IMS footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-nav">
                <a href="#">Home</a>
                <a href="#">About</a>
                <a href="#">Contact</a>
            </div>
            <div class="footer-copyright">
                &copy; 2023 Calloway Pharmacy IMS. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Dropdown toggle
        document.querySelector('.dropdown-toggle').addEventListener('click', function() {
            document.querySelector('.dropdown-menu').classList.toggle('show');
        });

        // Category filtering with smooth transition
        function filterCategory(category) {
            window.location.href = '?category=' + encodeURIComponent(category);
        }

        // POS cart toggle
        function toggleCart() {
            const cart = document.getElementById('cart');
            cart.classList.toggle('open');
        }

        // Loading effect
        function showLoading(button) {
            button.classList.add('loading');
            setTimeout(() => button.classList.remove('loading'), 1000);
        }

        // Confetti on success
        window.addEventListener('load', () => {
            // Check for checkout success (set via AJAX response)
            if (window.checkoutSuccess) {
                const confetti = document.getElementById('confetti');
                confetti.style.display = 'block';
                for (let i = 0; i < 50; i++) {
                    const piece = document.createElement('div');
                    piece.className = 'confetti-piece';
                    piece.style.left = Math.random() * 100 + '%';
                    piece.style.animationDelay = Math.random() * 3 + 's';
                    piece.style.background = ['red', 'blue', 'green', 'yellow', 'purple'][Math.floor(Math.random() * 5)];
                    confetti.appendChild(piece);
                }
                setTimeout(() => confetti.style.display = 'none', 3000);
            }
        });
    </script>
    <script>
        // Submit the closest form via AJAX and update cart fragment
        async function postFormAjax(button){
            const form = button.closest('form');
            if (!form) return;
            const fd = new FormData(form);
            // if button has data-action, set the appropriate field
            const action = button.dataset.action;
            if (action === 'add') fd.append('add','1');
            if (action === 'remove') fd.append('remove', fd.get('remove') || '');
            if (action === 'checkout') fd.append('checkout','1');
            fd.append('ajax','1');
            fd.append('csrf', '<?php echo $CSRF; ?>'); // Add CSRF token
            try{
                button.disabled = true; const res = await fetch(location.pathname, { method: 'POST', body: fd}); const j = await res.json(); button.disabled = false;
                if (j.ok && j.cart_html!==undefined){ document.getElementById('cart').innerHTML = j.cart_html; if(j.message) showTempMessage(j.message); }
                if (j.checkout) { window.checkoutSuccess = true; // Trigger confetti
                    const confetti = document.getElementById('confetti'); if(confetti){ confetti.style.display='block'; setTimeout(()=>confetti.style.display='none',3000); }
                }
            }catch(err){ console.error(err); button.disabled=false; }
        }

        function showTempMessage(msg){ const el = document.createElement('div'); el.className='pos-temp-message'; el.innerText=msg; document.body.appendChild(el); setTimeout(()=>el.remove(),2500); }
    </script>
</body>
</html>
