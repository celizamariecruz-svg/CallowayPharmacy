<?php
/**
 * Upload product image
 * POST multipart/form-data with field "image" and "product_id"
 */
require_once 'db_connection.php';
require_once 'Auth.php';

header('Content-Type: application/json');

$auth = new Auth($conn);
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$productId = intval($_POST['product_id'] ?? 0);

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No image uploaded or upload error']);
    exit;
}

$file = $_FILES['image'];
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid image type. Allowed: JPG, PNG, WebP, GIF.']);
    exit;
}

$ext = 'jpg';
switch ($mime) {
    case 'image/jpeg':
        $ext = 'jpg';
        break;
    case 'image/png':
        $ext = 'png';
        break;
    case 'image/webp':
        $ext = 'webp';
        break;
    case 'image/gif':
        $ext = 'gif';
        break;
}

// Generate unique filename
$filename = 'product_' . ($productId ?: 'new') . '_' . time() . '.' . $ext;
$filepath = $uploadDir . $filename;
$relPath = 'uploads/products/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save image']);
    exit;
}

// If product_id given, update the DB
if ($productId > 0) {
    // Delete old image file if exists
    $stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && $row['image_url'] && file_exists(__DIR__ . '/' . $row['image_url'])) {
        @unlink(__DIR__ . '/' . $row['image_url']);
    }

    $stmt = $conn->prepare("UPDATE products SET image_url = ? WHERE product_id = ?");
    $stmt->bind_param("si", $relPath, $productId);
    $stmt->execute();
}

echo json_encode([
    'success'   => true,
    'message'   => 'Image uploaded successfully',
    'image_url' => $relPath
]);
