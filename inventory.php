<?php
// inventory.php
// Inventory management page for Calloway Pharmacy
// - Uses existing tables: products, transactions, logs, employees
// - Bootstrap 5 UI matching POS style (white/blue theme)
// - Do NOT modify pos.php or database schema

require_once 'db_connection.php';
session_start();

// Use session employee if available, otherwise default to employee id 1
$employee_id = isset($_SESSION['employee_id']) ? intval($_SESSION['employee_id']) : 1;

// Helper: JSON response
function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// POST actions for inventory adjustments and product management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_stock') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $qty = intval($_POST['quantity'] ?? 0);
        if ($product_id <= 0 || $qty <= 0) json_response(['ok'=>false,'error'=>'Invalid input']);

        $conn->begin_transaction();
        try {
            // get current
            $stmt = $conn->prepare('SELECT name, stock_quantity FROM products WHERE product_id = ? LIMIT 1');
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || $res->num_rows === 0) throw new Exception('Product not found');
            $p = $res->fetch_assoc();
            $old = intval($p['stock_quantity']);
            $new = $old + $qty;

            $up = $conn->prepare('UPDATE products SET stock_quantity = ? WHERE product_id = ?');
            $up->bind_param('ii', $new, $product_id);
            if (!$up->execute()) throw new Exception('Failed to update stock');

            $actionText = sprintf("Add Stock: %s (old=%d -> new=%d) +%d", $p['name'], $old, $new, $qty);
            $log = $conn->prepare('INSERT INTO logs (action, user_id) VALUES (?, ?)');
            $log->bind_param('si', $actionText, $employee_id);
            $log->execute();

            $conn->commit();
            json_response(['ok'=>true,'product_id'=>$product_id,'old'=>$old,'new'=>$new]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }

    if ($action === 'reduce_stock') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $qty = intval($_POST['quantity'] ?? 0);
        if ($product_id <= 0 || $qty <= 0) json_response(['ok'=>false,'error'=>'Invalid input']);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('SELECT name, stock_quantity FROM products WHERE product_id = ? LIMIT 1');
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || $res->num_rows === 0) throw new Exception('Product not found');
            $p = $res->fetch_assoc();
            $old = intval($p['stock_quantity']);
            $new = $old - $qty;
            if ($new < 0) throw new Exception('Insufficient stock');

            $up = $conn->prepare('UPDATE products SET stock_quantity = ? WHERE product_id = ?');
            $up->bind_param('ii', $new, $product_id);
            if (!$up->execute()) throw new Exception('Failed to update stock');

            $actionText = sprintf("Reduce Stock: %s (old=%d -> new=%d) -%d", $p['name'], $old, $new, $qty);
            $log = $conn->prepare('INSERT INTO logs (action, user_id) VALUES (?, ?)');
            $log->bind_param('si', $actionText, $employee_id);
            $log->execute();

            $conn->commit();
            json_response(['ok'=>true,'product_id'=>$product_id,'old'=>$old,'new'=>$new]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }

    if ($action === 'edit_product') {
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id <= 0) json_response(['ok'=>false,'error'=>'Invalid product id']);
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $expiry_date = $_POST['expiry_date'] ?? null;
        $location = $_POST['location'] ?? null;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('SELECT name, category, price, expiry_date, location FROM products WHERE product_id = ? LIMIT 1');
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || $res->num_rows === 0) throw new Exception('Product not found');
            $old = $res->fetch_assoc();

            $up = $conn->prepare('UPDATE products SET name = ?, category = ?, price = ?, expiry_date = ?, location = ? WHERE product_id = ?');
            $up->bind_param('ssdssi', $name, $category, $price, $expiry_date, $location, $product_id);
            if (!$up->execute()) throw new Exception('Failed to update product');

            // Build change log
            $changes = [];
            if ($old['name'] !== $name) $changes[] = sprintf('name: "%s" -> "%s"', $old['name'], $name);
            if ($old['category'] !== $category) $changes[] = sprintf('category: "%s" -> "%s"', $old['category'], $category);
            if (floatval($old['price']) !== floatval($price)) $changes[] = sprintf('price: %.2f -> %.2f', $old['price'], $price);
            if ($old['expiry_date'] !== $expiry_date) $changes[] = sprintf('expiry: %s -> %s', $old['expiry_date'], $expiry_date);
            if ($old['location'] !== $location) $changes[] = sprintf('location: %s -> %s', $old['location'], $location);

            $actionText = 'Edit Product: ' . $old['name'] . ' (' . implode('; ', $changes) . ')';
            $log = $conn->prepare('INSERT INTO logs (action, user_id) VALUES (?, ?)');
            $log->bind_param('si', $actionText, $employee_id);
            $log->execute();

            $conn->commit();
            json_response(['ok'=>true]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }

    if ($action === 'deactivate_product') {
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id <= 0) json_response(['ok'=>false,'error'=>'Invalid product id']);
        try {
            $stmt = $conn->prepare('SELECT name, is_active FROM products WHERE product_id = ? LIMIT 1');
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || $res->num_rows === 0) throw new Exception('Product not found');
            $p = $res->fetch_assoc();

            $newActive = ($p['is_active'] == 1) ? 0 : 1;
            $up = $conn->prepare('UPDATE products SET is_active = ? WHERE product_id = ?');
            $up->bind_param('ii', $newActive, $product_id);
            if (!$up->execute()) throw new Exception('Failed to update active state');

            $actionText = sprintf('Set active=%d for %s', $newActive, $p['name']);
            $log = $conn->prepare('INSERT INTO logs (action, user_id) VALUES (?, ?)');
            $log->bind_param('si', $actionText, $employee_id);
            $log->execute();

            json_response(['ok'=>true,'active'=>$newActive]);
        } catch (Exception $e) {
            json_response(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }

    if ($action === 'delete_product') {
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id <= 0) json_response(['ok'=>false,'error'=>'Invalid product id']);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('SELECT name, stock_quantity FROM products WHERE product_id = ? LIMIT 1');
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || $res->num_rows === 0) throw new Exception('Product not found');
            $p = $res->fetch_assoc();

            $del = $conn->prepare('DELETE FROM products WHERE product_id = ?');
            $del->bind_param('i', $product_id);
            if (!$del->execute()) throw new Exception('Failed to delete product');

            $actionText = sprintf('Delete Product: %s (stock=%d)', $p['name'], $p['stock_quantity']);
            $log = $conn->prepare('INSERT INTO logs (action, user_id) VALUES (?, ?)');
            $log->bind_param('si', $actionText, $employee_id);
            $log->execute();

            $conn->commit();
            json_response(['ok'=>true]);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }

    json_response(['ok'=>false,'error'=>'Unknown action']);
}

// GET endpoints to return JSON data for front-end
if (isset($_GET['list_products']) && $_GET['list_products'] == '1') {
    $rows = [];
    $res = $conn->query('SELECT product_id, name, category, price, stock_quantity, expiry_date, location, is_active FROM products ORDER BY name');
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    json_response(['ok'=>true,'products'=>$rows]);
}

if (isset($_GET['list_logs']) && $_GET['list_logs'] == '1') {
    $rows = [];
    $q = "SELECT l.id, l.action, l.user_id, l.timestamp, e.name as employee_name FROM logs l LEFT JOIN employees e ON l.user_id = e.id ORDER BY l.timestamp DESC LIMIT 200";
    $res = $conn->query($q);
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    json_response(['ok'=>true,'logs'=>$rows]);
}

if (isset($_GET['expiring']) && in_array($_GET['expiring'], ['soon','expired'])) {
    $type = $_GET['expiring'];
    $today = new DateTime();
    if ($type === 'expired') {
        $q = $conn->prepare('SELECT product_id, name, category, price, stock_quantity, expiry_date FROM products WHERE expiry_date <= ? ORDER BY expiry_date ASC');
        $d = $today->format('Y-m-d');
        $q->bind_param('s', $d);
        $q->execute();
        $res = $q->get_result();
    } else {
        $limit = new DateTime(); $limit->modify('+30 days');
        $ld = $limit->format('Y-m-d');
        $td = $today->format('Y-m-d');
        $q = $conn->prepare('SELECT product_id, name, category, price, stock_quantity, expiry_date FROM products WHERE expiry_date > ? AND expiry_date <= ? ORDER BY expiry_date ASC');
        $q->bind_param('ss', $td, $ld);
        $q->execute();
        $res = $q->get_result();
    }
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    json_response(['ok'=>true,'items'=>$rows]);
}

// If no endpoints matched, render the page HTML
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inventory - Calloway Pharmacy</title>
  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{--primary:#0a74da}
    body{font-family:Segoe UI,Arial,Helvetica,sans-serif;background:#f5f7fa}
    header{background:var(--primary);color:#fff;padding:1rem}
    .card{border-radius:8px}
    .product-row.low{background:#fffbe6}
    .product-row.out{background:#ffecec}
    .product-row.expiring{background:#fff4e6}
    .table-actions button{margin-right:.3rem}
    .small{font-size:.9rem}
  </style>
</head>
<body>
<header class="d-flex align-items-center justify-content-between">
  <div class="ms-3">
    <h4 class="mb-0">Calloway Pharmacy</h4>
    <div class="small">Inventory Management</div>
  </div>
  <div class="me-3 text-white small">Simple POS built from inventory manager</div>
</header>
<div class="container-fluid p-4">
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="mb-0">Product Inventory</h5>
          <div>
            <button class="btn btn-outline-primary btn-sm" id="refresh-products">Refresh</button>
            <button class="btn btn-primary btn-sm" id="add-product-btn" data-bs-toggle="modal" data-bs-target="#editModal">Add New</button>
          </div>
        </div>
        <hr>
        <div class="table-responsive">
          <table class="table table-hover align-middle" id="products-table">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Expiry</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="products-tbody">
              <!-- filled by JS -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3 mb-3">
        <h6>Expiry Tracking</h6>
        <div class="mt-2">
          <button class="btn btn-sm btn-warning w-100 mb-2" id="show-expiring">Expiring Soon (<=30d)</button>
          <button class="btn btn-sm btn-danger w-100 mb-2" id="show-expired">Expired</button>
        </div>
        <div id="expiry-list" class="mt-3 small"></div>
      </div>

      <div class="card p-3">
        <h6>Inventory Logs</h6>
        <div class="table-responsive" style="max-height:360px;overflow:auto">
          <table class="table table-sm table-striped small mb-0">
            <thead><tr><th>When</th><th>Action</th><th>By</th></tr></thead>
            <tbody id="logs-tbody">
              <!-- filled by JS -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modals -->
<!-- Edit/Add Product Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="edit-form">
        <div class="modal-header">
          <h5 class="modal-title">Edit Product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="product_id" id="product_id" />
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input class="form-control" name="name" id="p_name" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <input class="form-control" name="category" id="p_category" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Price (₱)</label>
              <input class="form-control" type="number" step="0.01" name="price" id="p_price" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Stock</label>
              <input class="form-control" type="number" id="p_stock" disabled />
            </div>
            <div class="col-md-4">
              <label class="form-label">Expiry</label>
              <input class="form-control" type="date" name="expiry_date" id="p_expiry" />
            </div>
            <div class="col-12">
              <label class="form-label">Location</label>
              <input class="form-control" name="location" id="p_location" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Stock Adjust Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="adjust-form">
        <div class="modal-header">
          <h5 class="modal-title" id="adjust-title">Adjust Stock</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="adj_product_id" name="product_id" />
          <div class="mb-2">
            <label class="form-label">Quantity</label>
            <input class="form-control" type="number" name="quantity" id="adj_qty" min="1" value="1" />
          </div>
          <div id="adj-note" class="small text-muted">Enter amount to add or reduce.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="adj-submit">Apply</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDelete" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <p>Are you sure you want to delete this product? This action cannot be undone.</p>
        <div class="text-end">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger" id="confirm-delete-btn">Delete</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Front-end logic: load products, logs, expiry lists, handle modals
const api = (data) => fetch('inventory.php', {method:'POST', body:data}).then(r=>r.json());
const fetchJson = (url) => fetch(url).then(r=>r.json());

async function loadProducts(){
  const res = await fetch('inventory.php?list_products=1');
  const json = await res.json();
  const tbody = document.getElementById('products-tbody');
  tbody.innerHTML = '';
  json.products.forEach(p=>{
    const tr = document.createElement('tr');
    tr.dataset.productId = p.product_id;
    // color coding
    const expDate = p.expiry_date ? new Date(p.expiry_date) : null;
    const today = new Date();
    if (p.stock_quantity <= 0) tr.classList.add('out');
    else if (p.stock_quantity < 10) tr.classList.add('low');
    if (expDate){
      const diff = Math.ceil((expDate - today)/(1000*60*60*24));
      if (diff < 0) tr.classList.add('expiring');
      else if (diff <= 30) tr.classList.add('expiring');
    }

    tr.innerHTML = `
      <td>${p.product_id}</td>
      <td>${escapeHtml(p.name)}</td>
      <td>${escapeHtml(p.category || '')}</td>
      <td>₱${Number(p.price).toFixed(2)}</td>
      <td>${p.stock_quantity}</td>
      <td>${p.expiry_date || ''}</td>
      <td class="table-actions">
        <button class="btn btn-sm btn-success" data-action="add" data-id="${p.product_id}">Add Stock</button>
        <button class="btn btn-sm btn-warning" data-action="reduce" data-id="${p.product_id}">Reduce</button>
        <button class="btn btn-sm btn-outline-primary" data-action="edit" data-id="${p.product_id}">Edit</button>
        <button class="btn btn-sm btn-secondary" data-action="deactivate" data-id="${p.product_id}">Deactivate</button>
        <button class="btn btn-sm btn-danger" data-action="delete" data-id="${p.product_id}">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

async function loadLogs(){
  const res = await fetch('inventory.php?list_logs=1');
  const json = await res.json();
  const tbody = document.getElementById('logs-tbody');
  tbody.innerHTML = '';
  json.logs.forEach(l=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${escapeHtml(l.timestamp)}</td><td>${escapeHtml(l.action)}</td><td>${escapeHtml(l.employee_name || l.user_id)}</td>`;
    tbody.appendChild(tr);
  });
}

async function loadExpiry(type){
  const res = await fetch('inventory.php?expiring=' + type);
  const json = await res.json();
  const div = document.getElementById('expiry-list');
  if (!json.items || json.items.length === 0) { div.innerHTML = '<div class="small text-muted">No items</div>'; return; }
  div.innerHTML = '';
  json.items.forEach(i=>{
    const el = document.createElement('div');
    el.className = 'd-flex justify-content-between align-items-center mb-2';
    el.innerHTML = `<div>${escapeHtml(i.name)} <div class="small text-muted">Expiry: ${i.expiry_date}</div></div><div class="text-end">Stock: ${i.stock_quantity}</div>`;
    div.appendChild(el);
  });
}

function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// event delegation for product actions
document.getElementById('products-tbody').addEventListener('click', async (e)=>{
  const btn = e.target.closest('button');
  if (!btn) return;
  const action = btn.dataset.action;
  const id = btn.dataset.id;
  if (action === 'add' || action === 'reduce'){
    document.getElementById('adj_product_id').value = id;
    document.getElementById('adj_qty').value = 1;
    document.getElementById('adjust-title').textContent = (action==='add'?'Add Stock':'Reduce Stock');
    document.getElementById('adj-submit').dataset.mode = action;
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
    return;
  }
  if (action === 'edit'){
    // load product data
    const row = btn.closest('tr');
    const cells = row.children;
    document.getElementById('product_id').value = id;
    document.getElementById('p_name').value = cells[1].innerText.trim();
    document.getElementById('p_category').value = cells[2].innerText.trim();
    const priceText = cells[3].innerText.replace('₱','').trim();
    document.getElementById('p_price').value = priceText;
    document.getElementById('p_stock').value = cells[4].innerText.trim();
    document.getElementById('p_expiry').value = cells[5].innerText.trim();
    document.getElementById('p_location').value = '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
    return;
  }
  if (action === 'deactivate'){
    if (!confirm('Toggle active state for this product?')) return;
    const fd = new FormData(); fd.append('action','deactivate_product'); fd.append('product_id',id);
    const res = await fetch('inventory.php',{method:'POST',body:fd}); const js = await res.json();
    if (!js.ok) return alert(js.error||'Error');
    loadProducts(); loadLogs();
    return;
  }
  if (action === 'delete'){
    document.getElementById('confirm-delete-btn').dataset.id = id;
    new bootstrap.Modal(document.getElementById('confirmDelete')).show();
    return;
  }
});

// handle adjust form
document.getElementById('adjust-form').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const mode = document.getElementById('adj-submit').dataset.mode;
  const id = document.getElementById('adj_product_id').value;
  const qty = document.getElementById('adj_qty').value;
  const fd = new FormData(); fd.append('product_id', id); fd.append('quantity', qty);
  fd.append('action', mode==='add'?'add_stock':'reduce_stock');
  const res = await fetch('inventory.php',{method:'POST',body:fd}); const js = await res.json();
  if (!js.ok) return alert(js.error||'Error');
  bootstrap.Modal.getInstance(document.getElementById('adjustModal')).hide();
  loadProducts(); loadLogs();
});

// handle edit/save product
document.getElementById('edit-form').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action','edit_product');
  const res = await fetch('inventory.php',{method:'POST',body:fd}); const js = await res.json();
  if (!js.ok) return alert(js.error||'Error');
  bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
  loadProducts(); loadLogs();
});

// confirm delete
document.getElementById('confirm-delete-btn').addEventListener('click', async function(){
  const id = this.dataset.id; const fd = new FormData(); fd.append('action','delete_product'); fd.append('product_id', id);
  const res = await fetch('inventory.php',{method:'POST',body:fd}); const js = await res.json();
  if (!js.ok) return alert(js.error||'Error');
  bootstrap.Modal.getInstance(document.getElementById('confirmDelete')).hide();
  loadProducts(); loadLogs();
});

// refresh and expiry
document.getElementById('refresh-products').addEventListener('click', ()=>{ loadProducts(); loadLogs(); });
document.getElementById('show-expiring').addEventListener('click', ()=> loadExpiry('soon'));
document.getElementById('show-expired').addEventListener('click', ()=> loadExpiry('expired'));

// initial load
loadProducts(); loadLogs();

// Fix suggestion for pos.php CSS printing bug:
// If pos.php prints raw CSS at the bottom of the page, it usually means there's stray text after </html> or an unclosed <style> tag.
// To fix pos.php: open pos.php and ensure the file ends exactly with "</body>\n</html>" and nothing after it.
// Also ensure every <style> block is closed with </style> before </head> or before ending the file.

</script>
</body>
</html>
