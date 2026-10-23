<?php
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: dashboard.php");
    exit;
}

$id = (int) $id;

// Load the deal
$stmt = $conn->prepare("SELECT * FROM deals WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$deal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$deal) {
    header("Location: dashboard.php");
    exit;
}

// Check user is the seller
if ($user['id'] != $deal['seller_id']) {
    header("Location: deal.php?id=" . $id);
    exit;
}

// Check deal status is correct
if ($deal['status'] !== 'FUNDS_HELD') {
    header("Location: deal.php?id=" . $id);
    exit;
}

// Update deal status
$stmt = $conn->prepare("UPDATE deals SET status = 'DELIVERING' WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Notify the buyer
$msg = "Seller has marked deal #" . $deal['deal_code'] . " as delivered. Confirm receipt when you receive the item.";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $deal['buyer_id'], $id, $msg);
$stmt->execute();
$stmt->close();

header("Location: deal.php?id=" . $id);
exit;
?>