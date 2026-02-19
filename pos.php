<?php
/**
 * Calloway Pharmacy - Point of Sale System v5.0
 * Rebuilt for cashier speed — not browsing
 */

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

$currentUser = $auth->getCurrentUser();
$page_title = 'Point of Sale';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <script>
    // Apply theme immediately to prevent flash
    (function() {
      const theme = localStorage.getItem('calloway_theme') || 'light';
      document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Calloway Pharmacy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="custom-modal.js?v=2"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-color);
            overflow: hidden;
        }

        /* === LAYOUT === */
        .pos-wrapper {
            display: grid;
            grid-template-columns: 1fr 400px;
            height: calc(100vh - 60px);
            margin-top: 60px;
            padding-top: 0;
        }

        /* === LEFT: PRODUCT CATALOG === */
        .pos-left {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-color);
        }

        /* -- Toolbar: search + categories pinned -- */
        .pos-toolbar {
            padding: 1rem 1.25rem 0;
            background: var(--bg-color);
            flex-shrink: 0;
            border-bottom: 1px solid var(--divider-color);
        }

        .search-row {
            display: flex;
            gap: 0.6rem;
            margin-bottom: 0.75rem;
        }

        .search-box {
            flex: 1;
            position: relative;
            min-width: 0;
        }

        .search-box i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.9rem;
            pointer-events: none;
            width: 1rem;
            text-align: center;
            line-height: 1;
            z-index: 1;
        }

        .search-input {
            width: 100%;
            padding: 0.7rem 2.9rem 0.7rem 1rem;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 0.85rem;
            background: var(--card-bg);
            color: var(--text-color);
            transition: border-color 0.15s;
            box-sizing: border-box;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.08);
        }

        .search-input::placeholder { color: var(--text-light); opacity: 0.7; }



        /* -- Category tabs -- */
        .cat-tabs {
            display: flex;
            gap: 0;
            overflow-x: auto;
            scrollbar-width: none;
            margin: 0 -1.25rem;
            padding: 0 1.25rem;
        }
        .cat-tabs::-webkit-scrollbar { display: none; }

        .cat-tab {
            padding: 0.55rem 1rem;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-light);
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s;
            letter-spacing: 0.01em;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            position: relative;
        }

        .cat-tab:hover {
            max-width: none;
            overflow: visible;
            z-index: 10;
        }

        .cat-tab:hover { color: var(--text-color); background: var(--card-bg); border-radius: 6px 6px 0 0; }

        .cat-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }

        /* -- Results info bar -- */
        .results-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1.25rem;
            font-size: 0.78rem;
            color: var(--text-light);
            flex-shrink: 0;
        }

        .results-bar .view-toggle {
            display: flex;
            gap: 0.25rem;
        }

        .view-toggle button {
            width: 28px;
            height: 28px;
            border: 1px solid var(--input-border);
            background: var(--card-bg);
            color: var(--text-light);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: all 0.15s;
        }

        .view-toggle button.active {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }

        /* -- Product grid (compact) -- */
        .products-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem 1.25rem 1.25rem;
        }

        .products-scroll::-webkit-scrollbar { width: 4px; }
        .products-scroll::-webkit-scrollbar-thumb {
            background: var(--divider-color);
            border-radius: 4px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
            gap: 0.6rem;
        }

        .products-grid.list-view {
            grid-template-columns: 1fr;
            gap: 0.35rem;
        }

        /* -- Product card (grid view) -- */
        .product-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 0.85rem;
            cursor: pointer;
            transition: border-color 0.15s, box-shadow 0.15s;
            border: 1px solid var(--divider-color);
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            user-select: none;
        }

        .product-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.1);
        }

        .product-card:active { transform: scale(0.97); transition: transform 0.08s; }

        .product-card .p-category {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-light);
            font-weight: 500;
        }

        .product-card .p-name {
            font-weight: 600;
            font-size: 0.88rem;
            line-height: 1.3;
            color: var(--text-color);
        }

        .product-card .p-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 0.3rem;
        }

        .product-card .p-price {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-color);
        }

        .product-card .p-stock {
            font-size: 0.7rem;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .p-stock.ok { color: var(--secondary-color); background: rgba(16, 185, 129, 0.08); }
        .p-stock.low { color: #d97706; background: rgba(217, 119, 6, 0.08); }
        .p-stock.out { color: var(--danger-color); background: rgba(239, 68, 68, 0.08); }

        .product-card.out-of-stock {
            opacity: 0.45;
            pointer-events: none;
        }

        .piece-sell-btn {
            background: var(--primary-light);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            border-radius: 6px;
            font-size: 0.7rem;
            padding: 3px 8px;
            margin-top: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
            pointer-events: auto;
        }
        .piece-sell-btn:hover {
            background: var(--primary-color);
            color: #fff;
        }

        /* -- Product card (list view) -- */
        .products-grid.list-view .product-card {
            flex-direction: row;
            align-items: center;
            padding: 0.6rem 0.85rem;
            gap: 0.75rem;
            border-radius: 8px;
        }

        .products-grid.list-view .p-category { display: none; }

        .products-grid.list-view .p-name {
            flex: 1;
            font-size: 0.85rem;
        }

        .products-grid.list-view .p-bottom {
            margin-top: 0;
            padding-top: 0;
            gap: 0.75rem;
        }

        .products-grid.list-view .p-price { font-size: 0.85rem; }

        /* Keyboard hint on search */
        .kbd {
            display: inline-block;
            padding: 0.1rem 0.4rem;
            font-size: 0.65rem;
            font-family: inherit;
            background: var(--bg-color);
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            color: var(--text-light);
            margin-left: 0.5rem;
            vertical-align: middle;
        }

        /* === RIGHT: CART PANEL === */
        .pos-right {
            background: var(--card-bg);
            border-left: 1px solid var(--divider-color);
            display: flex;
            flex-direction: column;
            height: 100%;
            z-index: 50;
        }

        .cart-header {
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--divider-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-header h2 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
        }

        .cart-header .item-count {
            font-size: 0.72rem;
            font-weight: 600;
            background: var(--primary-color);
            color: #fff;
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        .cart-header-actions {
            display: flex;
            gap: 0.4rem;
        }

        .cart-action-btn {
            color: var(--text-light);
            background: none;
            border: 1px solid var(--divider-color);
            cursor: pointer;
            padding: 0.35rem 0.5rem;
            border-radius: 6px;
            transition: all 0.15s;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .cart-action-btn:hover { border-color: var(--danger-color); color: var(--danger-color); }

        /* -- Cart items -- */
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem 0;
        }

        .cart-items::-webkit-scrollbar { width: 3px; }
        .cart-items::-webkit-scrollbar-thumb { background: var(--divider-color); border-radius: 3px; }

        .cart-item {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            border-bottom: 1px solid var(--divider-color);
            animation: cartSlide 0.2s ease-out;
        }

        .cart-item:last-child { border-bottom: none; }

        @keyframes cartSlide {
            from { opacity: 0; transform: translateX(10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .item-info h4 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-color);
            line-height: 1.3;
        }

        .item-info .item-unit-price {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.1rem;
        }

        .item-qty {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .qty-btn {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            border: 1px solid var(--divider-color);
            background: var(--bg-color);
            color: var(--text-color);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.12s;
            line-height: 1;
        }

        .qty-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }

        .qty-display {
            font-weight: 700;
            font-size: 0.9rem;
            min-width: 22px;
            text-align: center;
            color: var(--text-color);
        }

        .item-total {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-color);
            min-width: 65px;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .remove-btn {
            color: var(--text-light);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            font-size: 0.75rem;
            transition: color 0.12s;
            opacity: 0.4;
        }

        .cart-item:hover .remove-btn { opacity: 1; }
        .remove-btn:hover { color: var(--danger-color); }

        /* -- Empty state -- */
        .empty-cart-state {
            text-align: center;
            padding: 2.5rem 1.5rem;
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 0.75rem;
        }

        .empty-cart-state .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            opacity: 0.45;
        }

        .empty-cart-state p { font-size: 0.85rem; max-width: 200px; line-height: 1.5; }

        .empty-cart-state .shortcut-hints {
            margin-top: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.75rem;
            color: var(--text-light);
            opacity: 0.7;
        }

        /* -- Cart footer / totals -- */
        .cart-footer {
            padding: 1rem 1.25rem 1.25rem;
            background: var(--card-bg);
            border-top: 1px solid var(--divider-color);
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
            z-index: 1;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .summary-row span:last-child { font-variant-numeric: tabular-nums; }

        .discount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            position: relative;
            z-index: 5;
        }

        .discount-toggle-btn {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.45rem 0.85rem;
            border-radius: 8px;
            border: 1.5px solid var(--divider-color);
            background: var(--bg-color);
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
            font-size: 0.82rem;
            color: var(--text-light);
            font-weight: 600;
        }

        .discount-toggle-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(10, 116, 218, 0.06);
        }

        .discount-toggle-btn.active {
            border-color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }

        .discount-toggle-btn .toggle-track {
            width: 34px;
            height: 18px;
            border-radius: 9px;
            background: rgba(0,0,0,0.15);
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .discount-toggle-btn.active .toggle-track {
            background: #22c55e;
        }

        .discount-toggle-btn .toggle-thumb {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: white;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .discount-toggle-btn.active .toggle-thumb {
            transform: translateX(16px);
        }

        .discount-toggle-btn .discount-icon {
            font-size: 0.85rem;
        }

        .discount-amount {
            font-weight: 600;
            color: #dc2626;
            font-variant-numeric: tabular-nums;
        }

        .discount-amount.has-discount {
            color: #16a34a;
            font-weight: 700;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 0.6rem;
            padding-top: 0.6rem;
            border-top: 1px solid var(--divider-color);
            margin-bottom: 1rem;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-color);
        }

        .summary-total span:last-child { font-variant-numeric: tabular-nums; }

        .pay-btn {
            width: 100%;
            padding: 0.85rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .pay-btn:hover:not(:disabled) { opacity: 0.9; }
        .pay-btn:active:not(:disabled) { transform: scale(0.98); }

        .pay-btn:disabled {
            background: var(--divider-color);
            color: var(--text-light);
            cursor: not-allowed;
        }

        .pay-btn .pay-shortcut {
            font-size: 0.7rem;
            background: rgba(255,255,255,0.2);
            padding: 0.1rem 0.4rem;
            border-radius: 3px;
            font-weight: 500;
            margin-left: 0.25rem;
        }

        /* === MODALS === */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active { display: flex; animation: fadeIn 0.15s; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal-content {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 16px;
            width: 100%;
            max-width: 440px;
            position: relative;
            transform: scale(0.95);
            animation: modalPop 0.2s ease forwards;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.18);
        }

        @keyframes modalPop { to { transform: scale(1); } }

        .modal-content h2 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-color);
        }

        /* Payment modal specifics */
        .amount-due-display {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--bg-color);
            border-radius: 10px;
        }

        .amount-due-display .label {
            font-size: 0.78rem;
            color: var(--text-light);
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 500;
        }

        .amount-due-display .amount {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-color);
            font-variant-numeric: tabular-nums;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
            margin-bottom: 1.25rem;
        }

        .method-btn {
            padding: 0.7rem;
            border: 1.5px solid var(--divider-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-color);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.12s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .method-btn:hover { border-color: var(--primary-color); }

        .method-btn.active {
            border-color: var(--primary-color);
            background: rgba(var(--primary-rgb), 0.06);
            color: var(--primary-color);
        }

        .cash-input-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: var(--text-color);
        }

        .cash-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--input-border);
            border-radius: 8px;
            font-size: 1.3rem;
            font-weight: 700;
            background: var(--card-bg);
            color: var(--text-color);
            font-variant-numeric: tabular-nums;
            transition: border-color 0.15s;
        }

        .cash-input:focus { outline: none; border-color: var(--primary-color); }

        .quick-cash {
            display: flex;
            gap: 0.4rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }

        .quick-cash button {
            padding: 0.35rem 0.7rem;
            border: 1px solid var(--divider-color);
            border-radius: 6px;
            background: var(--bg-color);
            color: var(--text-color);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.12s;
        }

        .quick-cash button:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .change-display {
            margin-top: 0.75rem;
            padding: 0.6rem 0.85rem;
            background: var(--bg-color);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        .change-display .change-label { color: var(--text-light); }
        .change-display .change-amount { font-weight: 700; font-size: 1rem; font-variant-numeric: tabular-nums; }

        .modal-actions {
            display: flex;
            gap: 0.6rem;
            margin-top: 1.5rem;
        }

        .modal-actions .btn-confirm {
            flex: 2;
            padding: 0.8rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.15s;
        }

        .modal-actions .btn-confirm:hover:not(:disabled) { opacity: 0.9; }
        .modal-actions .btn-confirm:disabled { opacity: 0.4; cursor: not-allowed; }

        .modal-actions .btn-cancel {
            flex: 1;
            padding: 0.8rem;
            background: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--divider-color);
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.12s;
        }

        .modal-actions .btn-cancel:hover { border-color: var(--text-light); }

        /* Receipt modal */
        .receipt-success {
            width: 52px;
            height: 52px;
            background: #dcfce7;
            color: #16a34a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin: 0 auto 1.25rem;
        }

        .receipt-actions {
            display: flex;
            gap: 0.6rem;
            margin-top: 1.5rem;
        }

        .receipt-actions .btn-print {
            flex: 1;
            padding: 0.8rem;
            background: var(--text-color);
            color: var(--card-bg);
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: opacity 0.15s;
        }

        .receipt-actions .btn-print:hover { opacity: 0.85; }

        .receipt-actions .btn-new {
            flex: 1;
            padding: 0.8rem;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: opacity 0.15s;
        }

        .receipt-actions .btn-new:hover { opacity: 0.9; }

        /* Receipt content */
        .receipt {
            font-size: 0.85rem;
            color: var(--text-color);
            background: var(--card-bg);
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 1rem;
        }

        .receipt-header h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .receipt-meta {
            font-size: 0.75rem;
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .receipt-section {
            margin-top: 0.75rem;
        }

        .receipt-items {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .receipt-items th,
        .receipt-items td {
            padding: 0.35rem 0;
            border-bottom: 1px dashed var(--divider-color);
        }

        .receipt-items th {
            text-align: left;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-light);
        }

        .receipt-items td:last-child,
        .receipt-items th:last-child {
            text-align: right;
        }

        .receipt-totals {
            margin-top: 0.75rem;
            border-top: 1px solid var(--divider-color);
            padding-top: 0.5rem;
            display: grid;
            gap: 0.25rem;
        }

        .receipt-totals .row {
            display: flex;
            justify-content: space-between;
        }

        .receipt-totals .total {
            font-weight: 800;
            font-size: 1rem;
        }

        .receipt-footer {
            margin-top: 0.75rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-light);
        }

        /* No products state */
        .no-products {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .no-products i { font-size: 2rem; margin-bottom: 0.75rem; display: block; opacity: 0.3; }

        /* Keyboard shortcut bar */
        .shortcut-bar {
            display: flex;
            gap: 1.25rem;
            padding: 0.4rem 1.25rem;
            font-size: 0.7rem;
            color: var(--text-light);
            background: var(--bg-color);
            border-top: 1px solid var(--divider-color);
            flex-shrink: 0;
            opacity: 0.7;
        }

        .shortcut-bar span { display: flex; align-items: center; gap: 0.3rem; }
        .shortcut-bar kbd {
            display: inline-block;
            padding: 0.05rem 0.35rem;
            font-size: 0.65rem;
            font-family: inherit;
            background: var(--card-bg);
            border: 1px solid var(--divider-color);
            border-radius: 3px;
        }

        /* Toast override for POS */
        .toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            opacity: 0;
            z-index: 2000;
            pointer-events: none;
            transition: all 0.25s;
        }

        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }

        /* Print only the receipt content */
        @media print {
            body { background: #fff; }
            .pos-wrapper,
            .modal,
            .toast {
                display: none !important;
            }

            body.printing-receipt #receiptModal {
                display: flex !important;
                position: static;
                background: none;
                backdrop-filter: none;
            }

            body.printing-receipt #receiptModal .modal-content {
                box-shadow: none;
                border: 1px solid #e5e7eb;
                margin: 0;
                width: 100%;
                max-width: 320px;
            }

            body.printing-receipt .receipt-actions {
                display: none;
            }
        }

        /* ─── Thermal Printer Connect Button ─── */
        .printer-connect-btn {
            position: fixed;
            top: 12px;
            right: 130px;
            z-index: 9999;
            background: var(--card-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1.1rem;
            color: var(--text-light);
        }
        .printer-connect-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        .printer-connect-btn.connected {
            border-color: #16a34a;
            color: #16a34a;
        }
        .printer-status-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #dc2626;
        }
        .printer-connect-btn.connected .printer-status-dot {
            background: #16a34a;
            box-shadow: 0 0 6px rgba(22,163,74,0.5);
        }

        /* ─── Online Order Notifications ─── */
        .online-notif-bell {
            position: fixed;
            top: 12px;
            right: 80px;
            z-index: 9999;
            background: var(--card-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            color: var(--text-color);
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .online-notif-bell:hover { border-color: var(--primary-color); color: var(--primary-color); transform: scale(1.05); }

        .online-notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid var(--card-bg);
            animation: notifPulse 2s ease infinite;
        }
        .online-notif-badge.hidden { display: none; }

        @keyframes notifPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .online-notif-panel {
            position: fixed;
            top: 60px;
            right: 20px;
            width: 400px;
            max-height: 500px;
            background: var(--card-bg);
            border: 1px solid var(--input-border);
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            z-index: 9998;
            display: none;
            overflow: hidden;
        }
        .online-notif-panel.active { display: flex; flex-direction: column; }

        .notif-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--divider-color);
        }
        .notif-panel-header h3 {
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
        }
        .notif-mark-all {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
        }
        .notif-mark-all:hover { text-decoration: underline; }

        .notif-panel-body {
            flex: 1;
            overflow-y: auto;
            max-height: 400px;
        }

        .notif-item {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid var(--divider-color);
            cursor: pointer;
            transition: background 0.15s;
        }
        .notif-item:hover { background: rgba(var(--primary-rgb), 0.04); }
        .notif-item.unread { background: rgba(59, 130, 246, 0.06); border-left: 3px solid var(--primary-color); }

        .notif-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.3rem;
        }
        .notif-item-title {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-color);
        }
        .notif-item-time {
            font-size: 0.7rem;
            color: var(--text-light);
            white-space: nowrap;
            margin-left: 0.5rem;
        }
        .notif-item-body {
            font-size: 0.8rem;
            color: var(--text-light);
            line-height: 1.5;
            white-space: pre-line;
        }
        .notif-empty {
            padding: 2rem;
            text-align: center;
            color: var(--text-light);
            font-size: 0.88rem;
        }
        .notif-empty i { font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.4; }

        .notif-sound { display: none; }

        /* ─── POS Main Tab Switcher ─── */
        .pos-main-tabs {
            display: flex;
            gap: 0;
            background: var(--card-bg);
            border-bottom: 2px solid var(--divider-color);
            padding: 0 1.25rem;
            flex-shrink: 0;
        }
        .pos-main-tab {
            padding: 0.7rem 1.25rem;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-light);
            background: none;
            border: none;
            border-bottom: 2.5px solid transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.15s;
            position: relative;
            margin-bottom: -2px;
        }
        .pos-main-tab:hover { color: var(--text-color); }
        .pos-main-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        .pos-main-tab .tab-badge {
            background: #ef4444;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 800;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            animation: notifPulse 2s ease infinite;
        }
        .pos-main-tab .tab-badge.hidden { display: none; }

        /* ─── Online Orders Panel ─── */
        .online-orders-panel {
            display: none;
            flex-direction: column;
            flex: 1;
            overflow: hidden;
        }
        .online-orders-panel.active { display: flex; }

        .online-orders-toolbar {
            padding: 0.85rem 1.25rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
            border-bottom: 1px solid var(--divider-color);
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        .oo-filter-btn {
            padding: 0.4rem 0.85rem;
            border-radius: 20px;
            border: 1px solid var(--divider-color);
            background: var(--card-bg);
            color: var(--text-light);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .oo-filter-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }
        .oo-filter-btn.active {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }
        .oo-filter-btn .filter-count {
            display: inline-block;
            background: rgba(255,255,255,0.25);
            padding: 0 5px;
            border-radius: 8px;
            margin-left: 4px;
            font-size: 0.7rem;
        }
        .oo-filter-btn.active .filter-count {
            background: rgba(255,255,255,0.3);
        }
        .oo-refresh-btn {
            margin-left: auto;
            padding: 0.4rem 0.75rem;
            border: 1px solid var(--divider-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-light);
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s;
        }
        .oo-refresh-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }

        .online-orders-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem 1.25rem;
        }
        .online-orders-list::-webkit-scrollbar { width: 4px; }
        .online-orders-list::-webkit-scrollbar-thumb { background: var(--divider-color); border-radius: 4px; }

        .oo-card {
            background: var(--card-bg);
            border: 1px solid var(--divider-color);
            border-radius: 12px;
            padding: 1rem 1.15rem;
            margin-bottom: 0.65rem;
            transition: border-color 0.15s, box-shadow 0.15s;
            cursor: pointer;
        }
        .oo-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(var(--primary-rgb), 0.08);
        }
        .oo-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .oo-card-ref {
            font-weight: 700;
            font-size: 0.92rem;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .oo-card-ref i { color: var(--primary-color); font-size: 0.85rem; }
        .oo-status-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .oo-status-badge.pending { background: #fef3c7; color: #92400e; }
        .oo-status-badge.confirmed { background: #dbeafe; color: #1e40af; }
        .oo-status-badge.preparing { background: #e0e7ff; color: #3730a3; }
        .oo-status-badge.ready { background: #d1fae5; color: #065f46; }
        .oo-status-badge.completed { background: #f0fdf4; color: #166534; }
        .oo-status-badge.cancelled { background: #fee2e2; color: #991b1b; }

        .oo-card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem;
            font-size: 0.8rem;
            color: var(--text-light);
            margin-bottom: 0.6rem;
        }
        .oo-card-body span { display: flex; align-items: center; gap: 0.35rem; }
        .oo-card-body i { width: 14px; text-align: center; font-size: 0.75rem; }

        .oo-card-items {
            background: var(--bg-color);
            border-radius: 8px;
            padding: 0.6rem 0.85rem;
            margin-bottom: 0.6rem;
            display: none;
        }
        .oo-card.expanded .oo-card-items { display: block; }
        .oo-card-items .oo-item-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            padding: 0.25rem 0;
            color: var(--text-color);
        }
        .oo-card-items .oo-item-row:not(:last-child) { border-bottom: 1px dashed var(--divider-color); }
        .oo-card-items .oo-item-name { font-weight: 500; }
        .oo-card-items .oo-item-subtotal { font-weight: 600; font-variant-numeric: tabular-nums; }

        .oo-card-actions {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .oo-action-btn {
            padding: 0.4rem 0.85rem;
            border-radius: 8px;
            border: none;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .oo-action-btn.confirm-btn { background: #2563eb; color: #fff; }
        .oo-action-btn.confirm-btn:hover { background: #1d4ed8; }
        .oo-action-btn.prepare-btn { background: #7c3aed; color: #fff; }
        .oo-action-btn.prepare-btn:hover { background: #6d28d9; }
        .oo-action-btn.ready-btn { background: #059669; color: #fff; }
        .oo-action-btn.ready-btn:hover { background: #047857; }
        .oo-action-btn.complete-btn { background: #16a34a; color: #fff; }
        .oo-action-btn.complete-btn:hover { background: #15803d; }
        .oo-action-btn.cancel-btn { background: #fee2e2; color: #dc2626; }
        .oo-action-btn.cancel-btn:hover { background: #fecaca; }
        .oo-action-btn.view-btn { background: var(--bg-color); color: var(--text-color); border: 1px solid var(--divider-color); }
        .oo-action-btn.view-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }

        .oo-empty {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-light);
        }
        .oo-empty i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; opacity: 0.3; }
        .oo-empty p { font-size: 0.9rem; }

        /* Product panel visibility toggle */
        .pos-products-panel {
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow: hidden;
        }
        .pos-products-panel.hidden-panel { display: none; }
    </style>
</head>

<body data-cashier-name="<?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username'] ?? 'Cashier'); ?>">
    <?php include 'header-component.php'; ?>

    <!-- Thermal Printer Connect Button -->
    <div class="printer-connect-btn" id="printerConnectBtn" onclick="togglePrinterConnection()" title="Connect Thermal Printer">
        <i class="fas fa-print"></i>
        <span class="printer-status-dot" id="printerStatusDot"></span>
    </div>

    <!-- Online Order Notification Bell -->
    <div class="online-notif-bell" id="onlineNotifBell" onclick="togglePosNotifPanel()" title="Online Orders">
        <i class="fas fa-bell"></i>
        <span class="online-notif-badge hidden" id="posNotifBadge">0</span>
    </div>

    <!-- Notification Panel -->
    <div class="online-notif-panel" id="posNotifPanel">
        <div class="notif-panel-header">
            <h3><i class="fas fa-shopping-cart" style="color:var(--primary-color);"></i> Online Orders</h3>
            <button class="notif-mark-all" onclick="markAllPosNotifRead()">Mark all read</button>
        </div>
        <div class="notif-panel-body" id="posNotifPanelBody">
            <div class="notif-empty">
                <i class="fas fa-bell-slash"></i>
                No notifications yet
            </div>
        </div>
    </div>

    <div class="pos-wrapper">
        <!-- LEFT: PRODUCT CATALOG + ONLINE ORDERS -->
        <div class="pos-left">
            <!-- Main Tab Switcher -->
            <div class="pos-main-tabs">
                <button class="pos-main-tab active" onclick="switchPosTab('products', this)">
                    <i class="fas fa-boxes"></i> Products
                </button>
                <button class="pos-main-tab" onclick="switchPosTab('online-orders', this)" id="onlineOrdersTab">
                    <i class="fas fa-globe"></i> Online Orders
                    <span class="tab-badge hidden" id="onlineOrdersBadge">0</span>
                </button>
                <a href="loyalty_qr.php" class="pos-main-tab" style="text-decoration:none;" target="_blank">
                    <i class="fas fa-gift"></i> Loyalty & QR
                </a>
            </div>

            <!-- Products Panel -->
            <div class="pos-products-panel" id="productsPanel">
                <div class="pos-toolbar">
                    <div class="search-row">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="search-input" id="searchInput"
                                placeholder="Search products..." autofocus>
                        </div>

                    </div>
                    <div class="cat-tabs" id="categoryNav">
                        <button class="cat-tab active" data-category="all">All</button>
                    </div>
                </div>

                <div class="results-bar">
                    <span id="resultsCount">0 products</span>
                    <div class="view-toggle">
                        <button class="active" onclick="setView('grid', this)" title="Grid view">
                            <i class="fas fa-th"></i>
                        </button>
                        <button onclick="setView('list', this)" title="List view">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>

                <div class="products-scroll">
                    <div class="products-grid" id="productsGrid">
                        <div class="no-products"><i class="fas fa-spinner fa-spin"></i>Loading products...</div>
                    </div>
                </div>

                <div class="shortcut-bar">
                    <span><kbd>F2</kbd> Search</span>
                    <span><kbd>F4</kbd> Clear cart</span>
                    <span><kbd>F9</kbd> Pay</span>
                    <span><kbd>Esc</kbd> Close</span>
                </div>
            </div>

            <!-- Online Orders Panel -->
            <div class="online-orders-panel" id="onlineOrdersPanel">
                <div class="online-orders-toolbar">
                    <button class="oo-filter-btn active" onclick="filterOnlineOrders('all', this)">All</button>
                    <button class="oo-filter-btn" onclick="filterOnlineOrders('Pending', this)">🟡 Pending</button>
                    <button class="oo-filter-btn" onclick="filterOnlineOrders('Confirmed', this)">🔵 Confirmed</button>
                    <button class="oo-filter-btn" onclick="filterOnlineOrders('Preparing', this)">🟣 Preparing</button>
                    <button class="oo-filter-btn" onclick="filterOnlineOrders('Ready', this)">🟢 Ready</button>
                    <button class="oo-filter-btn" onclick="filterOnlineOrders('Completed', this)">✅ Done</button>
                    <button class="oo-refresh-btn" onclick="loadOnlineOrders()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="online-orders-list" id="onlineOrdersList">
                    <div class="oo-empty">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading online orders...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: CART -->
        <div class="pos-right">
            <div class="cart-header">
                <h2>
                    <i class="fas fa-receipt" style="color: var(--primary-color); font-size: 0.9rem;"></i>
                    Current Sale
                    <span class="item-count" id="cartCount" style="display: none;">0</span>
                </h2>
                <div class="cart-header-actions">
                    <button class="cart-action-btn" onclick="holdSale()" title="Hold sale">
                        <i class="fas fa-pause"></i>
                    </button>
                    <button class="cart-action-btn" onclick="showHeldSales()" title="Resume held sale" style="position:relative;">
                        <i class="fas fa-play"></i>
                        <span id="heldSalesBadge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;font-size:0.6rem;min-width:16px;height:16px;border-radius:8px;align-items:center;justify-content:center;font-weight:700;">0</span>
                    </button>
                    <button class="cart-action-btn" onclick="clearCart()" title="Clear all">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <div class="cart-items" id="cartItems">
                <div class="empty-cart-state">
                    <div class="empty-icon"><i class="fas fa-receipt"></i></div>
                    <p>Click a product or scan a barcode to start a sale</p>
                    <div class="shortcut-hints">
                        <span><kbd>F2</kbd> to search &nbsp;&middot;&nbsp; <kbd>F9</kbd> to pay</span>
                    </div>
                </div>
            </div>

            <div class="cart-footer">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="subtotalDisplay">₱0.00</span>
                </div>
                <div class="summary-row">
                    <span>VAT (12%)</span>
                    <span id="taxDisplay">₱0.00</span>
                </div>
                <div class="discount-row">
                    <button type="button" class="discount-toggle-btn" id="discountToggle" onclick="toggleDiscount()">
                        <div class="toggle-track"><div class="toggle-thumb"></div></div>
                        <span class="discount-icon"><i class="fas fa-id-card"></i></span>
                        <span>20% SC/PWD</span>
                    </button>
                    <span class="discount-amount" id="discountDisplay">-₱0.00</span>
                </div>
                <div class="summary-total">
                    <span>Total</span>
                    <span id="totalDisplay">₱0.00</span>
                </div>
                <button class="pay-btn" id="payBtn" onclick="openPaymentModal()" disabled>
                    <i class="fas fa-coins"></i> Process Payment
                    <span class="pay-shortcut">F9</span>
                </button>
            </div>
        </div>
    </div>

    <!-- PAYMENT MODAL -->
    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <h2>Process Payment</h2>

            <div class="amount-due-display">
                <div class="label">Amount Due</div>
                <div class="amount" id="modalTotalDisplay">₱0.00</div>
            </div>

            <div class="payment-methods">
                <button class="method-btn active" onclick="setPaymentMethod('cash', this)">
                    <i class="fas fa-money-bill-wave"></i> Cash
                </button>
                <button class="method-btn" onclick="setPaymentMethod('card', this)">
                    <i class="fas fa-credit-card"></i> Card
                </button>
            </div>

            <div id="cashInputGroup" class="cash-input-group">
                <label>Cash Tendered</label>
                <input type="number" class="cash-input" id="amountTendered" placeholder="0.00"
                    step="0.01" min="0" inputmode="decimal">
                <div class="quick-cash" id="quickCashBtns"></div>
                <div class="change-display">
                    <span class="change-label">Change</span>
                    <span class="change-amount" id="changeDisplay">₱0.00</span>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-confirm" onclick="completeSale()" id="completeSaleBtn">
                    Confirm Payment
                </button>
                <button class="btn-cancel" onclick="closePaymentModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal" id="receiptModal">
        <div class="modal-content">
            <div class="receipt-success"><i class="fas fa-check"></i></div>
            <div class="receipt" id="receiptContent">
                <!-- Receipt content is injected by JS -->
            </div>
            <div class="receipt-actions">
                <button class="btn-print" onclick="printReceipt()" id="btnPrintReceipt">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn-new" onclick="newSale()">
                    <i class="fas fa-plus"></i> New Sale
                </button>
            </div>
            <div id="printerHint" style="text-align:center;font-size:0.75rem;color:var(--text-light);margin-top:0.5rem;display:none;">
                <i class="fas fa-info-circle"></i> Click the <i class="fas fa-print"></i> icon in the top bar to connect your thermal printer
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script src="theme.js"></script>
    <script src="shared-polish.js"></script>
    <script>
        // === POS v5 — Built for Speed ===
        let products = [];
        let cart = [];
        let currentCategory = 'all';
        let paymentMethod = 'cash';
        let viewMode = 'grid';
        let lastSaleData = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadProducts();
            setupEventListeners();
            updatePrinterUI();
            updateHeldBadge();
        });

        // --- Data ---
        async function loadProducts() {
            try {
                const res = await fetch('inventory_api.php?action=get_products&limit=1000');
                const data = await res.json();
                if (data.success) {
                    products = data.data || [];
                    console.log(`POS: Loaded ${products.length} products`);
                    renderCategories();
                    renderProducts();
                } else {
                    console.error('POS: API returned error:', data.message || 'Unknown error');
                    document.getElementById('productsGrid').innerHTML =
                        '<div class="no-products"><i class="fas fa-exclamation-triangle"></i> ' + (data.message || 'Failed to load products') + '</div>';
                }
            } catch (err) {
                console.error("Failed to load products", err);
                document.getElementById('productsGrid').innerHTML =
                    '<div class="no-products"><i class="fas fa-exclamation-triangle"></i> Failed to load products. Check console for details.</div>';
            }
        }

        // --- Event Listeners ---
        function setupEventListeners() {
            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => renderProducts(e.target.value), 120);
            });

            document.getElementById('amountTendered').addEventListener('input', updateChange);

            // Keyboard shortcuts — cashier speed
            document.addEventListener('keydown', (e) => {
                // Don't trigger if typing in an input
                const tag = document.activeElement.tagName;
                const isInput = tag === 'INPUT' || tag === 'TEXTAREA';

                if (e.key === 'F2') {
                    e.preventDefault();
                    document.getElementById('searchInput').focus();
                    document.getElementById('searchInput').select();
                }
                if (e.key === 'F4') {
                    e.preventDefault();
                    clearCart();
                }
                if (e.key === 'F9') {
                    e.preventDefault();
                    if (cart.length > 0) {
                        if (document.getElementById('paymentModal').classList.contains('active')) {
                            completeSale();
                        } else {
                            openPaymentModal();
                        }
                    }
                }
                if (e.key === 'Escape') {
                    closePaymentModal();
                    document.getElementById('receiptModal').classList.remove('active');
                }

                // Enter in payment = confirm
                if (e.key === 'Enter' && document.getElementById('paymentModal').classList.contains('active')) {
                    e.preventDefault();
                    completeSale();
                }
            });
        }

        // --- Categories ---
        function renderCategories() {
            const cats = ['all', ...new Set(products.map(p => p.category_name))].filter(Boolean);
            const nav = document.getElementById('categoryNav');
            nav.innerHTML = cats.map(cat => {
                const count = cat === 'all' ? products.length : products.filter(p => p.category_name === cat).length;
                const label = cat === 'all' ? 'All' : cat;
                return `<button class="cat-tab ${cat === 'all' ? 'active' : ''}" title="${label} (${count})"
                    onclick="setCategory('${cat.replace(/'/g, "\\'")}', this)">
                    ${label} <span style="opacity:0.5;font-size:0.7rem;margin-left:2px;">${count}</span>
                </button>`;
            }).join('');
        }

        function setCategory(cat, btn) {
            currentCategory = cat;
            document.querySelectorAll('#categoryNav .cat-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderProducts(document.getElementById('searchInput').value);
        }

        // --- View Toggle ---
        function setView(mode, btn) {
            viewMode = mode;
            document.querySelectorAll('.view-toggle button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const grid = document.getElementById('productsGrid');
            grid.classList.toggle('list-view', mode === 'list');
        }

        // --- Products ---
        function renderProducts(searchTerm = '') {
            const grid = document.getElementById('productsGrid');
            const term = searchTerm.toLowerCase().trim();

            const filtered = products.filter(p => {
                const matchesSearch = p.name.toLowerCase().includes(term) ||
                    (p.sku && p.sku.toLowerCase().includes(term)) ||
                    (p.category_name && p.category_name.toLowerCase().includes(term)) ||
                    (p.generic_name && p.generic_name.toLowerCase().includes(term)) ||
                    (p.brand_name && p.brand_name.toLowerCase().includes(term));
                const matchesCat = currentCategory === 'all' || p.category_name === currentCategory;
                return matchesSearch && matchesCat;
            });

            document.getElementById('resultsCount').textContent =
                `${filtered.length} product${filtered.length !== 1 ? 's' : ''}`;

            if (filtered.length === 0) {
                grid.innerHTML = `<div class="no-products">
                    <i class="fas fa-search"></i>
                    No products found${term ? ' for "' + escapeHtmlPos(term) + '"' : ''}
                </div>`;
                return;
            }

            grid.innerHTML = filtered.map(p => {
                const stock = parseInt(p.stock_quantity);
                let stockClass, stockLabel;
                if (stock <= 0) { stockClass = 'out'; stockLabel = 'Out'; }
                else if (stock < 20) { stockClass = 'low'; stockLabel = stock + ' left'; }
                else { stockClass = 'ok'; stockLabel = stock + ' in stock'; }

                const isOut = stock <= 0;
                const variant = [p.strength, p.dosage_form].filter(Boolean).join(' ');
                const canSellPiece = parseInt(p.sell_by_piece) === 1;
                const isRx = parseInt(p.requires_prescription) === 1;

                const imgHtml = p.image_url
                    ? `<img src="${p.image_url}" style="width:40px;height:40px;border-radius:8px;object-fit:cover;margin-bottom:4px;">`
                    : `<img src="assets/placeholder-product.svg" style="width:40px;height:40px;border-radius:8px;object-fit:cover;margin-bottom:4px;opacity:0.6;">`;

                return `<div class="product-card ${isOut ? 'out-of-stock' : ''}"
                    onclick="${isOut ? '' : 'addToCart(' + p.product_id + ', false)'}">
                    ${imgHtml}
                    ${isRx ? '<span style="position:absolute;top:4px;right:4px;background:#e65100;color:#fff;font-size:0.6rem;padding:2px 5px;border-radius:4px;font-weight:700;z-index:2;">Rx</span>' : ''}
                    <span class="p-category">${p.category_name || ''}</span>
                    <span class="p-name">${escapeHtmlPos(p.name)}</span>
                    ${variant ? '<span style="font-size:0.7rem;color:var(--text-light);margin-top:-2px;">' + variant + '</span>' : ''}
                    <div class="p-bottom">
                        <span class="p-price">₱${parseFloat(p.selling_price).toFixed(2)}</span>
                        <span class="p-stock ${stockClass}">${stockLabel}</span>
                    </div>
                    ${canSellPiece && !isOut ? `<button class="piece-sell-btn" onclick="event.stopPropagation(); addToCart(${p.product_id}, true)" title="Sell per piece">
                        <i class="fas fa-tablets"></i> ₱${(parseFloat(p.price_per_piece) || 0).toFixed(2)}/pc
                    </button>` : ''}
                </div>`;
            }).join('');

            grid.classList.toggle('list-view', viewMode === 'list');
        }

        // --- Cart ---
        async function addToCart(productId, perPiece = false) {
            const product = products.find(p => p.product_id == productId);
            if (!product) {
                console.error('POS: Product not found in loaded products array. ID:', productId, 'Products loaded:', products.length);
                showToast('Product not found. Try refreshing.', 'error');
                return;
            }
            if (parseInt(product.stock_quantity) <= 0) {
                showToast('Product is out of stock', 'error');
                return;
            }

            // Prescription warning
            if (parseInt(product.requires_prescription) === 1) {
                const alreadyInCart = cart.find(item => item.id == productId);
                if (!alreadyInCart) {
                    const ok = await customConfirm(
                        'Prescription Required',
                        '"' + product.name + '" requires a valid prescription.\n\nPlease verify the customer has a valid prescription before proceeding.',
                        'prescription',
                        { confirmText: 'Yes, Verified', cancelText: 'Cancel' }
                    );
                    if (!ok) return;
                }
            }

            const price = perPiece ? parseFloat(product.price_per_piece) : parseFloat(product.selling_price);
            const label = perPiece ? product.name + ' (per pc)' : product.name;
            const cartKey = perPiece ? productId + '_pc' : productId + '_box';

            const maxPieces = perPiece ? parseInt(product.stock_quantity) * parseInt(product.pieces_per_box || 1) : parseInt(product.stock_quantity);

            const existing = cart.find(item => item.cartKey == cartKey);
            if (existing) {
                if (existing.qty >= maxPieces) {
                    showToast('Max stock reached', 'error');
                    return;
                }
                existing.qty++;
            } else {
                cart.push({
                    id: product.product_id,
                    cartKey: cartKey,
                    name: label,
                    price: price,
                    qty: 1,
                    maxStock: maxPieces,
                    perPiece: perPiece,
                    piecesPerBox: parseInt(product.pieces_per_box || 1)
                });
            }

            showToast(`${label} added`, 'success');
            updateCartUI();
        }

        function updateCartUI() {
            const container = document.getElementById('cartItems');
            const countBadge = document.getElementById('cartCount');
            const totalItems = cart.reduce((s, i) => s + i.qty, 0);

            if (totalItems > 0) {
                countBadge.textContent = totalItems;
                countBadge.style.display = 'inline-block';
            } else {
                countBadge.style.display = 'none';
            }

            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="empty-cart-state">
                        <div class="empty-icon"><i class="fas fa-receipt"></i></div>
                        <p>Click a product or scan a barcode to start a sale</p>
                        <div class="shortcut-hints">
                            <span><kbd>F2</kbd> to search &nbsp;&middot;&nbsp; <kbd>F9</kbd> to pay</span>
                        </div>
                    </div>`;
                document.getElementById('payBtn').disabled = true;
                updateTotals(0);
                return;
            }

            container.innerHTML = cart.map((item, index) => `
                <div class="cart-item">
                    <div class="item-info">
                        <h4>${escapeHtmlPos(item.name)}${item.perPiece ? ' <span style="color:var(--primary-color);font-size:0.7rem;">PIECE</span>' : ''}</h4>
                        <div class="item-unit-price">₱${item.price.toFixed(2)} ea.</div>
                    </div>
                    <div class="item-qty">
                        <button class="qty-btn" onclick="updateQty(${index}, -1)">&#8722;</button>
                        <span class="qty-display">${item.qty}</span>
                        <button class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
                    </div>
                    <div class="item-total">₱${(item.price * item.qty).toFixed(2)}</div>
                    <button class="remove-btn" onclick="removeFromCart(${index})" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `).join('');

            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            updateTotals(subtotal);
            document.getElementById('payBtn').disabled = false;
        }

        function updateQty(index, change) {
            const item = cart[index];
            const newQty = item.qty + change;
            if (newQty <= 0) {
                cart.splice(index, 1);
            } else if (newQty > item.maxStock) {
                showToast('Max stock reached', 'error');
                return;
            } else {
                item.qty = newQty;
            }
            updateCartUI();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartUI();
        }

        function clearCart() {
            if (cart.length === 0) return;
            cart = [];
            updateCartUI();
            showToast('Cart cleared', 'success');
        }

        function holdSale() {
            if (cart.length === 0) return;
            const heldSales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
            const heldEntry = {
                id: Date.now(),
                cart: JSON.parse(JSON.stringify(cart)),
                discountEnabled: discountEnabled,
                timestamp: new Date().toLocaleString(),
                total: getTotal()
            };
            heldSales.push(heldEntry);
            localStorage.setItem('pos_held_sales', JSON.stringify(heldSales));
            cart = [];
            discountEnabled = false;
            const btn = document.getElementById('discountToggle');
            if (btn) btn.classList.remove('active');
            updateCartUI();
            updateHeldBadge();
            showToast('Sale held (' + heldSales.length + ' held)', 'success');
        }

        function updateHeldBadge() {
            const heldSales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
            const badge = document.getElementById('heldSalesBadge');
            if (badge) {
                if (heldSales.length > 0) {
                    badge.textContent = heldSales.length;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        function showHeldSales() {
            const heldSales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
            if (heldSales.length === 0) {
                showToast('No held sales', 'error');
                return;
            }

            // Build modal directly in DOM so HTML renders properly
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:99999;opacity:0;transition:opacity 0.2s;';

            let itemsHtml = '';
            heldSales.forEach((sale, i) => {
                const itemCount = sale.cart.reduce((s, item) => s + item.qty, 0);
                const names = sale.cart.map(item => item.name).slice(0, 3).join(', ');
                const moreCount = sale.cart.length > 3 ? ` +${sale.cart.length - 3} more` : '';
                itemsHtml += `
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 1rem;border:1px solid var(--input-border);border-radius:10px;margin-bottom:0.5rem;background:var(--bg-color);">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:0.9rem;">${itemCount} item${itemCount !== 1 ? 's' : ''} &mdash; ₱${sale.total.toFixed(2)}</div>
                            <div style="font-size:0.75rem;color:var(--text-light);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${names}${moreCount}</div>
                            <div style="font-size:0.7rem;color:var(--text-light);margin-top:2px;">${sale.timestamp}</div>
                        </div>
                        <div style="display:flex;gap:0.4rem;margin-left:0.75rem;flex-shrink:0;">
                            <button class="held-resume-btn" data-index="${i}" style="padding:0.4rem 0.8rem;background:var(--primary-color);color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:0.8rem;display:flex;align-items:center;gap:4px;"><i class="fas fa-play"></i> Resume</button>
                            <button class="held-delete-btn" data-index="${i}" style="padding:0.4rem 0.6rem;background:#ef4444;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:0.8rem;"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>`;
            });

            overlay.innerHTML = `
                <div data-held-modal="true" style="background:var(--card-bg);border-radius:16px;padding:1.5rem;width:90%;max-width:440px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                        <h3 style="margin:0;font-size:1.1rem;color:var(--text-color);"><i class="fas fa-pause-circle" style="color:var(--primary-color);margin-right:6px;"></i>Held Sales (${heldSales.length})</h3>
                        <button class="held-close-btn" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-light);padding:4px 8px;">&times;</button>
                    </div>
                    <div style="overflow-y:auto;flex:1;">${itemsHtml}</div>
                </div>`;

            document.body.appendChild(overlay);
            requestAnimationFrame(() => overlay.style.opacity = '1');

            // Event delegation - use mousedown to avoid conflicts
            overlay.addEventListener('mousedown', (e) => {
                e.stopPropagation();
                const resumeBtn = e.target.closest('.held-resume-btn');
                const deleteBtn = e.target.closest('.held-delete-btn');
                const closeBtn = e.target.closest('.held-close-btn');
                const modalBox = e.target.closest('[data-held-modal]');

                if (resumeBtn) {
                    e.preventDefault();
                    const idx = parseInt(resumeBtn.dataset.index);
                    overlay.remove();
                    setTimeout(() => resumeHeldSale(idx), 50);
                } else if (deleteBtn) {
                    e.preventDefault();
                    const idx = parseInt(deleteBtn.dataset.index);
                    const sales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
                    sales.splice(idx, 1);
                    localStorage.setItem('pos_held_sales', JSON.stringify(sales));
                    updateHeldBadge();
                    overlay.remove();
                    if (sales.length > 0) setTimeout(() => showHeldSales(), 50);
                    showToast('Held sale deleted', 'success');
                } else if (closeBtn) {
                    overlay.remove();
                } else if (!modalBox) {
                    overlay.remove();
                }
            });
        }

        function resumeHeldSale(index) {
            const heldSales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
            if (index < 0 || index >= heldSales.length) return;
            const sale = heldSales[index];
            if (cart.length > 0) {
                holdSale();
            }
            cart = sale.cart;
            discountEnabled = sale.discountEnabled || false;
            const btn = document.getElementById('discountToggle');
            if (btn) btn.classList.toggle('active', discountEnabled);
            heldSales.splice(index, 1);
            localStorage.setItem('pos_held_sales', JSON.stringify(heldSales));
            updateCartUI();
            updateHeldBadge();
            showToast('Sale resumed', 'success');
        }

        let discountEnabled = false;
        const DISCOUNT_RATE = 0.20;
        const DISCOUNT_MIN_SUBTOTAL = 200;

        function toggleDiscount() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            // If trying to enable, enforce ₱200 minimum
            if (!discountEnabled && subtotal < DISCOUNT_MIN_SUBTOTAL) {
                showToast('Subtotal must be at least ₱' + DISCOUNT_MIN_SUBTOTAL.toFixed(2) + ' to apply the 20% SC/PWD discount', 'error');
                return;
            }
            discountEnabled = !discountEnabled;
            const btn = document.getElementById('discountToggle');
            if (btn) btn.classList.toggle('active', discountEnabled);
            updateTotals(subtotal);
            if (discountEnabled) {
                showToast('20% Senior/PWD discount applied', 'success');
            } else {
                showToast('Discount removed', 'success');
            }
        }

        // Legacy compat
        function applyDiscount() { toggleDiscount(); }

        function updateTotals(subtotal) {
            // Auto-disable discount if subtotal drops below minimum
            if (discountEnabled && subtotal < DISCOUNT_MIN_SUBTOTAL) {
                discountEnabled = false;
                const btn = document.getElementById('discountToggle');
                if (btn) btn.classList.remove('active');
                showToast('Discount removed — subtotal below ₱' + DISCOUNT_MIN_SUBTOTAL.toFixed(2), 'error');
            }
            const tax = subtotal * 0.12;
            const beforeDiscount = subtotal + tax;
            const discountAmount = discountEnabled ? beforeDiscount * DISCOUNT_RATE : 0;
            const total = beforeDiscount - discountAmount;

            document.getElementById('subtotalDisplay').textContent = '₱' + subtotal.toFixed(2);
            document.getElementById('taxDisplay').textContent = '₱' + tax.toFixed(2);
            const discDisplay = document.getElementById('discountDisplay');
            discDisplay.textContent = '-₱' + discountAmount.toFixed(2);
            discDisplay.classList.toggle('has-discount', discountAmount > 0);
            document.getElementById('totalDisplay').textContent = '₱' + total.toFixed(2);
            document.getElementById('modalTotalDisplay').textContent = '₱' + total.toFixed(2);
        }

        function getTotal() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const tax = subtotal * 0.12;
            const beforeDiscount = subtotal + tax;
            const discountAmount = discountEnabled ? beforeDiscount * DISCOUNT_RATE : 0;
            return beforeDiscount - discountAmount;
        }

        // --- Payment ---
        function openPaymentModal() {
            if (cart.length === 0) return;
            document.getElementById('paymentModal').classList.add('active');
            document.getElementById('amountTendered').value = '';

            const total = getTotal();
            generateQuickCash(total);

            updateChange();
            setTimeout(() => document.getElementById('amountTendered').focus(), 100);
        }

        function generateQuickCash(total) {
            const container = document.getElementById('quickCashBtns');
            const rounded = Math.ceil(total);
            const amounts = [
                rounded,
                Math.ceil(total / 50) * 50,
                Math.ceil(total / 100) * 100,
                Math.ceil(total / 500) * 500,
                1000
            ];

            // Dedupe and sort
            const unique = [...new Set(amounts)].filter(a => a >= total).sort((a, b) => a - b).slice(0, 4);

            container.innerHTML = unique.map(amt =>
                `<button onclick="setQuickCash(${amt})">₱${amt.toLocaleString()}</button>`
            ).join('');
        }

        function setQuickCash(amount) {
            document.getElementById('amountTendered').value = amount;
            updateChange();
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
        }

        function setPaymentMethod(method, btn) {
            paymentMethod = method;
            document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const cashGroup = document.getElementById('cashInputGroup');
            cashGroup.style.display = method === 'cash' ? 'block' : 'none';

            if (method === 'cash') {
                document.getElementById('amountTendered').focus();
            }
            updateChange();
        }

        function updateChange() {
            const total = getTotal();
            const tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
            const change = tendered - total;

            const display = document.getElementById('changeDisplay');
            display.textContent = '₱' + Math.max(0, change).toFixed(2);
            display.style.color = change >= 0 ? '#16a34a' : 'var(--danger-color)';

            const btn = document.getElementById('completeSaleBtn');
            if (paymentMethod === 'cash' && tendered < total) {
                btn.disabled = true;
            } else {
                btn.disabled = false;
            }
        }

        async function completeSale() {
            const btn = document.getElementById('completeSaleBtn');
            if (btn.disabled) return;

            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

            // Block payment if discount is on but subtotal is below minimum
            if (discountEnabled && subtotal < DISCOUNT_MIN_SUBTOTAL) {
                showToast('Cannot process: Subtotal must be at least ₱' + DISCOUNT_MIN_SUBTOTAL.toFixed(2) + ' for the 20% discount', 'error');
                discountEnabled = false;
                const discBtn = document.getElementById('discountToggle');
                if (discBtn) discBtn.classList.remove('active');
                updateTotals(subtotal);
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Processing...';

            const tax = subtotal * 0.12;
            const beforeDiscount = subtotal + tax;
            const discountAmount = discountEnabled ? beforeDiscount * DISCOUNT_RATE : 0;
            const total = beforeDiscount - discountAmount;

            const now = new Date();
            const saleData = {
                items: cart,
                total: total,
                subtotal: subtotal,
                tax: tax,
                discount_percent: discountEnabled ? 20 : 0,
                discount_amount: discountAmount,
                payment_method: paymentMethod,
                amount_tendered: parseFloat(document.getElementById('amountTendered').value) || total,
                receipt_no: 'TX-' + now.getTime().toString().slice(-8),
                created_at: now.toISOString(),
                cashier: document.body.dataset.cashierName || 'Cashier'
            };

            try {
                const res = await fetch('pos_api.php?action=create_sale', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(saleData)
                });
                const data = await res.json();

                if (!data.success) {
                    showToast(data.message || 'Sale failed', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Complete Sale';
                    return;
                }

                saleData.sale_id = data.sale_id;
                saleData.sale_reference = data.sale_reference;
                
                // Capture server-generated reward QR code if available
                if (data.reward_qr_code) {
                    saleData.reward_qr_code = data.reward_qr_code;
                    saleData.reward_qr_expires = data.reward_qr_expires;
                }
            } catch (err) {
                console.error('Sale error:', err);
                showToast('Network error — sale not saved', 'error');
                btn.disabled = false;
                btn.textContent = 'Complete Sale';
                return;
            }

            closePaymentModal();
            lastSaleData = saleData;

            // If points were auto-awarded for pickup, do NOT show a redeemable QR code
            // (prevents double-dipping and confusion)
            let pointsAutoAwarded = false;

            // If this was a pickup order, mark it as picked up
            if (currentPickupOrderId) {
                try {
                    const pickupRes = await fetch('online_order_api.php?action=mark_picked_up', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `order_id=${currentPickupOrderId}&sale_id=${saleData.sale_id}`
                    });
                    const pickupData = await pickupRes.json();
                    
                    if (pickupData.success) {
                        showToast('✅ Order marked as picked up!', 'success');
                        // Show loyalty points message if awarded
                        if (pickupData.loyalty && pickupData.loyalty.awarded) {
                            pointsAutoAwarded = true;
                            setTimeout(() => {
                                showToast('🎉 Customer earned ' + pickupData.loyalty.points + ' loyalty points!', 'success');
                            }, 1500);
                        }
                        // Refresh online orders panel
                        loadOnlineOrders();
                        pollPendingOrderCount();
                    }
                } catch (pickupErr) {
                    console.error('Error marking as picked up:', pickupErr);
                }
                
                // Clear the pickup order reference
                currentPickupOrderId = null;
            }

            // Generate one-time reward QR code for this sale (fallback if server didn't already generate one)
            // Note: POS sales are for walk-in customers, so we don't assign to a specific user
            // The customer can scan the QR code printed on their receipt to earn points
            if (pointsAutoAwarded) {
                // If points were already awarded automatically, remove any generated QR code
                delete saleData.reward_qr_code;
                delete saleData.reward_qr_expires;
            } else if (!saleData.reward_qr_code) {
                try {
                    const qrRes = await fetch('reward_qr_api.php?action=generate_reward_qr', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            source_type: 'pos',
                            order_id: saleData.sale_id || 0,
                            sale_reference: saleData.sale_reference || saleData.receipt_no,
                            customer_name: 'Walk-in Customer',
                            total_amount: saleData.total || 0
                        })
                    });
                    const qrData = await qrRes.json();
                    if (qrData.success) {
                        saleData.reward_qr_code = qrData.qr_code;
                        saleData.reward_qr_expires = qrData.expires_at;
                    }
                } catch (qrErr) {
                    console.warn('Could not generate reward QR:', qrErr);
                }
            }

            renderReceipt(saleData);
            document.getElementById('receiptModal').classList.add('active');
            btn.disabled = false;
            btn.textContent = 'Complete Sale';

            // Immediately refresh product stock after sale
            loadProducts();
        }

        function newSale() {
            cart = [];
            discountEnabled = false;
            const discToggle = document.getElementById('discountToggle');
            if (discToggle) discToggle.classList.remove('active');
            updateCartUI();
            document.getElementById('receiptModal').classList.remove('active');
            document.getElementById('searchInput').focus();
            // Refresh product list to reflect updated stock
            loadProducts();
        }

        // ═══════════════════════════════════════════════
        // ═══ THERMAL PRINTER — Web Bluetooth ESC/POS ═══
        // ═══════════════════════════════════════════════
        let btDevice = null;
        let btCharacteristic = null;
        const textEncoder = new TextEncoder();

        // Common BLE printer service/characteristic UUIDs
        const BLE_PRINTER_SERVICES = [
            '000018f0-0000-1000-8000-00805f9b34fb',
            '0000ff00-0000-1000-8000-00805f9b34fb',
            'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
            '49535343-fe7d-4ae5-8fa9-9fafd205e455',
        ];
        const BLE_WRITE_CHARACTERISTICS = [
            '00002af1-0000-1000-8000-00805f9b34fb',
            '0000ff02-0000-1000-8000-00805f9b34fb',
            'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f',
            '49535343-8841-43f4-a8d4-ecbe34729bb3',
            '49535343-1e4d-4bd9-ba61-23c647249616',
        ];

        const ESC = 0x1B, GS = 0x1D, LF = 0x0A;
        const ESCPOS = {
            INIT:         [ESC, 0x40],
            ALIGN_LEFT:   [ESC, 0x61, 0],
            ALIGN_CENTER: [ESC, 0x61, 1],
            ALIGN_RIGHT:  [ESC, 0x61, 2],
            BOLD_ON:      [ESC, 0x45, 1],
            BOLD_OFF:     [ESC, 0x45, 0],
            DOUBLE_ON:    [GS, 0x21, 0x11],
            DOUBLE_OFF:   [GS, 0x21, 0x00],
            FEED:         [ESC, 0x64, 5],
            CUT:          [GS, 0x56, 0x00],
            PARTIAL_CUT:  [GS, 0x56, 0x01],
            CASH_DRAWER:  [ESC, 0x70, 0, 0x19, 0xFA]
        };

        let hardwarePrintUnavailable = false;
        let backendPrintUnavailable = false;

        function isPrinterConnected() { return btDevice !== null && btCharacteristic !== null; }

        function updatePrinterUI() {
            const btn = document.getElementById('printerConnectBtn');
            const hint = document.getElementById('printerHint');
            if (isPrinterConnected()) {
                btn.classList.add('connected');
                btn.title = 'Printer: ' + (btDevice.name || 'Connected') + ' (click to disconnect)';
                if (hint) hint.style.display = 'none';
            } else {
                btn.classList.remove('connected');
                btn.title = 'Connect Thermal Printer';
                if (hint) hint.style.display = 'block';
            }
        }

        async function togglePrinterConnection() {
            if (isPrinterConnected()) {
                await disconnectPrinter();
            } else {
                await connectPrinter();
            }
        }

        async function connectPrinter() {
            if (!('bluetooth' in navigator)) {
                hardwarePrintUnavailable = true;
                showToast('Bluetooth not supported. Use Chrome or Edge.', 'error');
                return;
            }
            try {
                showToast('Searching for printer...', 'success');
                btDevice = await navigator.bluetooth.requestDevice({
                    filters: [{ namePrefix: 'JP' }],
                    optionalServices: BLE_PRINTER_SERVICES,
                    acceptAllDevices: false
                }).catch(() =>
                    // If name filter doesn't work, try accepting all devices
                    navigator.bluetooth.requestDevice({
                        acceptAllDevices: true,
                        optionalServices: BLE_PRINTER_SERVICES
                    })
                );

                if (!btDevice) {
                    showToast('No printer selected.', 'error');
                    return;
                }

                btDevice.addEventListener('gattserverdisconnected', () => {
                    console.log('Printer disconnected');
                    btCharacteristic = null;
                    updatePrinterUI();
                    showToast('Printer disconnected', 'error');
                });

                const server = await btDevice.gatt.connect();

                // Try each known service UUID until we find one
                let writeChar = null;
                for (const svcUuid of BLE_PRINTER_SERVICES) {
                    try {
                        const service = await server.getPrimaryService(svcUuid);
                        const chars = await service.getCharacteristics();
                        // Find writable characteristic
                        for (const c of chars) {
                            if (c.properties.write || c.properties.writeWithoutResponse) {
                                writeChar = c;
                                console.log('Found writable characteristic:', c.uuid, 'in service:', svcUuid);
                                break;
                            }
                        }
                        if (writeChar) break;
                    } catch (_) { /* service not found, try next */ }
                }

                if (!writeChar) {
                    showToast('Could not find print characteristic. Is this a BLE printer?', 'error');
                    btDevice.gatt.disconnect();
                    btDevice = null;
                    updatePrinterUI();
                    return;
                }

                btCharacteristic = writeChar;
                updatePrinterUI();
                showToast('Printer connected: ' + (btDevice.name || 'Thermal Printer'), 'success');

            } catch (err) {
                const msg = (err && err.message) ? String(err.message) : '';
                const name = (err && err.name) ? String(err.name) : '';
                if (
                    name === 'SecurityError' ||
                    name === 'NotAllowedError' ||
                    /permissions policy|feature policy|serial|bluetooth/i.test(msg)
                ) {
                    hardwarePrintUnavailable = true;
                }
                if (err.name !== 'NotFoundError') {
                    console.error('Bluetooth connect error:', err);
                    showToast('Failed to connect: ' + err.message, 'error');
                }
                btDevice = null;
                btCharacteristic = null;
                updatePrinterUI();
            }
        }

        async function disconnectPrinter() {
            try {
                if (btDevice && btDevice.gatt.connected) {
                    btDevice.gatt.disconnect();
                }
            } catch (err) {
                console.error('Disconnect error:', err);
            }
            btDevice = null;
            btCharacteristic = null;
            updatePrinterUI();
            showToast('Printer disconnected', 'success');
        }

        // BLE has limited MTU — send data in small chunks
        async function bleWrite(data) {
            if (!btCharacteristic) return false;
            const CHUNK = 100; // safe BLE chunk size
            try {
                for (let i = 0; i < data.length; i += CHUNK) {
                    const chunk = data.slice(i, i + CHUNK);
                    if (btCharacteristic.properties.writeWithoutResponse) {
                        await btCharacteristic.writeValueWithoutResponse(chunk);
                    } else {
                        await btCharacteristic.writeValueWithResponse(chunk);
                    }
                    // Small delay between chunks for printer to process
                    if (i + CHUNK < data.length) {
                        await new Promise(r => setTimeout(r, 20));
                    }
                }
                return true;
            } catch (err) {
                console.error('BLE write error:', err);
                return false;
            }
        }

        async function sendBytes(bytes) {
            return bleWrite(new Uint8Array(bytes));
        }

        async function sendText(text) {
            return bleWrite(textEncoder.encode(text));
        }

        function padLine(left, right, w) {
            const space = w - left.length - right.length;
            return left + ' '.repeat(Math.max(1, space)) + right;
        }

        function fmtMoney(val) {
            return 'P' + parseFloat(val).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function wrapText(text, width) {
            text = text.trim();
            if (!text) return [''];
            const lines = [];
            while (text.length > 0) {
                if (text.length <= width) { lines.push(text); break; }
                let cut = text.lastIndexOf(' ', width);
                if (cut === -1) cut = width;
                lines.push(text.substring(0, cut).trimEnd());
                text = text.substring(cut).trimStart();
            }
            return lines;
        }

        async function printReceiptToThermal(saleData) {
            const W = 32; // 58mm paper width
            const line = '-'.repeat(W);
            const dline = '='.repeat(W);

            const cashierName = document.body.dataset.cashierName || 'Cashier';
            const receiptNo = saleData.receipt_no || 'TX-' + Date.now().toString().slice(-8);
            const createdAt = saleData.created_at ? new Date(saleData.created_at) : new Date();
            const items = saleData.items || [];
            const subtotal = items.reduce((s, i) => s + (i.price * i.qty), 0);
            const tax = subtotal * 0.12;
            const beforeDiscount = subtotal + tax;
            const discountPct = saleData.discount_percent || 0;
            const discountAmt = saleData.discount_amount || 0;
            const total = beforeDiscount - discountAmt;
            const tendered = saleData.amount_tendered || total;
            const change = Math.max(0, tendered - total);
            const payMethod = saleData.payment_method || 'cash';

            // Header
            await sendBytes(ESCPOS.INIT);
            await sendBytes(ESCPOS.ALIGN_CENTER);
            await sendBytes(ESCPOS.DOUBLE_ON);
            await sendText('Calloway\nPharmacy\n');
            await sendBytes(ESCPOS.DOUBLE_OFF);
            await sendText('Official Receipt\n\n');

            // Receipt info
            await sendBytes(ESCPOS.ALIGN_LEFT);
            await sendText('Receipt: ' + receiptNo + '\n');
            await sendText('Date: ' + createdAt.toLocaleString() + '\n');
            await sendText('Cashier: ' + cashierName + '\n');
            await sendText(dline + '\n');

            // Items
            for (const item of items) {
                const name = item.name || '';
                const qty = parseInt(item.qty) || 0;
                const price = parseFloat(item.price) || 0;
                const lineTotal = price * qty;

                const nameLines = wrapText(name, W);
                for (const nl of nameLines) {
                    await sendText(nl + '\n');
                }
                const left = qty + ' x ' + fmtMoney(price);
                const right = fmtMoney(lineTotal);
                await sendText(padLine(left, right, W) + '\n');
            }

            await sendText(line + '\n');

            // Totals
            await sendText(padLine('Subtotal', fmtMoney(subtotal), W) + '\n');
            await sendText(padLine('VAT 12%', fmtMoney(tax), W) + '\n');
            if (discountPct > 0) {
                await sendText(padLine('Discount (' + discountPct + '%)', '-' + fmtMoney(discountAmt), W) + '\n');
            }
            await sendBytes(ESCPOS.BOLD_ON);
            await sendText(padLine('TOTAL', fmtMoney(total), W) + '\n');
            await sendBytes(ESCPOS.BOLD_OFF);
            await sendText(padLine('Paid (' + payMethod + ')', fmtMoney(tendered), W) + '\n');
            await sendText(padLine('Change', fmtMoney(change), W) + '\n');

            // Footer
            await sendText('\n');
            await sendBytes(ESCPOS.ALIGN_CENTER);
            await sendText('Thank you! Get well soon.\n');
            await sendText(dline + '\n');

            // Reward QR Code
            const qrCode = saleData.reward_qr_code;
            if (qrCode) {
                await sendText('\n');
                await sendBytes(ESCPOS.BOLD_ON);
                await sendText('*** Reward QR Code ***\n');
                await sendBytes(ESCPOS.BOLD_OFF);
                await sendText('Scan to earn loyalty points\n');
                await sendText('(25 pts per P500 spent)\n\n');

                // Build the scannable URL (same as the on-screen QR)
                const basePath = window.location.pathname.replace(/[^\/]*$/, '');
                const qrUrl = window.location.origin + basePath + 'receipt_qr_landing.php?code=' + encodeURIComponent(qrCode);
                const qrData = textEncoder.encode(qrUrl);
                const qrLen = qrData.length + 3; // data + pL/pH overhead
                const pL = qrLen & 0xFF;
                const pH = (qrLen >> 8) & 0xFF;

                // ESC/POS QR Code commands (GS ( k)
                // 1. Select QR model 2
                await sendBytes([GS, 0x28, 0x6B, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00]);
                // 2. Set module size (4 = good size for 58mm paper)
                await sendBytes([GS, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x43, 0x06]);
                // 3. Set error correction level M
                await sendBytes([GS, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x45, 0x31]);
                // 4. Store QR data
                await sendBytes([GS, 0x28, 0x6B, pL, pH, 0x31, 0x50, 0x30]);
                await bleWrite(qrData);
                // Small delay for printer to process QR data
                await new Promise(r => setTimeout(r, 100));
                // 5. Print QR code
                await sendBytes([GS, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x51, 0x30]);

                await sendText('\n');
                await sendText(qrCode + '\n');
                await sendText('Valid 30 days | One-time use\n');
                await sendText(dline + '\n');
            }

            await sendBytes(ESCPOS.FEED);
            await sendBytes(ESCPOS.PARTIAL_CUT);

            return true;
        }

        async function printReceipt() {
            if (!lastSaleData) {
                showToast('No receipt to print', 'error');
                return;
            }

            // Try connected thermal printer first
            if (!hardwarePrintUnavailable && isPrinterConnected()) {
                try {
                    await printReceiptToThermal(lastSaleData);
                    showToast('Receipt sent to printer', 'success');
                    return;
                } catch (err) {
                    console.error('Thermal print error:', err);
                    hardwarePrintUnavailable = true;
                    showToast('Thermal print failed — using browser print fallback.', 'error');
                }
            }

            // If local hardware printing is unavailable, try backend as secondary option
            if (!backendPrintUnavailable) {
                const result = await sendReceiptToPrinter(lastSaleData);
                if (result.success) {
                    showToast('Receipt sent to printer', 'success');
                    return;
                }
                console.warn('Backend print failed:', result.message);
                if (/spooler|rpc server|not authenticated|print request failed/i.test(result.message || '')) {
                    backendPrintUnavailable = true;
                }
            }

            // Ultimate fallback: browser print
            printReceiptBrowserFallback(lastSaleData);
        }

        function buildPlainTextReceipt(saleData) {
            const W = 32;
            const line = '-'.repeat(W);
            const dline = '='.repeat(W);

            const cashierName = document.body.dataset.cashierName || 'Cashier';
            const receiptNo = saleData.receipt_no || 'TX-' + Date.now().toString().slice(-8);
            const createdAt = saleData.created_at ? new Date(saleData.created_at) : new Date();
            const items = saleData.items || [];

            const subtotal = items.reduce((s, i) => s + (i.price * i.qty), 0);
            const tax = subtotal * 0.12;
            const beforeDiscount = subtotal + tax;
            const discountPct = saleData.discount_percent || 0;
            const discountAmt = saleData.discount_amount || 0;
            const total = beforeDiscount - discountAmt;
            const tendered = saleData.amount_tendered || total;
            const change = Math.max(0, tendered - total);
            const payMethod = saleData.payment_method || 'cash';

            const out = [];
            out.push('CALLOWAY PHARMACY');
            out.push('OFFICIAL RECEIPT');
            out.push('');
            out.push('Receipt: ' + receiptNo);
            out.push('Date: ' + createdAt.toLocaleString());
            out.push('Cashier: ' + cashierName);
            out.push(dline);

            for (const item of items) {
                const name = item.name || '';
                const qty = parseInt(item.qty) || 0;
                const price = parseFloat(item.price) || 0;
                const lineTotal = qty * price;

                const nameLines = wrapText(name, W);
                for (const nl of nameLines) out.push(nl);
                out.push(padLine(qty + ' x ' + fmtMoney(price), fmtMoney(lineTotal), W));
            }

            out.push(line);
            out.push(padLine('Subtotal', fmtMoney(subtotal), W));
            out.push(padLine('VAT 12%', fmtMoney(tax), W));
            if (discountPct > 0) {
                out.push(padLine('Discount (' + discountPct + '%)', '-' + fmtMoney(discountAmt), W));
            }
            out.push(padLine('TOTAL', fmtMoney(total), W));
            out.push(padLine('Paid (' + payMethod + ')', fmtMoney(tendered), W));
            out.push(padLine('Change', fmtMoney(change), W));
            out.push('');
            out.push('Thank you! Get well soon.');
            out.push(dline);

            return out.join('\n');
        }

        function printReceiptBrowserFallback(saleData) {
            const text = buildPlainTextReceipt(saleData || lastSaleData || {});
            const win = window.open('', '_blank', 'width=420,height=700');
            if (!win) {
                showToast('Popup blocked. Allow popups then print again.', 'error');
                return;
            }

            win.document.write(`<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Receipt</title>
  <style>
    body { font-family: 'Courier New', monospace; margin: 8px; }
    pre { white-space: pre-wrap; font-size: 12px; line-height: 1.3; }
    @media print { @page { margin: 4mm; } }
  </style>
</head>
<body>
  <pre>${escapeHtmlPos(text)}</pre>
</body>
</html>`);
            win.document.close();
            win.focus();
            setTimeout(() => { win.print(); }, 120);
        }

        async function sendReceiptToPrinter(payload) {
            try {
                const res = await fetch('print_receipt.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const raw = await res.text();
                let data = null;
                try {
                    data = JSON.parse(raw);
                } catch (_) {
                    data = null;
                }

                if (data && data.success === true) {
                    return { success: true, message: 'Printed' };
                }

                if (data && data.details) {
                    console.warn('Print details:', data.details);
                }

                return {
                    success: false,
                    message: (data && data.message)
                        ? data.message
                        : `Print request failed (${res.status}).`
                };
            } catch (err) {
                console.error('Print error:', err);
                return { success: false, message: 'Print request failed.' };
            }
        }

        function renderReceipt(saleData) {
            const receiptEl = document.getElementById('receiptContent');
            const cashierName = document.body.dataset.cashierName || 'Cashier';
            const receiptNo = saleData.receipt_no || 'TX-' + Date.now().toString().slice(-8);
            const createdAt = saleData.created_at ? new Date(saleData.created_at) : new Date();

            const subtotal = saleData.items.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const tax = subtotal * 0.12;
            const beforeDiscount = subtotal + tax;
            const discountPct = saleData.discount_percent || 0;
            const discountAmt = saleData.discount_amount || 0;
            const total = beforeDiscount - discountAmt;
            const tendered = saleData.amount_tendered || total;
            const change = Math.max(0, tendered - total);

            const rows = saleData.items.map(item => {
                const lineTotal = item.price * item.qty;
                return `
                    <tr>
                        <td>
                            <div style="font-weight: 600;">${escapeHtmlPos(item.name)}</div>
                            <div style="color: var(--text-light); font-size: 0.72rem;">${item.qty} x ₱${item.price.toFixed(2)}</div>
                        </td>
                        <td>₱${lineTotal.toFixed(2)}</td>
                    </tr>
                `;
            }).join('');

            receiptEl.innerHTML = `
                <div class="receipt-header">
                    <h3>Calloway Pharmacy</h3>
                    <div class="receipt-meta">
                        <span>Receipt: ${receiptNo}</span>
                        <span>Date: ${createdAt.toLocaleString()}</span>
                        <span>Cashier: ${cashierName}</span>
                    </div>
                </div>

                <div class="receipt-section">
                    <table class="receipt-items">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                    </table>
                </div>

                <div class="receipt-totals">
                    <div class="row"><span>Subtotal</span><span>₱${subtotal.toFixed(2)}</span></div>
                    <div class="row"><span>VAT (12%)</span><span>₱${tax.toFixed(2)}</span></div>
                    ${discountPct > 0 ? `<div class="row" style="color:#dc2626;"><span>Discount (${discountPct}%)</span><span>-₱${discountAmt.toFixed(2)}</span></div>` : ''}
                    <div class="row total"><span>Total</span><span>₱${total.toFixed(2)}</span></div>
                    <div class="row"><span>Paid (${saleData.payment_method})</span><span>₱${tendered.toFixed(2)}</span></div>
                    <div class="row"><span>Change</span><span>₱${change.toFixed(2)}</span></div>
                </div>

                <div class="receipt-footer">
                    Thank you! Get well soon.
                </div>

                ${saleData.reward_qr_code ? `
                <div style="margin-top:1rem; padding-top:0.75rem; border-top:1px dashed var(--divider-color); text-align:center;">
                    <div style="font-weight:700; font-size:0.85rem; margin-bottom:0.4rem; color:var(--primary-color);">🎁 Reward QR Code</div>
                    <div style="font-size:0.75rem; color:var(--text-light); margin-bottom:0.5rem;">Scan this code to earn loyalty points (25 points per ₱500 spent)!</div>
                    <div id="receiptQrCodeContainer" style="display:flex;justify-content:center;margin:0.5rem auto;"></div>
                    <div style="font-size:0.7rem; color:var(--text-light); word-break:break-all; margin-top:0.3rem;">${saleData.reward_qr_code}</div>
                    <div style="font-size:0.65rem; color:var(--text-light); margin-top:0.2rem;">Valid for 30 days &bull; One-time use only</div>
                </div>
                ` : ''}
            `;

            // Render actual QR code
            if (saleData.reward_qr_code) {
                setTimeout(() => {
                    const qrContainer = document.getElementById('receiptQrCodeContainer');
                    if (qrContainer && typeof QRCode !== 'undefined') {
                        qrContainer.innerHTML = '';
                        // Build a scannable URL so phones open the landing page directly
                        const basePath = window.location.pathname.replace(/[^\/]*$/, '');
                        const qrUrl = window.location.origin + basePath + 'receipt_qr_landing.php?code=' + encodeURIComponent(saleData.reward_qr_code);
                        new QRCode(qrContainer, {
                            text: qrUrl,
                            width: 150,
                            height: 150,
                            colorDark: '#000000',
                            colorLight: '#ffffff',
                            correctLevel: QRCode.CorrectLevel.M
                        });
                    }
                }, 100);
            }
        }

        // --- Toast ---
        function showToast(msg, type = 'success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'toast ' + type + ' show';
            clearTimeout(t._timer);
            t._timer = setTimeout(() => t.classList.remove('show'), 1800);
        }

        // ─── Online Order Notification System ───
        let lastNotifCount = 0;
        let posNotifPanelOpen = false;

        function togglePosNotifPanel() {
            const panel = document.getElementById('posNotifPanel');
            posNotifPanelOpen = !posNotifPanelOpen;
            if (posNotifPanelOpen) {
                panel.classList.add('active');
                loadPosNotifications();
            } else {
                panel.classList.remove('active');
            }
        }

        // Close panel on outside click
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('posNotifPanel');
            const bell = document.getElementById('onlineNotifBell');
            if (posNotifPanelOpen && !panel.contains(e.target) && !bell.contains(e.target)) {
                posNotifPanelOpen = false;
                panel.classList.remove('active');
            }
        });

        async function loadPosNotifications() {
            try {
                const res = await fetch('online_order_api.php?action=get_notifications&limit=20');
                const data = await res.json();
                if (data.success) {
                    renderPosNotifications(data.notifications);
                }
            } catch (err) {
                console.error('Failed to load notifications:', err);
            }
        }

        function renderPosNotifications(notifications) {
            const body = document.getElementById('posNotifPanelBody');
            if (!notifications || notifications.length === 0) {
                body.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>';
                return;
            }

            body.innerHTML = notifications.map(n => {
                const isUnread = n.is_read == 0;
                const time = formatTimeAgo(n.created_at);
                return `
                    <div class="notif-item ${isUnread ? 'unread' : ''}" onclick="viewOrderNotif(${n.notification_id}, ${n.order_id})">
                        <div class="notif-item-header">
                            <span class="notif-item-title">${escapeHtmlPos(n.title)}</span>
                            <span class="notif-item-time">${time}</span>
                        </div>
                        <div class="notif-item-body">${escapeHtmlPos(n.message)}</div>
                    </div>
                `;
            }).join('');
        }

        async function viewOrderNotif(notifId, orderId) {
            // Mark as read
            try {
                await fetch('online_order_api.php?action=mark_read&notification_id=' + notifId);
                pollPosUnreadCount();
                loadPosNotifications();
            } catch (err) { /* ignore */ }
        }

        async function markAllPosNotifRead() {
            try {
                await fetch('online_order_api.php?action=mark_all_read');
                pollPosUnreadCount();
                loadPosNotifications();
                showToast('All notifications marked as read', 'success');
            } catch (err) { /* ignore */ }
        }

        async function pollPosUnreadCount() {
            try {
                const res = await fetch('online_order_api.php?action=get_unread_count');
                const data = await res.json();
                if (data.success) {
                    const count = data.count;
                    const badge = document.getElementById('posNotifBadge');
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.remove('hidden');

                        // Play sound + toast if new notifications
                        if (count > lastNotifCount && lastNotifCount >= 0) {
                            showToast('🛒 New online order received!', 'success');
                            // Shake the bell
                            const bell = document.getElementById('onlineNotifBell');
                            bell.style.animation = 'none';
                            bell.offsetHeight;
                            bell.style.animation = 'bellShake 0.5s ease 3';
                        }
                        lastNotifCount = count;
                    } else {
                        badge.classList.add('hidden');
                        lastNotifCount = 0;
                    }
                }
            } catch (err) { /* ignore */ }
        }

        function formatTimeAgo(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return date.toLocaleDateString();
        }

        function escapeHtmlPos(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Poll every 10 seconds
        pollPosUnreadCount();
        setInterval(pollPosUnreadCount, 10000);

        // Add bell shake animation
        const shakeStyle = document.createElement('style');
        shakeStyle.textContent = `
            @keyframes bellShake {
                0%, 100% { transform: rotate(0); }
                20% { transform: rotate(15deg); }
                40% { transform: rotate(-15deg); }
                60% { transform: rotate(10deg); }
                80% { transform: rotate(-10deg); }
            }
        `;
        document.head.appendChild(shakeStyle);

        // ─── Online Orders Tab System ───
        let currentOOFilter = 'all';
        let onlineOrders = [];
        let lastPendingCount = 0;

        function switchPosTab(tab, btn) {
            document.querySelectorAll('.pos-main-tab').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');

            const productsPanel = document.getElementById('productsPanel');
            const ordersPanel = document.getElementById('onlineOrdersPanel');

            if (tab === 'products') {
                productsPanel.classList.remove('hidden-panel');
                productsPanel.style.display = 'flex';
                ordersPanel.classList.remove('active');
                ordersPanel.style.display = 'none';
                document.getElementById('searchInput').focus();
            } else {
                productsPanel.classList.add('hidden-panel');
                productsPanel.style.display = 'none';
                ordersPanel.classList.add('active');
                ordersPanel.style.display = 'flex';
                loadOnlineOrders();
            }
        }

        function filterOnlineOrders(status, btn) {
            currentOOFilter = status;
            document.querySelectorAll('.oo-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderOnlineOrders();
        }

        async function loadOnlineOrders() {
            try {
                const res = await fetch('online_order_api.php?action=get_online_orders&limit=50');
                const data = await res.json();
                if (data.success) {
                    onlineOrders = data.orders;
                    renderOnlineOrders();
                }
            } catch (err) {
                console.error('Failed to load online orders:', err);
                document.getElementById('onlineOrdersList').innerHTML =
                    '<div class="oo-empty"><i class="fas fa-exclamation-triangle"></i><p>Failed to load orders</p></div>';
            }
        }

        function renderOnlineOrders() {
            const container = document.getElementById('onlineOrdersList');
            let filtered = onlineOrders;

            if (currentOOFilter !== 'all') {
                filtered = onlineOrders.filter(o => o.status === currentOOFilter);
            }

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="oo-empty">
                        <i class="fas fa-shopping-bag"></i>
                        <p>No ${currentOOFilter !== 'all' ? currentOOFilter.toLowerCase() : ''} online orders</p>
                    </div>`;
                return;
            }

            container.innerHTML = filtered.map(order => {
                const statusClass = order.status.toLowerCase();
                const timeAgo = formatTimeAgo(order.created_at);
                const actions = getOrderActions(order);

                return `
                    <div class="oo-card" id="ooCard${order.order_id}" onclick="toggleOrderExpand(${order.order_id})">
                        <div class="oo-card-header">
                            <span class="oo-card-ref">
                                <i class="fas fa-shopping-cart"></i>
                                ${escapeHtmlPos(order.order_ref)}
                            </span>
                            <span class="oo-status-badge ${statusClass}">${order.status}</span>
                        </div>
                        <div class="oo-card-body">
                            <span><i class="fas fa-user"></i> ${escapeHtmlPos(order.customer_name)}</span>
                            <span><i class="fas fa-clock"></i> ${timeAgo}</span>
                            <span><i class="fas fa-peso-sign"></i> ₱${parseFloat(order.total_amount).toFixed(2)}</span>
                            <span><i class="fas fa-credit-card"></i> ${escapeHtmlPos(order.payment_method || 'Cash on Pickup')}</span>
                            <span><i class="fas fa-box"></i> ${order.item_count} item(s)</span>
                        </div>
                        <div class="oo-card-items" id="ooItems${order.order_id}">
                            <div style="text-align:center;color:var(--text-light);font-size:0.8rem;padding:0.5rem;">
                                <i class="fas fa-spinner fa-spin"></i> Loading items...
                            </div>
                        </div>
                        <div class="oo-card-actions" onclick="event.stopPropagation();">
                            ${actions}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function getOrderActions(order) {
            let html = '';
            switch (order.status) {
                case 'Pending':
                    html += `<button class="oo-action-btn confirm-btn" onclick="changeOrderStatus(${order.order_id}, 'Confirmed')"><i class="fas fa-check"></i> Confirm</button>`;
                    html += `<button class="oo-action-btn cancel-btn" onclick="changeOrderStatus(${order.order_id}, 'Cancelled')"><i class="fas fa-times"></i> Cancel</button>`;
                    break;
                case 'Confirmed':
                    html += `<button class="oo-action-btn prepare-btn" onclick="changeOrderStatus(${order.order_id}, 'Preparing')"><i class="fas fa-mortar-pestle"></i> Start Preparing</button>`;
                    html += `<button class="oo-action-btn cancel-btn" onclick="changeOrderStatus(${order.order_id}, 'Cancelled')"><i class="fas fa-times"></i> Cancel</button>`;
                    break;
                case 'Preparing':
                    html += `<button class="oo-action-btn ready-btn" onclick="changeOrderStatus(${order.order_id}, 'Ready')"><i class="fas fa-check-double"></i> Mark Ready</button>`;
                    break;
                case 'Ready':
                    html += `<button class="oo-action-btn complete-btn" onclick="processPickupPayment(${order.order_id})"><i class="fas fa-cash-register"></i> Process Pickup Payment</button>`;
                    html += `<button class="oo-action-btn" onclick="changeOrderStatus(${order.order_id}, 'Completed')" style="background:#6b7280;"><i class="fas fa-flag-checkered"></i> Mark Complete (No Payment)</button>`;
                    break;
            }
            html += `<button class="oo-action-btn view-btn" onclick="toggleOrderExpand(${order.order_id})"><i class="fas fa-eye"></i> Details</button>`;
            return html;
        }

        async function toggleOrderExpand(orderId) {
            const card = document.getElementById('ooCard' + orderId);
            if (!card) return;

            if (card.classList.contains('expanded')) {
                card.classList.remove('expanded');
                return;
            }

            card.classList.add('expanded');

            // Load items for this order
            const itemsContainer = document.getElementById('ooItems' + orderId);
            try {
                const res = await fetch('online_order_api.php?action=get_order_details&order_id=' + orderId);
                const data = await res.json();
                if (data.success && data.order && data.order.items) {
                    itemsContainer.innerHTML = data.order.items.map(item => `
                        <div class="oo-item-row">
                            <span class="oo-item-name">${item.quantity}x ${escapeHtmlPos(item.product_name)}</span>
                            <span class="oo-item-subtotal">₱${parseFloat(item.subtotal).toFixed(2)}</span>
                        </div>
                    `).join('');
                } else {
                    itemsContainer.innerHTML = '<div style="font-size:0.8rem;color:var(--text-light);">Could not load items</div>';
                }
            } catch (err) {
                itemsContainer.innerHTML = '<div style="font-size:0.8rem;color:var(--text-light);">Error loading items</div>';
            }
        }

        // ─── Process Pickup Payment - Load order into POS cart ───
        let currentPickupOrderId = null;
        
        async function processPickupPayment(orderId) {
            try {
                // Fetch order details
                const res = await fetch('online_order_api.php?action=get_order_details&order_id=' + orderId);
                const data = await res.json();
                
                if (!data.success || !data.order || !data.order.items) {
                    showToast('Failed to load order details', 'error');
                    return;
                }
                
                const order = data.order;
                
                // Confirm with user
                const ok = await customConfirm(
                    'Process Pickup Payment',
                    `Load ${order.customer_name}'s order (${order.order_ref}) into POS cart for payment?`,
                    'info',
                    { confirmText: 'Yes, Load to Cart', cancelText: 'Cancel' }
                );
                
                if (!ok) return;
                
                // Clear current cart
                cart = [];
                
                // Load order items into cart using order data directly
                for (const item of order.items) {
                    cart.push({
                        id: item.product_id,
                        cartKey: item.product_id + '_box',
                        name: item.product_name,
                        price: parseFloat(item.price),
                        qty: parseInt(item.quantity),
                        maxStock: parseInt(item.quantity) + 100,
                        perPiece: false,
                        piecesPerBox: 1
                    });
                }
                
                // Store the pickup order ID so we can link it when payment is completed
                currentPickupOrderId = orderId;
                
                // Update cart UI
                updateCartUI();
                
                // Switch to POS products tab
                const productsTab = document.querySelector('.pos-main-tab');
                if (productsTab) switchPosTab('products', productsTab);
                
                // Show success message
                showToast(`Order loaded! Total: ₱${order.total_amount} - Customer: ${order.customer_name}`, 'success');
                
                // Show payment panel automatically
                setTimeout(() => {
                    document.getElementById('payBtn').click();
                }, 300);
                
            } catch (err) {
                console.error('Error loading pickup order:', err);
                showToast('Error loading order into cart', 'error');
            }
        }

        async function changeOrderStatus(orderId, newStatus) {
            if (newStatus === 'Cancelled') {
                const ok = await customConfirm('Cancel Order', 'Are you sure you want to cancel this order? Stock will be restored.', 'danger', { confirmText: 'Yes, Cancel Order', cancelText: 'Go Back' });
                if (!ok) return;
            }
            if (newStatus === 'Completed') {
                const ok = await customConfirm('Complete Order', 'Mark this order as completed? This will create a sale record in the system.', 'success', { confirmText: 'Yes, Complete', cancelText: 'Not Yet' });
                if (!ok) return;
            }

            try {
                const res = await fetch('online_order_api.php?action=update_order_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `order_id=${orderId}&status=${encodeURIComponent(newStatus)}`
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Order updated to ${newStatus}`, 'success');
                    loadOnlineOrders();
                    pollPendingOrderCount();
                } else {
                    showToast(data.message || 'Failed to update', 'error');
                }
            } catch (err) {
                showToast('Network error', 'error');
            }
        }

        // Poll pending online order count for the tab badge
        async function pollPendingOrderCount() {
            try {
                const res = await fetch('online_order_api.php?action=get_pending_count');
                const data = await res.json();
                if (data.success) {
                    const count = data.count;
                    const badge = document.getElementById('onlineOrdersBadge');
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.remove('hidden');

                        // Alert on new orders
                        if (count > lastPendingCount && lastPendingCount >= 0) {
                            const tab = document.getElementById('onlineOrdersTab');
                            tab.style.animation = 'none';
                            tab.offsetHeight;
                            tab.style.animation = 'bellShake 0.5s ease 2';
                        }
                        lastPendingCount = count;
                    } else {
                        badge.classList.add('hidden');
                        lastPendingCount = 0;
                    }
                }
            } catch (err) { /* ignore */ }
        }

        // Poll pending count on load and every 10 seconds
        pollPendingOrderCount();
        setInterval(pollPendingOrderCount, 10000);

        // Auto-refresh online orders panel if it's visible
        setInterval(() => {
            const panel = document.getElementById('onlineOrdersPanel');
            if (panel && panel.classList.contains('active')) {
                loadOnlineOrders();
            }
        }, 15000);

        // Auto-refresh product stock every 30 seconds to catch other terminals' sales
        setInterval(() => {
            loadProducts();
        }, 30000);
    </script>
</body>

</html>