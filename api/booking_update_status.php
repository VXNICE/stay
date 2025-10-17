<?php
// final/api/booking_update_status.php
declare(strict_types=1);
header('Content-Type: application/json');
ini_set('display_errors','0'); ini_set('log_errors','1');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

try {
  $user = require_auth();
  $role = strtolower($user['role_name'] ?? '');
  if (!in_array($role, ['admin','owner','manager'], true)) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']); exit;
  }

  $pdo = get_db();
  $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
  $action = strtolower(trim($_POST['action'] ?? ''));
  $reason = trim($_POST['reason'] ?? '');

  if ($booking_id <= 0 || !in_array($action, ['approve','reject'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
  }

  if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE bookings SET status='approved', approved_by=:uid, approved_at=NOW(), rejection_reason=NULL WHERE id=:id AND status='pending'");
    $stmt->execute([':uid' => $user['id'] ?? null, ':id' => $booking_id]);
  } else {
    $stmt = $pdo->prepare("UPDATE bookings SET status='rejected', approved_by=:uid, approved_at=NOW(), rejection_reason=:reason WHERE id=:id AND status='pending'");
    $stmt->execute([':uid' => $user['id'] ?? null, ':reason' => ($reason !== '' ? $reason : null), ':id' => $booking_id]);
  }

  if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or already processed']); exit;
  }

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
