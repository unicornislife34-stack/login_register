<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
include 'helpers.php';

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($orderId <= 0) {
    echo "<p>Invalid order ID.</p>";
    exit;
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "<p>Order not found.</p>";
    exit;
}

$stmt = $conn->prepare("SELECT oi.*, i.item_name as current_name FROM order_items oi LEFT JOIN inventory i ON oi.inventory_id = i.id WHERE oi.order_id = ?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$orderItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$paymentDetails = [];
if (!empty($order['payment_details'])) {
    $paymentDetails = json_decode($order['payment_details'], true) ?: [];
}

// Calculate totals
$subtotal = 0;
foreach ($orderItems as $item) {
    $subtotal += floatval($item['subtotal']);
}
$tax = $subtotal * 0.12;
$total = $subtotal + $tax;

// Use stored total if present
if (isset($order['total_amount'])) {
    $total = floatval($order['total_amount']);
}

$ticketNumber = htmlspecialchars($order['ticket_number'] ?? '');
$user = htmlspecialchars($order['user'] ?? '');
$createdAt = date('M d, Y H:i', strtotime($order['created_at'] ?? ''));

function formatMoney($amount) {
    return number_format(floatval($amount), 2);
}

date_default_timezone_set('Asia/Manila');

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Receipt - <?= $ticketNumber ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        * { box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            padding: 24px 20px; 
            color: #1a1a1a; 
            line-height: 1.4;
            max-width: 400px;
            margin: 0 auto;
            background: white;
        }
        @media print {
            body { padding: 15px 12px; -webkit-print-color-adjust: exact; }
            @page { margin: 0.5in; size: 80mm; }
        }
        h1 { 
            margin: 0 0 12px 0; 
            font-size: 28px; 
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: -0.02em;
        }
        .header-info p { 
            margin: 4px 0; 
            font-size: 13px; 
            color: #555;
        }
        .receipt-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0 16px 0; 
            font-size: 13px;
        }
        .receipt-table th { 
            padding: 10px 4px 8px 0; 
            text-align: left; 
            font-weight: 600; 
            color: #333;
            border-bottom: 2px solid #eee;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .receipt-table th:last-child,
        .receipt-table td:last-child { text-align: right; }
        .receipt-table td { 
            padding: 8px 4px 8px 0; 
            border-bottom: 1px solid #f0f0f0; 
            vertical-align: top;
        }
        .receipt-table tbody tr:nth-child(even) { background: #fafafa; }
        .receipt-table .item-name { 
            font-weight: 500; 
            color: #1a1a1a;
            margin: 0;
        }
        .receipt-table .modifiers { 
            font-size: 11px; 
            color: #666; 
            margin-top: 2px; 
            font-style: italic;
            line-height: 1.3;
        }
        .receipt-table .price-col,
        .receipt-table .qty-col,
        .receipt-table .total-col { 
            font-family: 'SF Mono', Consolas, monospace; 
            font-weight: 500;
            min-width: 45px;
        }
        .receipt-table tfoot td { 
            padding: 10px 4px; 
            font-weight: 600;
            border-top: 2px solid #333;
        }
        .receipt-table .grand-total { 
            font-size: 16px !important; 
            color: #1a1a1a !important;
            font-weight: 700 !important;
        }
        .payment-section {
            margin: 16px 0;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 12px;
        }
        .payment-section table td {
            padding: 4px 0;
        }
        .footer { 
            margin-top: 24px; 
            font-size: 12px; 
            color: #888; 
            text-align: center;
            padding-top: 16px;
            border-top: 1px solid #eee;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>L LE JOSE</h1>
    <div class="header-info">
        <p><strong>Ticket #:</strong> <?= $ticketNumber ?></p>
        <p><strong>Order #:</strong> <?= $orderId ?></p>
        <p><strong>User:</strong> <?= $user ?></p>
        <p><strong>Date:</strong> <?= $createdAt ?></p>
    </div>

    <?php if ($paymentDetails): ?>
    <div class="payment-section">
            <h4>Payment details</h4>
            <table>
                <tbody>
                    <?php foreach ($paymentDetails as $pd): ?>
                        <?php
                            $methodLabel = !empty($pd['method']) ? ucfirst($pd['method']) : 'Payment';
                            $amt = floatval($pd['amount'] ?? 0);
                            $tendered = isset($pd['tendered']) ? floatval($pd['tendered']) : null;
                            $change = isset($pd['change']) ? floatval($pd['change']) : null;
                        ?>
                        <tr>
                            <td style="font-size:12px;color:#555;"><?= htmlspecialchars($methodLabel) ?></td>
                            <td style="text-align:right; font-size:12px;color:#555;">₱<?= formatMoney($amt) ?></td>
                        </tr>
                        <?php if ($tendered !== null): ?>
                            <tr>
                                <td style="font-size:12px;color:#555;">Tendered</td>
                                <td style="text-align:right; font-size:12px;color:#555;">₱<?= formatMoney($tendered) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($change !== null): ?>
                            <tr>
                                <td style="font-size:12px;color:#555;">Change</td>
                                <td style="text-align:right; font-size:12px;color:#555;">₱<?= formatMoney($change) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <table class="receipt-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="qty-col">Qty</th>
                <th class="price-col">Price</th>
                <th class="total-col">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orderItems as $item): ?>
                <tr>
                    <td>
                        <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                        <?php if ($modifiersText = formatModifiers($item['modifiers'] ?? '')): ?>
                            <div class="modifiers"><?= $modifiersText ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="qty-col"><?= intval($item['qty']) ?></td>
                    <td class="price-col">₱<?= formatMoney($item['price']) ?></td>
                    <td class="total-col">₱<?= formatMoney($item['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Subtotal</td>
                <td class="total-col">₱<?= formatMoney($subtotal) ?></td>
            </tr>
            <tr>
                <td colspan="3">Tax (12%)</td>
                <td class="total-col">₱<?= formatMoney($tax) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="grand-total">Grand Total</td>
                <td class="grand-total total-col">₱<?= formatMoney($total) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">Thank you for your purchase!</div>

    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</body>
</html>
