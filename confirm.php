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

$id = (int) $id;// Load the deal
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
if ($deal['status'] !== 'DELIVERING') {
    header("Location: deal.php?id=" . $id);
    exit;
}

$amount = $deal['amount'];
$buyer_id = $deal['buyer_id'];
$seller_id = $deal['seller_id'];

$stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
$stmt->bind_param("di", $amount, $seller_id);
$stmt->execute();
$stmt->close();

// 2. Update deal status
$stmt = $conn->prepare("UPDATE deals SET status = 'COMPLETED' WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// 3. Insert transaction record
$stmt = $conn->prepare("
    INSERT INTO transactions (deal_id, from_user, to_user, amount, type)
    VALUES (?, NULL, ?, ?, 'release')
");
$stmt->bind_param("iid", $id, $seller_id, $amount);
$stmt->execute();
$stmt->close();

// 4. Notify the seller
$msg = "Money released for deal #" . $deal['deal_code'] . ". Please CHECK your wallet.";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $seller_id, $id, $msg);
$stmt->execute();
$stmt->close();

// 5. Redirect back to deal page
header("Location: deal.php?id=" . $id);
exit;
?>

