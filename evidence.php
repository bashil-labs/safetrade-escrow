<?php
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$dispute_id = (int) ($_POST['dispute_id'] ?? 0);
$deal_id    = (int) ($_POST['deal_id'] ?? 0);
$description = trim($_POST['description'] ?? '');

if (!$dispute_id || !$deal_id || empty($description)) {
    header("Location: deal.php?id=" . $deal_id);
    exit;
}

// Verify the user is part of this deal
$stmt = $conn->prepare("SELECT id, seller_id, buyer_id, status FROM deals WHERE id = ?");
$stmt->bind_param("i", $deal_id);
$stmt->execute();
$deal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$deal || ($user['id'] != $deal['seller_id'] && $user['id'] != $deal['buyer_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Verify the dispute belongs to this deal and is still open
$stmt = $conn->prepare("SELECT id, status FROM disputes WHERE id = ? AND deal_id = ?");
$stmt->bind_param("ii", $dispute_id, $deal_id);
$stmt->execute();
$dispute = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dispute || $dispute['status'] !== 'open') {
    header("Location: deal.php?id=" . $deal_id);
    exit;
}

// Handle file upload
if (!isset($_FILES['evidence_file']) || $_FILES['evidence_file']['error'] !== UPLOAD_ERR_OK) {
    header("Location: deal.php?id=" . $deal_id);
    exit;
}

$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
$ext = strtolower(pathinfo($_FILES['evidence_file']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_ext)) {
    header("Location: deal.php?id=" . $deal_id);
    exit;
}

// Max 5MB
if ($_FILES['evidence_file']['size'] > 5 * 1024 * 1024) {
    header("Location: deal.php?id=" . $deal_id);
    exit;
}

// Save file
$upload_dir = __DIR__ . '/uploads/evidence/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$filename = 'dispute_' . $dispute_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$file_path_on_disk = $upload_dir . $filename;
$file_path_in_db = 'uploads/evidence/' . $filename;

if (!move_uploaded_file($_FILES['evidence_file']['tmp_name'], $file_path_on_disk)) {
    header("Location: deal.php?id=" . $deal_id);
    exit;
}

// Insert into evidence table
$stmt = $conn->prepare("
    INSERT INTO evidence (dispute_id, uploaded_by, file_path, description)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("iiss", $dispute_id, $user['id'], $file_path_in_db, $description);
$stmt->execute();
$stmt->close();

// Notify the other party
$other_user = ($user['id'] == $deal['seller_id']) ? $deal['buyer_id'] : $deal['seller_id'];
$msg = "New evidence uploaded for the dispute on deal #" . $deal_id;
$stmt = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $other_user, $deal_id, $msg);
$stmt->execute();
$stmt->close();

header("Location: deal.php?id=" . $deal_id);
exit;
?>