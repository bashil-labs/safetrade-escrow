<?php
require_once 'config/db.php';
require_once 'config/session.php';

// If already logged in, go to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($phone) || empty($password)) {
        $error = "Phone and password are required.";
    } else {
        // Find the user by phone
        $stmt = $conn->prepare("SELECT id, full_name, password_hash, role, is_banned FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "Invalid phone or password.";
        } else {
            $user = $result->fetch_assoc();

            if ($user['is_banned']) {
                $error = "Your account has been suspended.";
            } elseif (password_verify($password, $user['password_hash'])) {
                // Correct login — store in session
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid phone or password.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login — SafeTrade</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container-narrow">
        <div class="card">
            <h1>Sign In</h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="0991111111" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <button type="submit" class="btn btn-full">Sign In</button>
            </form>

            <p class="text-center mt-16">
                Don't have an account? <a href="register.php">Register</a>
            </p>
        </div>
    </div>
</body>oti
</html>