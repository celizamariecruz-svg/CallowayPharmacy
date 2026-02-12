<?php
// SIMPLIFIED TEST VERSION - process_sale_simple.php
header('Content-Type: application/json');

try {
    // Just return success
    echo json_encode([
        'success' => true,
        'message' => 'Test response',
        'test' => 'This is a simple test'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
?>
