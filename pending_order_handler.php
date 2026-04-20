<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'employee') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'config.php';
$username = $_SESSION['username'];
$session_id = session_id();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

function cleanupExpiredOrders($conn) {
    $conn->query("DELETE FROM pending_orders WHERE expires_at < NOW()");
}

switch ($action) {
    case 'get_pending':
        cleanupExpiredOrders($conn);
        $stmt = $conn->prepare("SELECT order_data, subtotal, tax, total_amount FROM pending_orders WHERE username = ? AND session_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->bind_param('ss', $username, $session_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();
        
        if ($order) {
            echo json_encode([
                'success' => true,
                'order' => json_decode($order['order_data'], true),
                'subtotal' => $order['subtotal'],
                'tax' => $order['tax'],
                'total' => $order['total_amount']
            ]);
        } else {
            echo json_encode(['success' => true, 'order' => []]);
        }
        break;
        
    case 'save_pending':
        $orderData = json_decode(file_get_contents('php://input'), true);
        if (!is_array($orderData)) {
            echo json_encode(['success' => false, 'error' => 'Invalid order data']);
            break;
        }
        
        cleanupExpiredOrders($conn);
        
        // Calculate totals
        $subtotal = 0;
        $items = [];
        foreach ($orderData as $item) {
            $qty = intval($item['qty'] ?? 0);
            if ($qty > 0) {
                $items[] = $item;
                $subtotal += floatval($item['price']) * $qty;
            }
        }
        $tax = round($subtotal * 0.12, 2);
        $total = round($subtotal + $tax, 2);
        
        $orderJson = json_encode($items);
        
        $conn->begin_transaction();
        try {
            // Delete existing
            $stmt = $conn->prepare("DELETE FROM pending_orders WHERE username = ? AND session_id = ?");
            $stmt->bind_param('ss', $username, $session_id);
            $stmt->execute();
            $stmt->close();
            
            // Insert new
            $stmt = $conn->prepare("INSERT INTO pending_orders (username, order_data, subtotal, tax, total_amount, session_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sddds', $username, $orderJson, $subtotal, $tax, $total, $session_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true, 'order_id' => $conn->insert_id, 'total' => $total]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'delete_pending':
        cleanupExpiredOrders($conn);
        $stmt = $conn->prepare("DELETE FROM pending_orders WHERE username = ? AND session_id = ?");
        $stmt->bind_param('ss', $username, $session_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;
        
    case 'clear_expired':
        cleanupExpiredOrders($conn);
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>

