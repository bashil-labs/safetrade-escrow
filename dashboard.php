<?php
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

// If the user is an admin, send them straight to the admin panel
if ($user['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

// Get wallet balance and trust score
$stmt = $conn->prepare("SELECT wallet_balance, trust_score FROM users WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get user's deals
$stmt = $conn->prepare("
    SELECT id, deal_code, title, amount, status, seller_id, buyer_id, created_at
    FROM deals
    WHERE seller_id = ? OR buyer_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("ii", $user['id'], $user['id']);
$stmt->execute();
$deals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard — Escrow</title>
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
                    <span class="notif-badge"><?= $unread ?></span>
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

        <div class="welcome">
            <h2>Welcome back, <?= htmlspecialchars($user['full_name']) ?> 👋</h2>
            <p>Here's what's happening with your escrow activity.</p>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="label">Wallet Balance</div>
                <div class="value">MK <?= number_format($userData['wallet_balance'], 2) ?></div>
                <a href="topup.php" class="btn btn-sm mt-16">+ Top Up</a>
            </div>
            <div class="stat">
                <div class="label">Trust Score</div>
                <div class="value"><?= $userData['trust_score'] ?> / 100</div>
            </div>
            <div class="stat">
                <div class="label">Your Role</div>
                <div class="value"><?= ucfirst($user['role']) ?></div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h3>Your Deals</h3>
                <?php if ($user['role'] === 'seller'): ?>
                    <a href="create-deal.php" class="btn btn-sm">+ Create a Deal</a>
                <?php endif; ?>
            </div>

            <?php if (count($deals) === 0): ?>
                <p class="empty">You don't have any deals yet. Deals will appear here once you create or join one.</p>
            <?php else: ?>
                <table class="deal-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Amount</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deals as $deal): ?>
                            <?php
                                $roleInDeal = ($deal['seller_id'] == $user['id']) ? 'Selling' : 'Buying';

                                $badgeClass = 'badge-cancelled';
                                if ($deal['status'] === 'AWAITING_PAYMENT') $badgeClass = 'badge-pending';
                                elseif ($deal['status'] === 'FUNDS_HELD')   $badgeClass = 'badge-held';
                                elseif ($deal['status'] === 'DELIVERING')   $badgeClass = 'badge-delivering';
                                elseif ($deal['status'] === 'COMPLETED')    $badgeClass = 'badge-completed';
                                elseif ($deal['status'] === 'DISPUTED')     $badgeClass = 'badge-disputed';
                                elseif ($deal['status'] === 'RESOLVED')     $badgeClass = 'badge-resolved';
                            ?>
                            <tr>
                                <td class="code">#<?= htmlspecialchars($deal['deal_code']) ?></td>
                                <td><?= htmlspecialchars($deal['title']) ?></td>
                                <td>MK <?= number_format($deal['amount'], 2) ?></td>
                                <td><?= $roleInDeal ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($deal['status']) ?></span></td>
                                <td><a href="deal.php?id=<?= $deal['id'] ?>" class="view-link">View →</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>