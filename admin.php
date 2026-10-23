<?php
// ============================================
// ADMIN PANEL — View and resolve disputes
// ============================================
require_once 'config/db.php';
require_once 'config/session.php';
requireLogin();

$user = currentUser();

// --------------------------------------------
// 1. Only admins allowed
// --------------------------------------------
if ($user['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// --------------------------------------------
// 2. Check if viewing a specific dispute
// --------------------------------------------
$dispute_id = $_GET['id'] ?? null;

if ($dispute_id && !is_numeric($dispute_id)) {
    header("Location: admin.php");
    exit;
}

// ============================================
// CHUNK 3: HANDLE RESOLUTION (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dispute_id) {
    $dispute_id = (int) $dispute_id;
    $resolution = $_POST['resolution'] ?? '';
    $note       = trim($_POST['note'] ?? '');

    if (!in_array($resolution, ['refund', 'release'])) {
        header("Location: admin.php?id=" . $dispute_id);
        exit;
    }

    // Load the dispute + deal
    $stmt = $conn->prepare("
        SELECT dp.*, d.id AS deal_id, d.amount, d.seller_id, d.buyer_id, d.deal_code
        FROM disputes dp
        JOIN deals d ON dp.deal_id = d.id
        WHERE dp.id = ?
    ");
    $stmt->bind_param("i", $dispute_id);
    $stmt->execute();
    $disp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$disp || $disp['status'] !== 'open') {
        header("Location: admin.php?id=" . $dispute_id);
        exit;
    }

    $deal_id   = $disp['deal_id'];
    $amount    = $disp['amount'];
    $buyer_id  = $disp['buyer_id'];
    $seller_id = $disp['seller_id'];
    $deal_code = $disp['deal_code'];

    // --- Perform the resolution ---
    if ($resolution === 'refund') {
        $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $buyer_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO transactions (deal_id, from_user, to_user, amount, type)
            VALUES (?, NULL, ?, ?, 'refund')
        ");
        $stmt->bind_param("iid", $deal_id, $buyer_id, $amount);
        $stmt->execute();
        $stmt->close();

        $msg_buyer  = "Dispute resolved for deal #$deal_code. You have been refunded MK " . number_format($amount, 2) . ".";
        $msg_seller = "Dispute resolved for deal #$deal_code. The buyer has been refunded.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $seller_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO transactions (deal_id, from_user, to_user, amount, type)
            VALUES (?, NULL, ?, ?, 'release')
        ");
        $stmt->bind_param("iid", $deal_id, $seller_id, $amount);
        $stmt->execute();
        $stmt->close();

        $msg_buyer  = "Dispute resolved for deal #$deal_code. The funds have been released to the seller.";
        $msg_seller = "Dispute resolved for deal #$deal_code. You have received MK " . number_format($amount, 2) . ".";
    }

    // Update dispute status
    $stmt = $conn->prepare("
        UPDATE disputes
        SET status = 'resolved', resolution = ?, resolved_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $resolution, $user['id'], $dispute_id);
    $stmt->execute();
    $stmt->close();

    // Update deal status
    $stmt = $conn->prepare("UPDATE deals SET status = 'RESOLVED' WHERE id = ?");
    $stmt->bind_param("i", $deal_id);
    $stmt->execute();
    $stmt->close();

    // Notify both parties
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $buyer_id, $deal_id, $msg_buyer);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, deal_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $seller_id, $deal_id, $msg_seller);
    $stmt->execute();
    $stmt->close();

    header("Location: admin.php?id=" . $dispute_id);
    exit;
}

// ============================================
// CHUNK 1: LIST VIEW (no ?id in URL)
// ============================================
if (!$dispute_id) {
    $disputes = $conn->query("
        SELECT dp.id, dp.reason, dp.status, dp.created_at,
               d.deal_code,
               u.full_name AS opened_by_name
        FROM disputes dp
        JOIN deals d ON dp.deal_id = d.id
        JOIN users u ON dp.opened_by = u.id
        ORDER BY dp.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

// ============================================
// CHUNK 2: DETAIL VIEW (with ?id in URL)
// ============================================
if ($dispute_id) {
    $dispute_id = (int) $dispute_id;

    $stmt = $conn->prepare("
        SELECT dp.*,
               d.deal_code, d.title, d.amount, d.status AS deal_status,
               d.seller_id, d.buyer_id,
               opener.full_name AS opened_by_name,
               seller.full_name AS seller_name,
               buyer.full_name  AS buyer_name
        FROM disputes dp
        JOIN deals d        ON dp.deal_id    = d.id
        JOIN users opener   ON dp.opened_by  = opener.id
        JOIN users seller   ON d.seller_id   = seller.id
        JOIN users buyer    ON d.buyer_id    = buyer.id
        WHERE dp.id = ?
    ");
    $stmt->bind_param("i", $dispute_id);
    $stmt->execute();
    $dispute = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dispute) {
        header("Location: admin.php");
        exit;
    }

    $stmt = $conn->prepare("
        SELECT e.*, u.full_name AS uploader_name
        FROM evidence e
        JOIN users u ON e.uploaded_by = u.id
        WHERE e.dispute_id = ?
        ORDER BY e.id ASC
    ");
    $stmt->bind_param("i", $dispute_id);
    $stmt->execute();
    $evidence = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin — SafeTrade</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- ===== TOPBAR ===== -->
    <div class="topbar">
        <h1>🔒  SafeTrade Admin</h1>
        <div class="topbar-user">
            <a href="notifications.php">
                Notifications
                <?php $unread = unreadNotifications($conn, $user['id']); ?>
                <?php if ($unread > 0): ?>
                    <span class="notif-badge"><?= $unread ?></span>
                <?php endif; ?>
            </a>
            <a href="admin.php">Admin</a>
            <span>Hello, <?= htmlspecialchars($user['full_name']) ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">

        <?php if ($dispute_id): ?>
        <!-- ============================================
             DETAIL VIEW
             ============================================ -->

            <p class="mb-16"><a href="admin.php">← Back to All Disputes</a></p>

            <div class="card">
                <h2>Dispute #<?= $dispute['id'] ?></h2>
                <p class="text-muted mt-16">
                    Deal <strong>#<?= htmlspecialchars($dispute['deal_code']) ?></strong>
                    — <?= htmlspecialchars($dispute['title']) ?>
                    — MK <?= number_format($dispute['amount'], 2) ?>
                </p>

                <hr class="divider">

                <h3>Parties</h3>
                <table class="detail-table">
                    <tr>
                        <td class="label">Seller</td>
                        <td><?= htmlspecialchars($dispute['seller_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Buyer</td>
                        <td><?= htmlspecialchars($dispute['buyer_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Deal status</td>
                        <td><?= htmlspecialchars($dispute['deal_status']) ?></td>
                    </tr>
                </table>

                <hr class="divider">

                <h3>Dispute Details</h3>
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

                <hr class="divider">

                <h3>Evidence</h3>
                <?php if (count($evidence) === 0): ?>
                    <p class="empty">No evidence was uploaded for this dispute.</p>
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
                    <hr class="divider">

                    <h3>Resolve Dispute</h3>
                    <p class="text-muted mb-16">
                        Choose what happens to the MK <?= number_format($dispute['amount'], 2) ?> held in escrow.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="dispute_id" value="<?= $dispute['id'] ?>">

                        <label>Resolution</label>
                        <select name="resolution" required>
                            <option value="">Select...</option>
                            <option value="refund">Refund buyer (MK <?= number_format($dispute['amount'], 2) ?> → buyer)</option>
                            <option value="release">Release to seller (MK <?= number_format($dispute['amount'], 2) ?> → seller)</option>
                        </select>

                        <label>Note (optional)</label>
                        <textarea name="note" placeholder="Explain the decision..."></textarea>

                        <button type="submit" class="btn btn-full">Resolve</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mt-24">
                        This dispute has been resolved
                        (<?= htmlspecialchars($dispute['resolution'] ?? '') ?>).
                    </div>
                <?php endif; ?>

            </div>

        <?php else: ?>
        <!-- ============================================
             LIST VIEW
             ============================================ -->

            <h2 class="mb-24">All Disputes</h2>

            <div class="card">
                <?php if (count($disputes) === 0): ?>
                    <p class="empty">No disputes have been filed yet.</p>
                <?php else: ?>
                    <table class="deal-table">
                        <thead>
                            <tr>
                                <th>Deal</th>
                                <th>Reason</th>
                                <th>Opened By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputes as $dp): ?>
                                <?php
                                    $badgeClass = 'badge-cancelled';
                                    if ($dp['status'] === 'open')             $badgeClass = 'badge-pending';
                                    elseif ($dp['status'] === 'under_review') $badgeClass = 'badge-held';
                                    elseif ($dp['status'] === 'resolved')     $badgeClass = 'badge-completed';
                                ?>
                                <tr>
                                    <td class="code">#<?= htmlspecialchars($dp['deal_code']) ?></td>
                                    <td><?= htmlspecialchars($dp['reason']) ?></td>
                                    <td><?= htmlspecialchars($dp['opened_by_name']) ?></td>
                                    <td><?= htmlspecialchars($dp['created_at']) ?></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($dp['status']) ?></span></td>
                                    <td><a href="admin.php?id=<?= $dp['id'] ?>" class="view-link">View →</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>