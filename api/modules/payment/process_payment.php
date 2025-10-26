<?php
require_once '../../../bootstrap/bootstrap.php';
require APP_PATH . '/app/services/paymongo_service.php';

header('Content-Type: application/json');

//echo config('config.payment.paymongo.public_key');
$amount = $_POST['amount'] ?? null;
$method = $_POST['method'] ?? null;

if (!$amount || !$method) {
    echo json_encode(['success' => false, 'message' => 'Missing payment details']);
    exit;
}

$paymongoService = new PayMongoService();

try {
    # Generate a unique transaction ID
    $txnId = uniqid("txn_{$method}_");

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
        ':checkout_url' => 'temp'
    ]);

    echo json_encode([
        'success' => true
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
