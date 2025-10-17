<?php
// final/api/bookings_create.php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$user = require_auth();
$pdo = get_db();

// Inputs
$room_id = (int)($_POST['room_id'] ?? 0);
$start_date = trim($_POST['start_date'] ?? '');
$end_date   = trim($_POST['end_date'] ?? '');
$guests     = (int)($_POST['guests'] ?? 1);
$extras     = $_POST['extras'] ?? [];          // may be array or JSON (UI sends array)
$notes      = trim($_POST['notes'] ?? '');

if ($room_id <= 0 || $start_date === '' || $end_date === '') {
  echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit;
}

if (!is_array($extras)) {
  // If UI sent JSON string
  $decoded = json_decode((string)$extras, true);
  if (is_array($decoded)) $extras = $decoded; else $extras = [];
}

try {
  $stmt = $pdo->prepare("
    INSERT INTO bookings (room_id, start_date, end_date, guests, extras_json, notes, status, created_at)
    VALUES (:room_id, :start_date, :end_date, :guests, :extras_json, :notes, 'pending', NOW())
  ");
  $stmt->execute([
    ':room_id'     => $room_id,
    ':start_date'  => $start_date,
    ':end_date'    => $end_date,
    ':guests'      => $guests,
    ':extras_json' => json_encode($extras),
    ':notes'       => $notes !== '' ? $notes : null,
  ]);

  echo json_encode(['success' => true, 'booking_id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => 'DB error: '.$e->getMessage()]);
}
