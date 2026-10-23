<?php
require_once 'config/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Welcome — SafeTrade Escrow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #1e3a8a 0%, #0ea5e9 100%);">

    <div class="card" style="max-width: 500px; text-align: center; padding: 50px 40px; margin: 20px;">
        
        <div style="background: #e0f2fe; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
            <span style="font-size: 40px;">🔒</span>
        </div>
        
        <h1 style="color: #1e3a8a; font-size: 36px; margin-bottom: 12px; font-weight: 800;">SafeTrade</h1>
        
        <p style="color: #64748b; font-size: 16px; margin-bottom: 35px; line-height: 1.6;">
            The trusted escrow platform for Malawi. We hold funds securely until both the buyer and seller are satisfied.
        </p>

        <div style="display: flex; flex-direction: column; gap: 15px;">
            <a href="login.php" class="btn btn-full" style="margin-top: 0;">Sign In to Your Account</a>
            <a href="register.php" class="btn btn-secondary btn-full" style="margin-top: 0;">Create a New Account</a>
        </div>

    </div>

</body>
</html>