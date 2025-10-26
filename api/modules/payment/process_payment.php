<?php
require_once '../../../bootstrap/bootstrap.php';
require APP_PATH . '/app/services/paymongo_service.php';

header('Content-Type: application/json');
header('Accept: application/json');

//echo config('config.payment.paymongo.public_key');
$amount = $_POST['amount'] ?? null;
$method = $_POST['method'] ?? null;

if (!$amount || !$method) {
    echo json_encode(['success' => false, 'message' => 'Missing payment details']);
    exit;
}

$paymongoService = new PayMongoService();

try {
    $payIntentResponse = $paymongoService->createPaymentIntent($amount, $method);
    $payMethodResponse = $paymongoService->createPaymentMethod($method);
    $attachPaymentMethodResponse = $paymongoService->attachPaymentMethod(
        $payIntentResponse['data']['id'],
        $payMethodResponse['data']['id']
    );

    # Generate a unique transaction ID
    $txnId = uniqid("txn_{$method}_");

    $stmt = $pdo->prepare("
        INSERT INTO transactions
            (
                txn_id, 
                amount, 
                method, 
                status, 
                checkout_url, 
                payment_intent_id, 
                payment_method_id,
                provider
            )
        VALUES (
            :txn_id, 
            :amount, 
            :method, 
            :status, 
            :checkout_url,
            :payment_intent_id,
            :payment_method_id,
            :provider
        )
     ");

    $redirectUrl = $attachPaymentMethodResponse['data']['attributes']['next_action']['redirect']['url'] ?? null;
    $shouldRedirect = $attachPaymentMethodResponse['data']['attributes']['status'] === 'awaiting_next_action';

    $stmt->execute([
        ':txn_id' => $txnId,
        ':amount' => $amount,
        ':method' => strtoupper($method),
        ':status' => 'PENDING',
        ':checkout_url' => $redirectUrl,
        ':payment_intent_id' => $payIntentResponse['data']['id'],
        ':payment_method_id' => $payMethodResponse['data']['id'],
        ':provider' => 'PAYMONGO'
    ]);

    $redirectUrl = null;
    if ($attachPaymentMethodResponse['data']['attributes']['status'] === 'awaiting_next_action') {
        $redirectUrl = $attachPaymentMethodResponse['data']['attributes']['next_action']['redirect']['url'];
    }

    echo json_encode([
        'success' => true,
        'redirect_url' => $redirectUrl,
        'should_redirect' => $shouldRedirect,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
} catch (Exception $exception) {
    echo json_encode([
        'success' => false,
        'message' => 'Payment Error Occurred: ' . $exception->getMessage()
    ]);
}
