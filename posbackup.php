<?php
// Enhanced POS with Box-Style Layout and Categories for Calloway IMS
session_start();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// helper: JSON response for AJAX
function json_response($data){ header('Content-Type: application/json'); echo json_encode($data); exit; }

// Expanded products with categories (add more as needed)
$products = [
    // Headaches
    ['id' => 1, 'name' => 'Aspirin', 'price' => 5.99, 'icon' => '💊', 'category' => 'Headaches'],
    ['id' => 2, 'name' => 'Ibuprofen', 'price' => 4.49, 'icon' => '🔥', 'category' => 'Headaches'],
    // Fever
    ['id' => 3, 'name' => 'Acetaminophen', 'price' => 6.99, 'icon' => '🌡️', 'category' => 'Fever'],
    ['id' => 4, 'name' => 'Fever Reducer Syrup', 'price' => 8.49, 'icon' => '🧴', 'category' => 'Fever'],
    // Condoms
    ['id' => 5, 'name' => 'Condom Pack', 'price' => 12.99, 'icon' => '🛡️', 'category' => 'Condoms'],
    ['id' => 6, 'name' => 'Lubricated Condoms', 'price' => 14.99, 'icon' => '💦', 'category' => 'Condoms'],
    // Other (for variety)
    ['id' => 7, 'name' => 'Bandages', 'price' => 3.49, 'icon' => '🩹', 'category' => 'First Aid'],
    ['id' => 8, 'name' => 'Cough Syrup', 'price' => 7.99, 'icon' => '🍯', 'category' => 'Cough'],
];

// Get unique categories for tabs
$categories = array_unique(array_column($products, 'category'));

// Filter products by selected category (default: all)
$selectedCategory = $_GET['category'] ?? 'All';
$filteredProducts = $selectedCategory === 'All' ? $products : array_filter($products, fn($p) => $p['category'] === $selectedCategory);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $id = $_POST['product_id'];
        $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
        $message = "Added to cart!";
        if (isset($_POST['ajax'])) {
            // build cart html and return JSON
            ob_start();
            // ...render cart fragment...
            if (empty($_SESSION['cart'])) {
                echo '<p>Cart is empty. Add some items! 🛒</p>';
            } else {
                foreach ($_SESSION['cart'] as $idc => $qtyc) {
                    $matches = array_values(array_filter($products, fn($p) => $p['id'] == $idc)); $pc = $matches[0] ?? null; if (!$pc) continue;
                    echo '<div class="pos-cart-item"><span>'.htmlspecialchars($pc['icon']).' '.htmlspecialchars($pc['name']).' x'.(int)$qtyc.' - $'.number_format($pc['price']*$qtyc,2)."</span>";
                    echo '<form method="post" style="display:inline;"><input type="hidden" name="remove" value="'.(int)$idc.'"><button type="button" onclick="postFormAjax(this)">Remove</button></form></div>';
                }
                echo '<p class="pos-total">Total: $'.number_format(array_sum(array_map(function($idk,$q){ return ($GLOBALS["products"][array_search($idk,array_column($GLOBALS["products"],"id"))]["price"] ?? 0) * $q; }, array_keys($_SESSION['cart']), $_SESSION['cart'])),2).'</p>';
                echo '<form method="post"><button type="button" onclick="postFormAjax(this)">Checkout</button></form>';
            }
            $cart_html = ob_get_clean();
            json_response(['ok'=>true,'cart_html'=>$cart_html,'message'=>$message]);
        }
    } elseif (isset($_POST['remove'])) {
        $id = $_POST['remove'];
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] <= 0) unset($_SESSION['cart'][$id]);
            $message = "Removed from cart!";
            if (isset($_POST['ajax'])) {
                // reuse same fragment building as above
                ob_start();
                if (empty($_SESSION['cart'])) {
                    echo '<p>Cart is empty. Add some items! 🛒</p>';
                } else {
                    foreach ($_SESSION['cart'] as $idc => $qtyc) {
                        $matches = array_values(array_filter($products, fn($p) => $p['id'] == $idc)); $pc = $matches[0] ?? null; if (!$pc) continue;
                        echo '<div class="pos-cart-item"><span>'.htmlspecialchars($pc['icon']).' '.htmlspecialchars($pc['name']).' x'.(int)$qtyc.' - $'.number_format($pc['price']*$qtyc,2)."</span>";
                        echo '<form method="post" style="display:inline;"><input type="hidden" name="remove" value="'.(int)$idc.'"><button type="button" onclick="postFormAjax(this)">Remove</button></form></div>';
                    }
                    echo '<p class="pos-total">Total: $'.number_format(array_sum(array_map(function($idk,$q){ return ($GLOBALS["products"][array_search($idk,array_column($GLOBALS["products"],"id"))]["price"] ?? 0) * $q; }, array_keys($_SESSION['cart']), $_SESSION['cart'])),2).'</p>';
                    echo '<form method="post"><button type="button" onclick="postFormAjax(this)">Checkout</button></form>';
                }
                $cart_html = ob_get_clean();
                json_response(['ok'=>true,'cart_html'=>$cart_html,'message'=>$message]);
            }
        }
    } elseif (isset($_POST['checkout'])) {
        sleep(1); // Simulate delay
        $_SESSION['cart'] = [];
        $message = "Checkout successful! 🎉";
        $checkoutSuccess = true;
        if (isset($_POST['ajax'])) {
            ob_start(); echo '<p>Cart is empty. Add some items! 🛒</p>'; $cart_html = ob_get_clean(); json_response(['ok'=>true,'cart_html'=>$cart_html,'message'=>$message,'checkout'=>true]);
        }
    }
}

// Calculate totals
$total = 0;
$cartCount = 0;
foreach ($_SESSION['cart'] as $id => $qty) {
    $product = array_filter($products, fn($p) => $p['id'] == $id)[0] ?? null;
    if ($product) {
        $total += $product['price'] * $qty;
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
        <a href="#" class="back-button">← Back</a>
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
            <!-- Category Tabs -->
            <div class="pos-categories">
                <div class="pos-category-tab <?php echo $selectedCategory === 'All' ? 'active' : ''; ?>" onclick="filterCategory('All')">All</div>
                <?php foreach ($categories as $cat): ?>
                    <div class="pos-category-tab <?php echo $selectedCategory === $cat ? 'active' : ''; ?>" onclick="filterCategory(<?php echo json_encode($cat); ?>)"><?php echo htmlspecialchars($cat); ?></div>
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
                <h2>🛍️ Your Cart</h2>
                <?php if (empty($_SESSION['cart'])): ?>
                    <p>Cart is empty. Add some items! 🛒</p>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $id => $qty): ?>
                        <?php $matches = array_values(array_filter($products, fn($p) => $p['id'] == $id)); $product = $matches[0] ?? null; if (!$product) continue; ?>
                        <div class="pos-cart-item">
                            <span><?php echo htmlspecialchars($product['icon']); ?> <?php echo htmlspecialchars($product['name']); ?> x<?php echo (int)$qty; ?> - $<?php echo number_format($product['price'] * $qty, 2); ?></span>
                            <form method="post" style="display:inline;" onsubmit="return false;">
                                <input type="hidden" name="remove" value="<?php echo (int)$id; ?>">
                                <button type="button" data-action="remove" onclick="postFormAjax(this)">Remove</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <p class="pos-total">Total: $<?php echo number_format($total, 2); ?></p>
                    <form method="post" onsubmit="return false;">
                        <button type="button" data-action="checkout" onclick="postFormAjax(this)">Checkout</button>
                    </form>
                <?php endif; ?>
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
        <?php if (isset($checkoutSuccess) && $checkoutSuccess): ?>
            window.addEventListener('load', () => {
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
            });
        <?php endif; ?>
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
            try{
                button.disabled = true; const res = await fetch(location.pathname, { method: 'POST', body: fd}); const j = await res.json(); button.disabled = false;
                if (j.ok && j.cart_html!==undefined){ document.getElementById('cart').innerHTML = j.cart_html; if(j.message) showTempMessage(j.message); }
                if (j.checkout) { // show confetti
                    const confetti = document.getElementById('confetti'); if(confetti){ confetti.style.display='block'; setTimeout(()=>confetti.style.display='none',3000); }
                }
            }catch(err){ console.error(err); button.disabled=false; }
        }

        function showTempMessage(msg){ const el = document.createElement('div'); el.className='pos-temp-message'; el.innerText=msg; document.body.appendChild(el); setTimeout(()=>el.remove(),2500); }
    </script>
</body>
</html>
