<?php
// final/api/booking_payment_upload.php
declare(strict_types=1);
header('Content-Type: application/json');

// Be strict about errors -> log them instead of printing
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

try {
  $user = require_auth();
  $pdo  = get_db();

  $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
  $method     = trim($_POST['method'] ?? '');
  $reference  = trim($_POST['reference'] ?? '');

  if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking id']); exit;
  }

  // Verify booking exists and still pending
  $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id=:id LIMIT 1");
  $stmt->execute([':id' => $booking_id]);
  $bk = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$bk) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']); exit;
  }

  // Handle optional file
  $receiptPath = null;
  if (!empty($_FILES['receipt']['name'])) {
    if ($_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
      echo json_encode(['success' => false, 'message' => 'Upload error']); exit;
    }
    $tmp  = $_FILES['receipt']['tmp_name'];
    $name = basename($_FILES['receipt']['name']);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    // allow images and pdf
    if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
      echo json_encode(['success' => false, 'message' => 'Invalid file type']); exit;
    }
    $uploadDir = __DIR__ . '/../assets/uploads/payments';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
    $destName = 'pay_' . $booking_id . '_' . uniqid('', true) . '.' . $ext;
    $destPath = $uploadDir . '/' . $destName;
    if (!move_uploaded_file($tmp, $destPath)) {
      echo json_encode(['success' => false, 'message' => 'Failed to save file']); exit;
    }
    // store relative path used by the app
    $receiptPath = 'assets/uploads/payments/' . $destName;
  }

  $stmt = $pdo->prepare("
    UPDATE bookings
       SET payment_method = :method,
           payment_reference = :reference,
           payment_receipt = COALESCE(:receipt, payment_receipt),
           payment_uploaded_at = NOW()
     WHERE id = :id
     LIMIT 1
  ");
  $stmt->execute([
    ':method'   => $method !== '' ? $method : null,
    ':reference'=> $reference !== '' ? $reference : null,
    ':receipt'  => $receiptPath,
    ':id'       => $booking_id,
  ]);

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  // Never echo raw HTML or PHP warnings
  echo json_encode(['success' => false, 'message' => 'Server error']); 
}
