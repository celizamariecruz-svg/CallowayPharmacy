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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Calloway Pharmacy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.9rem;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 0.7rem 0.7rem 0.7rem 2.4rem;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 0.95rem;
            background: var(--card-bg);
            color: var(--text-color);
            transition: border-color 0.15s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.08);
        }

        .search-input::placeholder { color: var(--text-light); opacity: 0.7; }

        .scan-btn {
            padding: 0 1rem;
            background: var(--card-bg);
            color: var(--text-color);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .scan-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }

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
        }

        .cat-tab:hover { color: var(--text-color); }

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
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .summary-row span:last-child { font-variant-numeric: tabular-nums; }

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

    <!-- Online Order Notification Bell -->
    <div class="online-notif-bell" id="onlineNotifBell" onclick="toggleNotifPanel()" title="Online Orders">
        <i class="fas fa-bell"></i>
        <span class="online-notif-badge hidden" id="notifBadge">0</span>
    </div>

    <!-- Notification Panel -->
    <div class="online-notif-panel" id="notifPanel">
        <div class="notif-panel-header">
            <h3><i class="fas fa-shopping-cart" style="color:var(--primary-color);"></i> Online Orders</h3>
            <button class="notif-mark-all" onclick="markAllNotifRead()">Mark all read</button>
        </div>
        <div class="notif-panel-body" id="notifPanelBody">
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
                        <button class="scan-btn" id="scanBtn">
                            <i class="fas fa-barcode"></i> Scan
                        </button>
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
                <button class="btn-print" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn-new" onclick="newSale()">
                    <i class="fas fa-plus"></i> New Sale
                </button>
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
        });

        // --- Data ---
        async function loadProducts() {
            try {
                const res = await fetch('inventory_api.php?action=get_products&limit=1000');
                const data = await res.json();
                if (data.success) {
                    products = data.data;
                    renderCategories();
                    renderProducts();
                }
            } catch (err) {
                console.error("Failed to load products", err);
                document.getElementById('productsGrid').innerHTML =
                    '<div class="no-products"><i class="fas fa-exclamation-triangle"></i>Failed to load products</div>';
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
                return `<button class="cat-tab ${cat === 'all' ? 'active' : ''}"
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
                    No products found${term ? ' for "' + term + '"' : ''}
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
                    <span class="p-name">${p.name}</span>
                    ${variant ? '<span style="font-size:0.7rem;color:var(--text-light);margin-top:-2px;">' + variant + '</span>' : ''}
                    <div class="p-bottom">
                        <span class="p-price">₱${parseFloat(p.selling_price).toFixed(2)}</span>
                        <span class="p-stock ${stockClass}">${stockLabel}</span>
                    </div>
                    ${canSellPiece && !isOut ? `<button class="piece-sell-btn" onclick="event.stopPropagation(); addToCart(${p.product_id}, true)" title="Sell per piece">
                        <i class="fas fa-tablets"></i> ₱${parseFloat(p.price_per_piece).toFixed(2)}/pc
                    </button>` : ''}
                </div>`;
            }).join('');

            grid.classList.toggle('list-view', viewMode === 'list');
        }

        // --- Cart ---
        function addToCart(productId, perPiece = false) {
            const product = products.find(p => p.product_id == productId);
            if (!product || parseInt(product.stock_quantity) <= 0) return;

            // Prescription warning
            if (parseInt(product.requires_prescription) === 1) {
                const alreadyInCart = cart.find(item => item.id == productId);
                if (!alreadyInCart) {
                    if (!confirm('⚠️ PRESCRIPTION REQUIRED\n\n"' + product.name + '" requires a valid prescription.\n\nPlease verify the customer has a valid prescription before proceeding.\n\nContinue?')) {
                        return;
                    }
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
                        <h4>${item.name}${item.perPiece ? ' <span style="color:var(--primary-color);font-size:0.7rem;">PIECE</span>' : ''}</h4>
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
            showToast('Sale held (feature coming)', 'success');
        }

        function updateTotals(subtotal) {
            const tax = subtotal * 0.12;
            const total = subtotal + tax;

            document.getElementById('subtotalDisplay').textContent = '₱' + subtotal.toFixed(2);
            document.getElementById('taxDisplay').textContent = '₱' + tax.toFixed(2);
            document.getElementById('totalDisplay').textContent = '₱' + total.toFixed(2);
            document.getElementById('modalTotalDisplay').textContent = '₱' + total.toFixed(2);
        }

        // --- Payment ---
        function openPaymentModal() {
            if (cart.length === 0) return;
            document.getElementById('paymentModal').classList.add('active');
            document.getElementById('amountTendered').value = '';

            // Generate quick cash buttons based on total
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const total = subtotal * 1.12;
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
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const total = subtotal * 1.12;
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
            btn.disabled = true;
            btn.textContent = 'Processing...';

            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const total = subtotal * 1.12;

            const now = new Date();
            const saleData = {
                items: cart,
                total: total,
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
            } catch (err) {
                console.error('Sale error:', err);
                showToast('Network error — sale not saved', 'error');
                btn.disabled = false;
                btn.textContent = 'Complete Sale';
                return;
            }

            closePaymentModal();
            lastSaleData = saleData;
            renderReceipt(saleData);
            document.getElementById('receiptModal').classList.add('active');
            btn.disabled = false;
            btn.textContent = 'Complete Sale';
        }

        function newSale() {
            cart = [];
            updateCartUI();
            document.getElementById('receiptModal').classList.remove('active');
            document.getElementById('searchInput').focus();
            // Refresh product list to reflect updated stock
            loadProducts();
        }

        async function printReceipt() {
            if (!lastSaleData) {
                showToast('No receipt to print', 'error');
                return;
            }

            const ok = await sendReceiptToPrinter(lastSaleData);
            if (!ok) {
                showToast('Printer error. Check server log.', 'error');
                return;
            }

            showToast('Receipt sent to printer', 'success');
        }

        async function sendReceiptToPrinter(payload) {
            try {
                const res = await fetch('print_receipt.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                return data && data.success === true;
            } catch (err) {
                console.error('Print error:', err);
                return false;
            }
        }

        function renderReceipt(saleData) {
            const receiptEl = document.getElementById('receiptContent');
            const cashierName = document.body.dataset.cashierName || 'Cashier';
            const receiptNo = saleData.receipt_no || 'TX-' + Date.now().toString().slice(-8);
            const createdAt = saleData.created_at ? new Date(saleData.created_at) : new Date();

            const subtotal = saleData.items.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const tax = subtotal * 0.12;
            const total = subtotal + tax;
            const tendered = saleData.amount_tendered || total;
            const change = Math.max(0, tendered - total);

            const rows = saleData.items.map(item => {
                const lineTotal = item.price * item.qty;
                return `
                    <tr>
                        <td>
                            <div style="font-weight: 600;">${item.name}</div>
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
                    <div class="row total"><span>Total</span><span>₱${total.toFixed(2)}</span></div>
                    <div class="row"><span>Paid (${saleData.payment_method})</span><span>₱${tendered.toFixed(2)}</span></div>
                    <div class="row"><span>Change</span><span>₱${change.toFixed(2)}</span></div>
                </div>

                <div class="receipt-footer">
                    Thank you! Get well soon.
                </div>
            `;
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
        let notifPanelOpen = false;

        function toggleNotifPanel() {
            const panel = document.getElementById('notifPanel');
            notifPanelOpen = !notifPanelOpen;
            if (notifPanelOpen) {
                panel.classList.add('active');
                loadNotifications();
            } else {
                panel.classList.remove('active');
            }
        }

        // Close panel on outside click
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('notifPanel');
            const bell = document.getElementById('onlineNotifBell');
            if (notifPanelOpen && !panel.contains(e.target) && !bell.contains(e.target)) {
                notifPanelOpen = false;
                panel.classList.remove('active');
            }
        });

        async function loadNotifications() {
            try {
                const res = await fetch('online_order_api.php?action=get_notifications&limit=20');
                const data = await res.json();
                if (data.success) {
                    renderNotifications(data.notifications);
                }
            } catch (err) {
                console.error('Failed to load notifications:', err);
            }
        }

        function renderNotifications(notifications) {
            const body = document.getElementById('notifPanelBody');
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
                pollUnreadCount();
                loadNotifications();
            } catch (err) { /* ignore */ }
        }

        async function markAllNotifRead() {
            try {
                await fetch('online_order_api.php?action=mark_all_read');
                pollUnreadCount();
                loadNotifications();
                showToast('All notifications marked as read', 'success');
            } catch (err) { /* ignore */ }
        }

        async function pollUnreadCount() {
            try {
                const res = await fetch('online_order_api.php?action=get_unread_count');
                const data = await res.json();
                if (data.success) {
                    const count = data.count;
                    const badge = document.getElementById('notifBadge');
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
        pollUnreadCount();
        setInterval(pollUnreadCount, 10000);

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
                    html += `<button class="oo-action-btn complete-btn" onclick="changeOrderStatus(${order.order_id}, 'Completed')"><i class="fas fa-flag-checkered"></i> Complete (Picked Up)</button>`;
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

        async function changeOrderStatus(orderId, newStatus) {
            if (newStatus === 'Cancelled') {
                if (!confirm('Are you sure you want to cancel this order? Stock will be restored.')) return;
            }
            if (newStatus === 'Completed') {
                if (!confirm('Mark this order as completed? This will create a sale record in the system.')) return;
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
    </script>
</body>

</html>