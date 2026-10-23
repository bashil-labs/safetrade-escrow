<?php
require_once 'config/db.php';
require_once 'config/session.php';

// If already logged in, go to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? 'buyer';

    // Validate
    if (empty($full_name) || empty($phone) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone must be 10 digits.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!in_array($role, ['buyer', 'seller'])) {
        $error = "Invalid role selected.";
    } else {
        // Check if phone already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "This phone number is already registered.";
        } else {
            // Hash the password
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (full_name, phone, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $phone, $hash, $role);

            if ($stmt->execute()) {
                $success = "Account created! You can now log in.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register — SafeTrade</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container-narrow">
        <div class="card">
            <h1>Create Account</h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Full Name</label>
                <input type="text" name="full_name" required>

                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="0991111111" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <label>I want to</label>
                <select name="role" required>
                    <option value="buyer">Buy items (Buyer)</option>
                    <option value="seller">Sell items (Seller)</option>
                </select>

                <button type="submit" class="btn btn-full">Create Account</button>
            </form>

            <p class="text-center mt-16">
                Already have an account? <a href="login.php">Log in</a>
            </p>
        </div>
    </div>
</body>
</html>