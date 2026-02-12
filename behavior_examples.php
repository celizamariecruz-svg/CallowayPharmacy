<?php
/**
 * Behavior Tree Engine - Usage Examples
 * How to implement and use the stage-based behavior system
 */

require_once 'db_connection.php';
require_once 'BehaviorTreeEngine.php';

// Initialize the behavior tree engine
$engine = new BehaviorTreeEngine($conn);

/**
 * EXAMPLE 1: Run a full cycle to evaluate all medicines
 */
function exampleFullCycle() {
    global $engine;
    
    $cycle_results = $engine->runFullCycle();
    
    echo json_encode([
        'success' => true,
        'message' => 'Behavior cycle completed',
        'total_medicines_evaluated' => $cycle_results['total_evaluated'],
        'timestamp' => $cycle_results['timestamp'],
        'alerts_generated' => count($cycle_results['alerts']),
        'stage_transitions' => count($cycle_results['transitions']),
        'summary' => [
            'results' => $cycle_results['results'],
            'alerts' => $cycle_results['alerts'],
            'transitions' => $cycle_results['transitions']
        ]
    ], JSON_PRETTY_PRINT);
}

/**
 * EXAMPLE 2: Check stage of a specific medicine
 */
function exampleCheckIndividualMedicine($product_id) {
    global $engine;
    
    $stage_info = $engine->getCurrentStage($product_id);
    
    echo json_encode([
        'product_id' => $product_id,
        'current_stage' => $stage_info['stage'],
        'color' => $stage_info['color'],
        'alert_type' => $stage_info['alert_type'],
        'actions_triggered' => $stage_info['actions_triggered']
    ], JSON_PRETTY_PRINT);
}

/**
 * EXAMPLE 3: Get stage statistics for dashboard
 */
function exampleGetStageStats() {
    global $engine;
    
    $stats = $engine->getStageStatistics();
    
    echo json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'stage_distribution' => $stats
    ], JSON_PRETTY_PRINT);
}

/**
 * EXAMPLE 4: Automated daemon - Run continuously
 * Add to cron job: */5 * * * * php behavior_daemon.php
 */
function exampleAutomatedDaemon() {
    global $engine;
    
    // Run behavior tree evaluation every 5 minutes
    $results = $engine->runFullCycle();
    
    // Log results
    $log_file = 'logs/behavior_engine.log';
    $log_entry = date('Y-m-d H:i:s') . ' - Cycle Results: ' . json_encode($results) . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    return $results;
}

/**
 * EXAMPLE 5: Process specific stage manually
 */
function exampleManualStageProcessing($stage_name) {
    global $conn;
    
    $query = "SELECT * FROM products WHERE current_stage = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $stage_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $medicines = [];
    while ($row = $result->fetch_assoc()) {
        $medicines[] = $row;
    }
    
    echo json_encode([
        'stage' => $stage_name,
        'count' => count($medicines),
        'medicines' => $medicines
    ], JSON_PRETTY_PRINT);
}

// Use case based on request parameter
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'full_cycle':
            exampleFullCycle();
            break;
            
        case 'check_medicine':
            if (isset($_GET['product_id'])) {
                exampleCheckIndividualMedicine((int)$_GET['product_id']);
            }
            break;
            
        case 'stats':
            exampleGetStageStats();
            break;
            
        case 'stage_products':
            if (isset($_GET['stage'])) {
                exampleManualStageProcessing($_GET['stage']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
} else {
    echo json_encode([
        'available_actions' => [
            'full_cycle' => '/behavior_examples.php?action=full_cycle',
            'check_medicine' => '/behavior_examples.php?action=check_medicine&product_id=1',
            'stats' => '/behavior_examples.php?action=stats',
            'stage_products' => '/behavior_examples.php?action=stage_products&stage=LOW_STOCK'
        ]
    ]);
}
