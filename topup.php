<?php
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);

    if ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } elseif ($amount > 1000000) {
        $error = "Maximum top-up is MK 1,000,000 per transaction.";
    } else {
        // Update wallet
        $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user['id']);

        if ($stmt->execute()) {
            $success = "MK " . number_format($amount, 2) . " added to your wallet.";
        } else {
            $error = "Top-up failed. Please try again.";
        }
        $stmt->close();
    }
}

// Reload the fresh balance
$stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Top Up Wallet — SafeTrade</title>
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

        <p class="mb-16"><a href="dashboard.php">← Back to Dashboard</a></p>

        <div class="card">
            <h2 class="mb-16">Top Up Wallet</h2>

            <p style="color: #6b7280; font-size: 14px;">
                Current balance:
                <strong>MK <?= number_format($userData['wallet_balance'], 2) ?></strong>
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error mt-16"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success mt-16"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Amount to Add (MWK)</label>
                <input type="number" name="amount" step="0.01" min="1" placeholder="e.g., 50000" required>

                <button type="submit" class="btn btn-full">Add Money</button>
            </form>

            <p class="text-center mt-16" style="color: #6b7280; font-size: 12px;">
                This is a simulated top-up for demo purposes.
            </p>
        </div>

    </div>

</body>
</html>