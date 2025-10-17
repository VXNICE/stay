<?php
// final/api/bookings_list_pending.php
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

  // Only show bookings that are PENDING AND have a receipt uploaded
  $sql = "
    SELECT 
      b.id, b.room_id, b.start_date, b.end_date, b.guests, b.status, b.created_at,
      b.payment_method, b.payment_reference, b.payment_receipt, b.payment_uploaded_at,
      r.title AS room_title, r.location AS room_location, r.image AS room_image
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    WHERE b.status = 'pending'
      AND b.payment_receipt IS NOT NULL
    ORDER BY b.created_at ASC
  ";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['success' => true, 'bookings' => $rows]);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
