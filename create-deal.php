<?php
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

// Only sellers can create deals
if ($user['role'] !== 'seller') {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';
$deal_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $amount          = floatval($_POST['amount'] ?? 0);
    $buyer_phone     = trim($_POST['buyer_phone'] ?? '');
    $delivery_method = trim($_POST['delivery_method'] ?? '');
    $deadline        = trim($_POST['deadline'] ?? '');

    // Validate
    if (empty($title) || empty($amount) || empty($buyer_phone) || empty($delivery_method) || empty($deadline)) {
        $error = "All required fields must be filled.";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } elseif (!preg_match('/^[0-9]{10}$/', $buyer_phone)) {
        $error = "Buyer phone must be 10 digits.";
    } elseif ($buyer_phone === $user['id']) {
        $error = "You cannot create a deal with yourself.";
    } else {
        // Check if buyer exists
        $stmt = $conn->prepare("SELECT id, phone FROM users WHERE phone = ?");
        $stmt->bind_param("s", $buyer_phone);
        $stmt->execute();
        $buyer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$buyer) {
            $error = "Buyer not registered. Ask them to sign up first.";
        } elseif ($buyer['id'] == $user['id']) {
            $error = "You cannot create a deal with yourself.";
        } else {
            // Generate a unique deal code
            do {
                $deal_code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                $check = $conn->prepare("SELECT id FROM deals WHERE deal_code = ?");
                $check->bind_param("s", $deal_code);
                $check->execute();
                $check->store_result();
                $exists = $check->num_rows > 0;
                $check->close();
            } while ($exists);

            // Insert deal
            $stmt = $conn->prepare("
                INSERT INTO deals (deal_code, seller_id, buyer_id, title, description, amount, delivery_method, deadline, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'AWAITING_PAYMENT')
            ");
            $stmt->bind_param(
                "siissdss",
                $deal_code,
                $user['id'],
                $buyer['id'],
                $title,
                $description,
                $amount,
                $delivery_method,
                $deadline
            );

            if ($stmt->execute()) {
                $deal_id = $stmt->insert_id;

                // Notify the buyer
                $msg = "You have been invited to deal #" . $deal_code . " for MK " . number_format($amount, 2);
                $notif = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
                $notif->bind_param("iis", $buyer['id'], $deal_id, $msg);
                $notif->execute();
                $notif->close();

                $success = "Deal created successfully!";
            } else {
                $error = "Could not create deal. Try again.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Deal — SafeTrade</title>
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
        <div class="card">
            <h2 class="mb-16">Create a New Deal</h2>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?><br>
                    <strong>Deal Code: <?= htmlspecialchars($deal_code) ?></strong>
                </div>
            <?php endif; ?>

            <form method="POST">
                <label>Item Title *</label>
                <input type="text" name="title" required>

                <label>Description</label>
                <textarea name="description"></textarea>

                <label>Amount (MWK) *</label>
                <input type="number" name="amount" step="0.01" min="1" required>

                <label>Buyer Phone Number *</label>
                <input type="text" name="buyer_phone" placeholder="0991111111" required>

                <label>Delivery Method *</label>
                <select name="delivery_method" required>
                    <option value="">Select method</option>
                    <option value="Meet-up">Meet-up</option>
                    <option value="Courier">Courier</option>
                    <option value="Bus">Bus</option>
                    <option value="Pickup">Pickup</option>
                </select>

                <label>Deadline *</label>
                <input type="date" name="deadline" required>

                <button type="submit" class="btn btn-full">Create Deal</button>
            </form>

            <p class="text-center mt-16">
                <a href="dashboard.php">← Back to Dashboard</a>
            </p>
        </div>
    </div>
</body>
</html>