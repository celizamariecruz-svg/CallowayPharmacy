<?php
/**
 * Stage-Based Entity Behavior Tree Algorithm
 * Similar to: "Eye Can See You: 3D Time Loop Survival Game"
 * Adapted for Pharmacy Inventory Management
 * 
 * This system treats medicines as entities with behavioral states
 * that transition through stages based on conditions, triggering
 * automated actions and workflows at each stage.
 */

class BehaviorTreeNode {
    public $id;
    public $name;
    public $type; // 'condition', 'action', 'sequence', 'selector'
    public $children = [];
    public $parent = null;
    public $status = 'idle'; // idle, running, success, failure
    
    public function __construct($id, $name, $type = 'action') {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
    }
    
    public function addChild($node) {
        $node->parent = $this;
        $this->children[] = $node;
        return $this;
    }
}

class MedicineEntity {
    public $product_id;
    public $name;
    public $stock_quantity;
    public $expiry_date;
    public $price;
    public $category;
    public $location;
    public $current_stage;
    public $stage_duration;
    public $actions_triggered = [];
    public $time_entered_stage;
    
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        $this->time_entered_stage = time();
        $this->actions_triggered = [];
    }
}

class StageDefinition {
    public $stage_name;
    public $priority; // 1 = highest
    public $conditions = []; // array of condition callbacks
    public $actions = []; // array of action callbacks
    public $color_code;
    public $alert_type;
    
    public function __construct($stage_name, $priority = 5) {
        $this->stage_name = $stage_name;
        $this->priority = $priority;
    }
    
    public function addCondition($callback) {
        $this->conditions[] = $callback;
        return $this;
    }
    
    public function addAction($callback) {
        $this->actions[] = $callback;
        return $this;
    }
}

class BehaviorTreeEngine {
    private $conn;
    private $medicines = [];
    private $stages = [];
    private $tree_root = null;
    private $stage_transition_log = [];
    private $alerts = [];
    
    // Stage constants matching pharmacy workflow
    const STAGE_NORMAL = 'NORMAL';
    const STAGE_LOW_STOCK = 'LOW_STOCK';
    const STAGE_CRITICAL_STOCK = 'CRITICAL_STOCK';
    const STAGE_EXPIRING_SOON = 'EXPIRING_SOON';
    const STAGE_EXPIRED = 'EXPIRED';
    const STAGE_OUT_OF_STOCK = 'OUT_OF_STOCK';
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeStages();
        $this->buildBehaviorTree();
    }
    
    /**
     * Initialize all possible stages with their conditions and actions
     */
    private function initializeStages() {
        // STAGE 1: NORMAL - Medicine is in good stock and safe condition
        $normal = new StageDefinition(self::STAGE_NORMAL, 1);
        $normal->color_code = '#4CAF50';
        $normal->alert_type = 'info';
        $normal->addCondition(function($entity) {
            $days_to_expiry = $this->getDaysToExpiry($entity->expiry_date);
            return $entity->stock_quantity > 50 && $days_to_expiry > 90;
        });
        $normal->addAction(function($entity) {
            // Normal state - standard operations
            $this->logStageTransition($entity->product_id, self::STAGE_NORMAL, 'Operation Normal');
        });
        $this->stages[self::STAGE_NORMAL] = $normal;
        
        // STAGE 2: LOW_STOCK - Stock falling below threshold but not critical
        $low_stock = new StageDefinition(self::STAGE_LOW_STOCK, 2);
        $low_stock->color_code = '#FF9800';
        $low_stock->alert_type = 'warning';
        $low_stock->addCondition(function($entity) {
            $days_to_expiry = $this->getDaysToExpiry($entity->expiry_date);
            return $entity->stock_quantity > 0 && $entity->stock_quantity <= 50 && $days_to_expiry > 90;
        });
        $low_stock->addAction(function($entity) {
            $this->createAlert($entity->product_id, 'LOW_STOCK', 'Stock level low: ' . $entity->stock_quantity . ' units', 'warning');
            $this->triggerAutoReorder($entity);
            $this->logStageTransition($entity->product_id, self::STAGE_LOW_STOCK, 'Reorder triggered - Stock: ' . $entity->stock_quantity);
        });
        $this->stages[self::STAGE_LOW_STOCK] = $low_stock;
        
        // STAGE 3: CRITICAL_STOCK - Immediate restocking needed
        $critical = new StageDefinition(self::STAGE_CRITICAL_STOCK, 3);
        $critical->color_code = '#F44336';
        $critical->alert_type = 'critical';
        $critical->addCondition(function($entity) {
            $days_to_expiry = $this->getDaysToExpiry($entity->expiry_date);
            return $entity->stock_quantity > 0 && $entity->stock_quantity <= 20 && $days_to_expiry > 90;
        });
        $critical->addAction(function($entity) {
            $this->createAlert($entity->product_id, 'CRITICAL_STOCK', 'CRITICAL: Only ' . $entity->stock_quantity . ' units left!', 'critical');
            $this->triggerUrgentReorder($entity);
            $this->notifyPharmacist($entity, 'CRITICAL_STOCK');
            $this->logStageTransition($entity->product_id, self::STAGE_CRITICAL_STOCK, 'CRITICAL - Urgent reorder + Pharmacist notified');
        });
        $this->stages[self::STAGE_CRITICAL_STOCK] = $critical;
        
        // STAGE 4: EXPIRING_SOON - Medicine approaching expiration
        $expiring = new StageDefinition(self::STAGE_EXPIRING_SOON, 4);
        $expiring->color_code = '#E91E63';
        $expiring->alert_type = 'warning';
        $expiring->addCondition(function($entity) {
            $days_to_expiry = $this->getDaysToExpiry($entity->expiry_date);
            return $days_to_expiry > 0 && $days_to_expiry <= 90 && $entity->stock_quantity > 0;
        });
        $expiring->addAction(function($entity) {
            $days_left = $this->getDaysToExpiry($entity->expiry_date);
            $this->createAlert($entity->product_id, 'EXPIRING_SOON', 'Expiring in ' . $days_left . ' days: ' . $entity->name, 'warning');
            $this->promoteSalePrice($entity);
            $this->flagForRotation($entity);
            $this->logStageTransition($entity->product_id, self::STAGE_EXPIRING_SOON, 'Expiring in ' . $days_left . ' days - Promotion set');
        });
        $this->stages[self::STAGE_EXPIRING_SOON] = $expiring;
        
        // STAGE 5: EXPIRED - Medicine past expiration date
        $expired = new StageDefinition(self::STAGE_EXPIRED, 5);
        $expired->color_code = '#9C27B0';
        $expired->alert_type = 'critical';
        $expired->addCondition(function($entity) {
            $days_to_expiry = $this->getDaysToExpiry($entity->expiry_date);
            return $days_to_expiry < 0;
        });
        $expired->addAction(function($entity) {
            $this->createAlert($entity->product_id, 'EXPIRED', 'Product EXPIRED: ' . $entity->name . ' on ' . $entity->expiry_date, 'critical');
            $this->removeFromSale($entity);
            $this->scheduleDisposal($entity);
            $this->recordExpiredLoss($entity);
            $this->logStageTransition($entity->product_id, self::STAGE_EXPIRED, 'Marked for disposal - Removed from sale');
        });
        $this->stages[self::STAGE_EXPIRED] = $expired;
        
        // STAGE 6: OUT_OF_STOCK - No stock available
        $out_of_stock = new StageDefinition(self::STAGE_OUT_OF_STOCK, 6);
        $out_of_stock->color_code = '#37474F';
        $out_of_stock->alert_type = 'critical';
        $out_of_stock->addCondition(function($entity) {
            return $entity->stock_quantity == 0;
        });
        $out_of_stock->addAction(function($entity) {
            $this->createAlert($entity->product_id, 'OUT_OF_STOCK', 'Product OUT OF STOCK: ' . $entity->name, 'critical');
            $this->holdOrders($entity);
            $this->triggerEmergencyReorder($entity);
            $this->notifyPharmacist($entity, 'OUT_OF_STOCK');
            $this->logStageTransition($entity->product_id, self::STAGE_OUT_OF_STOCK, 'Emergency reorder triggered');
        });
        $this->stages[self::STAGE_OUT_OF_STOCK] = $out_of_stock;
    }
    
    /**
     * Build the behavior tree structure
     */
    private function buildBehaviorTree() {
        $this->tree_root = new BehaviorTreeNode('root', 'Pharmacy Behavior Root', 'selector');
        
        // Check stages in priority order
        foreach ($this->stages as $stage_name => $stage) {
            $stage_node = new BehaviorTreeNode($stage_name, $stage_name, 'sequence');
            $stage_node->stage_definition = $stage;
            $this->tree_root->addChild($stage_node);
        }
    }
    
    /**
     * Execute behavior tree for a single medicine entity
     */
    public function evaluateEntity($medicine_data) {
        $entity = new MedicineEntity($medicine_data);
        
        // Evaluate each stage in priority order
        foreach ($this->stages as $stage_name => $stage) {
            $conditions_met = true;
            
            // Check all conditions for this stage
            foreach ($stage->conditions as $condition) {
                if (!call_user_func($condition, $entity)) {
                    $conditions_met = false;
                    break;
                }
            }
            
            // If all conditions met, execute actions and transition to this stage
            if ($conditions_met) {
                if ($entity->current_stage !== $stage_name) {
                    $entity->current_stage = $stage_name;
                    $entity->time_entered_stage = time();
                }
                
                // Execute all actions for this stage
                foreach ($stage->actions as $action) {
                    call_user_func($action, $entity);
                }
                
                return [
                    'product_id' => $entity->product_id,
                    'stage' => $stage_name,
                    'color' => $stage->color_code,
                    'alert_type' => $stage->alert_type,
                    'actions_triggered' => $entity->actions_triggered
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Process all medicines in inventory through the behavior tree
     */
    public function runFullCycle() {
        $query = "SELECT * FROM products WHERE is_active = 1";
        $result = $this->conn->query($query);
        
        $cycle_results = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $result_data = $this->evaluateEntity($row);
                if ($result_data) {
                    $cycle_results[] = $result_data;
                }
            }
        }
        
        return [
            'total_evaluated' => count($cycle_results),
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $cycle_results,
            'alerts' => $this->alerts,
            'transitions' => $this->stage_transition_log
        ];
    }
    
    /**
     * Get current stage of a medicine
     */
    public function getCurrentStage($product_id) {
        $query = "SELECT * FROM products WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $this->evaluateEntity($row);
        }
        
        return null;
    }
    
    /**
     * Helper: Calculate days until expiry
     */
    private function getDaysToExpiry($expiry_date) {
        $expiry = strtotime($expiry_date);
        $today = strtotime(date('Y-m-d'));
        return floor(($expiry - $today) / 86400);
    }
    
    /**
     * Helper: Create alert
     */
    private function createAlert($product_id, $alert_type, $message, $severity) {
        $alert = [
            'product_id' => $product_id,
            'type' => $alert_type,
            'message' => $message,
            'severity' => $severity,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->alerts[] = $alert;
        
        // Store in database
        $query = "INSERT INTO alerts (product_id, type, message, severity) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isss', $product_id, $alert_type, $message, $severity);
        $stmt->execute();
        
        return $alert;
    }
    
    /**
     * Helper: Log stage transition
     */
    private function logStageTransition($product_id, $new_stage, $reason) {
        $log_entry = [
            'product_id' => $product_id,
            'stage' => $new_stage,
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->stage_transition_log[] = $log_entry;
    }
    
    /**
     * Actions triggered by stages
     */
    private function triggerAutoReorder($entity) {
        // Auto-trigger reorder when low stock
        $query = "UPDATE products SET reorder_flag = 1 WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $entity->product_id);
        $stmt->execute();
    }
    
    private function triggerUrgentReorder($entity) {
        // Urgent reorder for critical stock
        $query = "UPDATE products SET reorder_flag = 1, urgent_reorder = 1 WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $entity->product_id);
        $stmt->execute();
    }
    
    private function triggerEmergencyReorder($entity) {
        // Emergency reorder for out of stock
        $query = "UPDATE products SET reorder_flag = 1, urgent_reorder = 1, emergency_reorder = 1 WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $entity->product_id);
        $stmt->execute();
    }
    
    private function notifyPharmacist($entity, $alert_type) {
        // Send notification to pharmacist (can integrate with email/SMS)
        // Store notification in database
        $query = "INSERT INTO pharmacist_notifications (product_id, alert_type, message) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $message = "$alert_type Alert: " . $entity->name;
        $stmt->bind_param('iss', $entity->product_id, $alert_type, $message);
        $stmt->execute();
    }
    
    private function promoteSalePrice($entity) {
        // Reduce price to encourage sales of expiring stock
        $discount = $entity->price * 0.15; // 15% discount
        $new_price = $entity->price - $discount;
        
        $query = "UPDATE products SET sale_price = ?, on_promotion = 1 WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('di', $new_price, $entity->product_id);
        $stmt->execute();
    }
    
    private function flagForRotation($entity) {
        // Flag for FIFO rotation
        $query = "UPDATE products SET rotation_flag = 1 WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $entity->product_id);
        $stmt->execute();
    }
    
    private function removeFromSale($entity) {
        // Remove expired medicine from sale
        $query = "UPDATE products SET is_active = 0, removal_date = NOW() WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $entity->product_id);
        $stmt->execute();
    }
    
    private function scheduleDisposal($entity) {
        // Schedule for disposal
        $query = "INSERT INTO disposal_schedule (product_id, reason, scheduled_date) VALUES (?, 'EXPIRED', NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $entity->product_id);
        $stmt->execute();
    }
    
    private function recordExpiredLoss($entity) {
        // Record financial loss
        $loss_amount = $entity->price * $entity->stock_quantity;
        $query = "INSERT INTO financial_losses (product_id, loss_amount, loss_type, date) VALUES (?, ?, 'EXPIRY', NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('id', $entity->product_id, $loss_amount);
        $stmt->execute();
    }
    
    private function holdOrders($entity) {
        // Hold any pending orders for out-of-stock items
        $query = "UPDATE orders SET status = 'HELD' WHERE product_id = ? AND status = 'PENDING'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $entity->product_id);
        $stmt->execute();
    }
    
    /**
     * Get stage statistics
     */
    public function getStageStatistics() {
        $stats = [];
        
        foreach ($this->stages as $stage_name => $stage) {
            $query = "SELECT COUNT(*) as count FROM products WHERE current_stage = ? AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('s', $stage_name);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $stats[$stage_name] = [
                'count' => $row['count'],
                'priority' => $stage->priority,
                'color' => $stage->color_code
            ];
        }
        
        return $stats;
    }
}
