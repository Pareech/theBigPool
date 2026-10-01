<?php
session_start();
require_once __DIR__ . '/../db_connections/connection_pdo.php';

// Redirect to login (scores.php) if not logged in
if (!isset($_SESSION['gm_name'])) {
    header("Location: ../?expired=1");
    exit();
}

$gmName = $_SESSION['gm_name'];
$message = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $message = "Both fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{8,}$/', $new_password)) {
        $message = "Password must be at least 8 characters long and include uppercase, lowercase, a number, and a special character.";
    } else {
        try {
            // 🔹 Get current hashed password from DB
            $stmt = $pdo->prepare("SELECT user_password FROM gms WHERE gm_name = :gm");
            $stmt->execute([':gm' => $gmName]);
            $currentHash = $stmt->fetchColumn();

            if (!$currentHash) {
                $message = "User not found.";
            } elseif (password_verify($new_password, $currentHash)) {
                // 🔹 Prevent using the same password
                $message = "Your new password cannot be the same as your current password.";
            } else {
                // 🔹 Update with new hashed password
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE gms 
                                         SET user_password = :password, must_change_password = FALSE 
                                         WHERE gm_name = :gm");
                $update->execute([
                    ':password' => $hashedPassword,
                    ':gm' => $gmName
                ]);

                session_destroy();
                echo
                "<script>
                    alert('Password has been successfully updated.\\n'
                        + 'You will be redirected to the login page.\\n\\n'
                        + 'Please login with your new password.');
                    window.location.href = '../';
                </script>";
                exit();
            }
        } catch (PDOException $e) {
            $message = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="../css/change_password.css">
</head>

<body>
    <div class="container">
        <h2>Change Your Password</h2>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'pwchange'): ?>
            <div class="password-rules">
                <strong>Security Update Required:</strong><br>
                You must change your password.<br><br>
                <ul>
                    <li>At least 8 characters long</li>
                    <li>Include uppercase and lowercase letters</li>
                    <li>Include a number</li>
                    <li>Include a special character</li>
                    <li>Cannot use the same password again</li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="<?= strpos($message, 'successfully') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="input-container">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
                <span class="toggle-password" onclick="togglePassword('new_password')">👁️</span>
                <div class="password-strength">
                    <div id="strength-bar"></div>
                </div>
                <div id="strength-text" class="strength-text"></div>
            </div>

            <div class="input-container">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <span class="toggle-password" onclick="togglePassword('confirm_password')">👁️</span>
            </div>

            <input type="submit" value="Change Password">
        </form>

        <a href="../" class="back-link">Back to Login Page</a>
    </div>

    <script>
        // Show/hide password toggle
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // Password strength meter with live text
        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');
        const passwordInput = document.getElementById('new_password');

        passwordInput.addEventListener('input', () => {
            const val = passwordInput.value;
            let strength = 0;

            if (val.length >= 8) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[a-z]/.test(val)) strength++;
            if (/\d/.test(val)) strength++;
            if (/[^A-Za-z\d]/.test(val)) strength++;

            // Update bar width
            let width = (strength / 5) * 100;
            strengthBar.style.width = width + '%';

            // Reset classes
            strengthBar.className = '';

            // Update strength bar color & text
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = "Weak";
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = "Medium";
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = "Strong";
            }
        });
    </script>

</body>

</html>