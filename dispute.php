<?php
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

// Get deal id from URL
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

// Check user is buyer or seller
if ($user['id'] != $deal['seller_id'] && $user['id'] != $deal['buyer_id']) {
    header("Location: dashboard.php");
    exit;
}

// Only allow dispute on active deals
if (!in_array($deal['status'], ['FUNDS_HELD', 'DELIVERING'])) {
    header("Location: deal.php?id=" . $id);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason      = trim($_POST['reason'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $allowed_reasons = [
        'Item not received',
        'Item not as described',
        'Wrong item sent',
        'Damaged item',
        'Payment issue',
        'Other'
    ];

    if (empty($reason) || empty($description)) {
        $error = "Both reason and description are required.";
    } elseif (!in_array($reason, $allowed_reasons)) {
        $error = "Please select a valid reason.";
    } elseif (strlen($description) < 10) {
        $error = "Please describe the issue (at least 10 characters).";
    } else {
        // 1. Insert the dispute
        $stmt = $conn->prepare("
            INSERT INTO disputes (deal_id, opened_by, reason, description, status)
            VALUES (?, ?, ?, ?, 'open')
        ");
        $stmt->bind_param("iiss", $id, $user['id'], $reason, $description);
        $stmt->execute();
        $stmt->close();

        // 2. Update deal status
        $stmt = $conn->prepare("UPDATE deals SET status = 'DISPUTED' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

               // 3. Notify the other party
        $other_user = ($user['id'] == $deal['seller_id']) ? $deal['buyer_id'] : $deal['seller_id'];
        $msg = "A dispute has been opened on deal #" . $deal['deal_code'] . ". Reason: " . $reason;
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $other_user, $id, $msg);
        $stmt->execute();
        $stmt->close();

        // 4. Notify the Admin(s)
        $admin_query = $conn->query("SELECT id FROM users WHERE role = 'admin'");
        $admin_msg = "URGENT: A new dispute has been filed on deal #" . $deal['deal_code'];
        while ($admin = $admin_query->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $admin['id'], $id, $admin_msg);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: deal.php?id=" . $id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Open Dispute — SafeTrade</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div class="topbar">
        <h1>🔒 SafeTrade</h1>
        <div class="topbar-user">
            <a href="notifications.php">
                Notifications
                <?php $unread = unreadNotifications($conn, $user['id']); ?>
                <?php if ($unread > 0): ?>
                    <span style="background: #dc2626; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"><?= $unread ?></span>
                <?php endif; ?>
            </a>
            <?php if ($user['role'] === 'admin'): ?>
                 <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span>Hello, <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container-narrow">

        <p class="mb-16"><a href="deal.php?id=<?= $id ?>">← Back to Deal</a></p>

        <div class="card">
            <h2 class="mb-16">Open a Dispute</h2>

            <p style="color: #6b7280; font-size: 14px; margin-bottom: 16px;">
                Deal <strong>#<?= htmlspecialchars($deal['deal_code']) ?></strong> —
                MK <?= number_format($deal['amount'], 2) ?>
            </p>

            <div class="alert alert-warning">
                Both parties will be notified. An admin will review the evidence and decide.
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Reason *</label>
                <select name="reason" required>
                    <option value="">Select a reason</option>
                    <option value="Item not received">Item not received</option>
                    <option value="Item not as described">Item not as described</option>
                    <option value="Wrong item sent">Wrong item sent</option>
                    <option value="Damaged item">Damaged item</option>
                    <option value="Payment issue">Payment issue</option>
                    <option value="Other">Other</option>
                </select>

                <label>Description *</label>
                <textarea name="description" placeholder="Explain what happened in detail..." required></textarea>

                <button type="submit" class="btn btn-full">Submit Dispute</button>
            </form>

            <p class="text-center mt-16">
                <a href="deal.php?id=<?= $id ?>">Cancel</a>
            </p>
        </div>
    </div>

</body>
</html>