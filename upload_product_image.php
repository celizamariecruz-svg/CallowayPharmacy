<?php
/**
 * Upload product image
 * POST multipart/form-data with field "image" and "product_id"
 */
require_once 'db_connection.php';
require_once 'Auth.php';

header('Content-Type: application/json');

function uploadJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

try {
    $auth = new Auth($conn);
    if (!$auth->isLoggedIn()) {
        uploadJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    $uploadDir = __DIR__ . '/uploads/products/';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        uploadJson(['success' => false, 'message' => 'Unable to create upload folder']);
    }

    $productId = intval($_POST['product_id'] ?? 0);

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['image']['error'] ?? null;
        uploadJson(['success' => false, 'message' => 'No image uploaded or upload error', 'error_code' => $errorCode]);
    }

    $file = $_FILES['image'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    if (($file['size'] ?? 0) > $maxSize) {
        uploadJson(['success' => false, 'message' => 'Image too large. Max 5MB.']);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($file['tmp_name']);
    }

    if (!in_array($mime, $allowedTypes, true)) {
        $nameExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $extToMime = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif'
        ];
        if (isset($extToMime[$nameExt])) {
            $mime = $extToMime[$nameExt];
        }
    }

    if (!in_array($mime, $allowedTypes, true)) {
        uploadJson(['success' => false, 'message' => 'Invalid image type. Allowed: JPG, PNG, WebP, GIF.', 'mime' => $mime]);
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

    $filename = 'product_' . ($productId ?: 'new') . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;
    $relPath = 'uploads/products/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        uploadJson([
            'success' => false,
            'message' => 'Failed to save image. Check folder permissions for uploads/products'
        ]);
    }

    if ($productId > 0) {
        $stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['image_url'] && file_exists(__DIR__ . '/' . $row['image_url'])) {
            @unlink(__DIR__ . '/' . $row['image_url']);
        }

        $stmt = $conn->prepare("UPDATE products SET image_url = ? WHERE product_id = ?");
        $stmt->bind_param("si", $relPath, $productId);
        if (!$stmt->execute()) {
            @unlink($filepath);
            uploadJson(['success' => false, 'message' => 'Image uploaded but failed to update product record']);
        }
    }

    uploadJson([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'image_url' => $relPath
    ]);
} catch (Throwable $e) {
    error_log('upload_product_image error: ' . $e->getMessage());
    uploadJson([
        'success' => false,
        'message' => 'Server error while uploading image',
        'detail' => $e->getMessage()
    ], 500);
}
