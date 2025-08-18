<?php
session_start();
require_once 'config.php';

// Read the reset token from the link. If it's missing, stop early.
$token = $_GET['token'] ?? '';
if (!$token) {
    die("Invalid or missing token.");
}

$error = null; // Will hold a single friendly error message if something fails

// If the form was submitted, try to reset the password.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1) Basic sanity checks on the two password fields
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (
        !preg_match('/[A-Z]/', $new_password) ||   // needs uppercase
        !preg_match('/[a-z]/', $new_password) ||   // needs lowercase
        !preg_match('/\d/', $new_password) ||      // needs number
        !preg_match('/[^A-Za-z0-9]/', $new_password) // needs symbol
    ) {
        $error = "Password must include upper, lower, number, and symbol.";
    } else {
        // 2) Look up the user by the reset token
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE reset_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 3) Don’t allow reusing the current password
            if (password_verify($new_password, $user['password'])) {
                $error = "Please choose a password you haven't used before.";
            } else {
                // 4) Hash the new password and clear the token (and its expiry)
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare("
                    UPDATE users
                    SET password = ?, reset_token = NULL, reset_token_expires = NULL
                    WHERE id = ?
                ");
                $uid = (int)$user['id'];
                $update->bind_param("si", $hashed, $uid);
                $update->execute();

                // 5) Friendly success, then send the user to login
                echo "<script>
                    alert('✅ Password reset successfully!');
                    window.location.href = 'login.php';
                </script>";
                exit;
            }
        } else {
            // Token didn’t match any user (or already used/expired and cleared)
            $error = "Invalid or expired token.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password | MediVerse</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .reset-card {
            margin-top: 80px;
            padding: 30px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .password-meter-label { font-size: .9rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <!-- Simple centered card for the reset form -->
            <div class="reset-card">
                <h3 class="mb-4 text-center">🔐 Reset Your Password</h3>

                <!-- Show any server-side error from the PHP checks above -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <!-- Keep the same action (POST to self). Token comes from the URL. -->
                <form method="POST" novalidate>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" id="new_password" name="new_password" class="form-control" required aria-describedby="pwdHelp">
                            <button class="btn btn-outline-secondary" type="button" id="togglePw1">Show</button>
                        </div>
                        <div id="pwdHelp" class="form-text">
                            At least 8 chars, with uppercase, lowercase, number, and symbol.
                        </div>

                        <!-- Visual strength meter (client-side only) -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="password-meter-label">Strength:</span>
                                <span id="strengthText" class="password-meter-label fw-semibold">Too weak</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div id="strengthBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePw2">Show</button>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS (optional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Client-side helpers: strength meter + show/hide password
(function() {
    const pw = document.getElementById('new_password');
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    const toggle1 = document.getElementById('togglePw1');
    const toggle2 = document.getElementById('togglePw2');
    const confirmPw = document.getElementById('confirm_password');

    // Heuristic scoring for UX only (server-side rules still enforce real policy)
    function scorePassword(p) {
        let score = 0;
        if (!p) return 0;

        // Length bonuses
        if (p.length >= 8) score += 20;
        if (p.length >= 12) score += 15;
        if (p.length >= 16) score += 15;

        // Character variety
        if (/[a-z]/.test(p)) score += 15;
        if (/[A-Z]/.test(p)) score += 15;
        if (/\d/.test(p))    score += 10;
        if (/[^A-Za-z0-9]/.test(p)) score += 15;

        // Light penalties for repeats
        if (/(.)\1{2,}/.test(p)) score -= 10;
        if (/([a-zA-Z0-9])\1{3,}/.test(p)) score -= 10;

        return Math.max(0, Math.min(100, score));
    }

    function setBar(val) {
        bar.style.width = val + '%';

        let label = 'Too weak';
        let cls = 'bg-danger';
        if (val >= 80)      { label = 'Strong'; cls = 'bg-success'; }
        else if (val >= 60) { label = 'Good';   cls = 'bg-info';    }
        else if (val >= 40) { label = 'Fair';   cls = 'bg-warning'; }

        bar.className = 'progress-bar ' + cls; // keep only one bg class
        text.textContent = label;
    }

    pw.addEventListener('input', function() {
        setBar(scorePassword(pw.value || ''));
    });

    // Show/hide buttons for each password field
    function toggle(el, btn) {
        const isHidden = el.type === 'password';
        el.type = isHidden ? 'text' : 'password';
        btn.textContent = isHidden ? 'Hide' : 'Show';
    }
    toggle1.addEventListener('click', () => toggle(pw, toggle1));
    toggle2.addEventListener('click', () => toggle(confirmPw, toggle2));
})();
</script>

</body>
</html>
