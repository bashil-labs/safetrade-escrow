<?php
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

// Get the deal id from URL
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

// Check user is the buyer
if ($user['id'] != $deal['buyer_id']) {
    header("Location: deal.php?id=" . $id);
    exit;
}

// Check deal status is correct
if ($deal['status'] !== 'AWAITING_PAYMENT') {
    header("Location: deal.php?id=" . $id);
    exit;
}

// Get buyer's current wallet balance
$stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$buyer = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check balance
if ($buyer['wallet_balance'] < $deal['amount']) {
    // Not enough money
    header("Location: deal.php?id=" . $id . "&error=insufficient");
    exit;
}

// ----- All checks passed. Do the payment. -----

$amount = $deal['amount'];
$buyer_id = $deal['buyer_id'];
$seller_id = $deal['seller_id'];

// 1. Deduct from buyer's wallet
$stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
$stmt->bind_param("di", $amount, $buyer_id);
$stmt->execute();
$stmt->close();

// 2. Update deal status
$stmt = $conn->prepare("UPDATE deals SET status = 'FUNDS_HELD' WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// 3. Insert transaction record
$stmt = $conn->prepare("
    INSERT INTO transactions (deal_id, from_user, to_user, amount, type)
    VALUES (?, ?, NULL, ?, 'deposit')
");
$stmt->bind_param("iid", $id, $buyer_id, $amount);
$stmt->execute();
$stmt->close();

// 4. Notify the seller
$msg = "Money secured for deal #" . $deal['deal_code'] . ". Please deliver the item.";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $seller_id, $id, $msg);
$stmt->execute();
$stmt->close();

// 5. Redirect back to deal page
header("Location: deal.php?id=" . $id);
exit;
?>