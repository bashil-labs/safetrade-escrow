<?php
// Start the session so PHP remembers who is logged in
session_start();

// check if someone is  logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// get the current user's basic info
function currentUser() {
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role'      => $_SESSION['role'] ?? null
    ];
}

// force loggin - kick out if not logged in
function requireLogin() {
    if(!isLoggedIn()) {
        header("Location: /escrow/login.php");
        exit;
    }
}

// Get unread notifications count for a user
function unreadNotifications($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['total'];
}
?>