<?php
require_once 'config/db.php';
require_once 'config/session.php';

requireLogin();

$user = currentUser();

// Get the deal ID from the query string.
$deal_id = $_GET['id'] ?? null;

if (!$deal_id || !is_numeric($deal_id)) {
    header("Location: dashboard.php");
    exit;
}

$deal_id = intval($deal_id);

$stmt = $conn->prepare("
    SELECT d.*, s.full_name AS seller_name, s.phone As seller_phone, b.full_name AS buyer_name, b.phone AS buyer_phone
    FROM deals d
    JOIN users s ON d.seller_id = s.id
    JOIN users b ON d.buyer_id = b.id
    WHERE d.id = ?
");
$stmt->bind_param("i", $deal_id);
$stmt->execute();
$deal = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if the current user is either the buyer or the seller of the deal.
if ($user['id'] != $deal['seller_id'] && $user['id'] != $deal['buyer_id']) {
    header("Location: dashboard.php");
    exit;
}

// Load dispute if one exists for this deal.
$dispute = null;
$evidence = [];

if (in_array($deal['status'], ['DISPUTED', 'UNDER_REVIEW', 'RESOLVED'])) {
    $stmt = $conn->prepare("
        SELECT dp.*, u.full_name AS opened_by_name
        FROM disputes dp
        JOIN users u ON dp.opened_by = u.id
        WHERE dp.deal_id = ?
        ORDER BY dp.id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $deal_id);
    $stmt->execute();
    $dispute = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dispute) {
        $stmt = $conn->prepare("
            SELECT e.*, u.full_name AS uploader_name
            FROM evidence e
            JOIN users u ON e.uploaded_by = u.id
            WHERE e.dispute_id = ?
            ORDER BY e.id ASC
        ");
        $stmt->bind_param("i", $dispute['id']);
        $stmt->execute();
        $evidence = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Pick badge class for status
$badgeClass = 'badge-cancelled';
if ($deal['status'] === 'AWAITING_PAYMENT') $badgeClass = 'badge-pending';
elseif ($deal['status'] === 'FUNDS_HELD')   $badgeClass = 'badge-held';
elseif ($deal['status'] === 'DELIVERING')   $badgeClass = 'badge-delivering';
elseif ($deal['status'] === 'COMPLETED')    $badgeClass = 'badge-completed';
elseif ($deal['status'] === 'DISPUTED')     $badgeClass = 'badge-disputed';
elseif ($deal['status'] === 'RESOLVED')     $badgeClass = 'badge-resolved';

// Determine the action based on the deal status and user role.
$action = '';

if ($deal['status'] == "AWAITING_PAYMENT" && $user['id'] == $deal['buyer_id']) {
    $action = 'pay';
} elseif ($deal['status'] == "FUNDS_HELD" && $user['id'] == $deal['seller_id']) {
    $action = 'deliver';
} elseif ($deal['status'] == "DELIVERING" && $user['id'] == $deal['buyer_id']) {
    $action = 'confirm';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Deal #<?= htmlspecialchars($deal['deal_code']) ?> — SafeTrade</title>
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

        <p class="mb-16"><a href="dashboard.php">← Back to Dashboard</a></p>

        <div class="card">

            <div class="flex-between">
                <div>
                    <h1 class="mb-0">#<?= htmlspecialchars($deal['deal_code']) ?></h1>
                    <h2 class="text-muted"><?= htmlspecialchars($deal['title']) ?></h2>
                </div>
                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($deal['status']) ?></span>
            </div>

            <p class="text-large mt-16">
                MK <?= number_format($deal['amount'], 2) ?>
            </p>

            <?php if (!empty($deal['description'])): ?>
                <p class="mt-16 text-muted">
                    <?= nl2br(htmlspecialchars($deal['description'])) ?>
                </p>
            <?php endif; ?>

            <hr class="divider">

            <table class="detail-table">
                <tr>
                    <td class="label">Seller</td>
                    <td>
                        <?= htmlspecialchars($deal['seller_name']) ?>
                        (<?= htmlspecialchars($deal['seller_phone']) ?>)
                    </td>
                </tr>
                <tr>
                    <td class="label">Buyer</td>
                    <td>
                        <?= htmlspecialchars($deal['buyer_name']) ?>
                        (<?= htmlspecialchars($deal['buyer_phone']) ?>)
                    </td>
                </tr>
                <tr>
                    <td class="label">Delivery</td>
                    <td><?= htmlspecialchars($deal['delivery_method']) ?></td>
                </tr>
                <tr>
                    <td class="label">Deadline</td>
                    <td><?= htmlspecialchars($deal['deadline']) ?></td>
                </tr>
            </table>

            <div class="mt-24">

                <?php if (isset($_GET['error']) && $_GET['error'] === 'insufficient'): ?>
                    <div class="alert alert-error">
                        Insufficient wallet balance. 
                         <a href="topup.php">Top up your wallet</a> to continue.
                    </div>
                <?php endif; ?>

                <?php if ($action === 'pay'): ?>
                    <a href="pay.php?id=<?= $deal['id'] ?>" class="btn btn-full">
                        Pay MK <?= number_format($deal['amount'], 2) ?> into Escrow
                    </a>

                <?php elseif ($action === 'deliver'): ?>
                    <a href="deliver.php?id=<?= $deal['id'] ?>" class="btn btn-full">
                        Mark as Delivered
                    </a>

                <?php elseif ($action === 'confirm'): ?>
                    <a href="confirm.php?id=<?= $deal['id'] ?>" class="btn btn-full">
                        Confirm Receipt & Release Money
                    </a>

                <?php elseif ($deal['status'] === 'COMPLETED'): ?>
                    <div class="alert alert-success">
                        ✅ This deal is completed. Money has been released to the seller.
                    </div>

                <?php elseif ($deal['status'] === 'RESOLVED'): ?>
                    <div class="alert alert-info">
                        This deal has been resolved by an admin.
                    </div>

                <?php elseif ($deal['status'] === 'CANCELLED'): ?>
                    <div class="alert alert-info">
                        This deal was cancelled.
                    </div>

                <?php elseif ($deal['status'] !== 'DISPUTED'): ?>
                    <div class="alert alert-info">
                        ⏳ Waiting for the other party to take action.
                    </div>
                <?php endif; ?>

                <?php if (in_array($deal['status'], ['FUNDS_HELD', 'DELIVERING'])): ?>
                    <a href="dispute.php?id=<?= $deal['id'] ?>" class="btn btn-sm btn-danger mt-16">
                        ⚠️ Open a Dispute
                    </a>
                <?php endif; ?>

                <?php if ($dispute): ?>
                    <hr class="divider">
                  

                    <table class="detail-table">
                        <tr>
                            <td class="label">Opened by</td>
                            <td><?= htmlspecialchars($dispute['opened_by_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Reason</td>
                            <td><?= htmlspecialchars($dispute['reason']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Description</td>
                            <td><?= nl2br(htmlspecialchars($dispute['description'])) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Opened at</td>
                            <td><?= htmlspecialchars($dispute['created_at']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Status</td>
                            <td><?= strtoupper(htmlspecialchars($dispute['status'])) ?></td>
                        </tr>
                    </table>

                    <h3 class="mt-24">Evidence</h3>

                    <?php if (count($evidence) === 0): ?>
                        <p class="empty">No evidence uploaded yet.</p>
                    <?php else: ?>
                        <ul class="evidence-list">
                            <?php foreach ($evidence as $e): ?>
                                <li>
                                    <a href="<?= htmlspecialchars($e['file_path']) ?>" target="_blank">
                                        <?= htmlspecialchars(basename($e['file_path'])) ?>
                                    </a>
                                    <br>
                                    <span class="text-muted">
                                        By <?= htmlspecialchars($e['uploader_name']) ?>
                                        — <?= htmlspecialchars($e['description']) ?>
                                        — <?= htmlspecialchars($e['created_at']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($dispute['status'] === 'open'): ?>
                        <h3 class="mt-24">Upload Your Evidence</h3>
                        <p class="text-muted mb-16">Both parties can upload proof (screenshots, receipts, photos) to help the admin resolve this.</p>
                        
                        <form method="POST" action="evidence.php" enctype="multipart/form-data">
                            <input type="hidden" name="dispute_id" value="<?= $dispute['id'] ?>">
                            <input type="hidden" name="deal_id" value="<?= $deal['id'] ?>">

                            <label>File (image or PDF)</label>
                            <input type="file" name="evidence_file" accept="image/*,.pdf" required>

                            <label>Short description</label>
                            <input type="text" name="description" placeholder="e.g., Photo of cracked screen" required>

                            <button type="submit" class="btn btn-full">Upload Evidence</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>