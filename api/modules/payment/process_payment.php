<?php
header('Content-Type: application/json');

require_once BOOTSTRAP_PATH . 'bootstrap.php';

$amount = $_POST['amount'] ?? null;
$method = $_POST['method'] ?? null;

if (!$amount || !$method) {
    echo json_encode(['success' => false, 'message' => 'Missing payment details']);
    exit;
}

// Simulate payment gateway URLs
if ($method === 'paymaya') {
    $checkoutUrl = "https://sandbox.paymaya.com/checkout/simulated-session?amount={$amount}";
} elseif ($method === 'gcash') {
    $checkoutUrl = "https://sandbox.gcash.com/payment/simulated-session?amount={$amount}";
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}

try {
    // Generate a unique transaction ID
    $txnId = uniqid('txn_');

    $stmt = $pdo->prepare("
        INSERT INTO transactions 
            (txn_id, amount, method, status, checkout_url) 
         VALUES (:txn_id, :amount, :method, :status, :checkout_url)
     ");
    $stmt->execute([
        ':txn_id' => $txnId,
        ':amount' => $amount,
        ':method' => strtoupper($method),
        ':status' => 'PENDING',
        ':checkout_url' => $checkoutUrl
    ]);

    echo json_encode(['success' => true, 'checkout_url' => $checkoutUrl]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
