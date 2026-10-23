<?php
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

// Handle "mark as read" for a single notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_one'])) {
    $nid = (int) ($_POST['notification_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $nid, $user['id']);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit;
}

// Handle "mark all as read"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit;
}

// Load all notifications
$stmt = $conn->prepare("
    SELECT id, deal_id, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count unread
$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notifications — SafeTrade</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div class="topbar">
        <h1>🔒 SafeTrade</h1>
        <div class="topbar-user">
            <a href="notifications.php">
                Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <?php if ($user['role'] === 'admin'): ?>
                 <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span>Hello, <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">

        <p class="mb-16"><a href="dashboard.php">← Back to Dashboard</a></p>

        <div class="card">

            <div class="section-header">
                <h2 class="mb-0">Notifications</h2>

                <?php if ($unreadCount > 0): ?>
                    <form method="POST">
                        <button type="submit" name="mark_all" value="1" class="btn btn-sm btn-secondary">
                            Mark all as read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (count($notifications) === 0): ?>
                <p class="empty">You have no notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div>
                            <span class="dot"></span>
                            <?= htmlspecialchars($n['message']) ?>
                            <div class="time"><?= htmlspecialchars($n['created_at']) ?></div>
                        </div>
                        <div class="actions">
                            <?php if ($n['deal_id']): ?>
                                <a href="deal.php?id=<?= $n['deal_id'] ?>">View</a>
                            <?php endif; ?>

                            <?php if (!$n['is_read']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                    <button type="submit" name="mark_one" value="1" class="btn-link">
                                        Mark as read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>