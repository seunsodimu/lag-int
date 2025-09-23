<?php
/**
 * Reset Password Page
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\PasswordResetService;
use Laguna\Integration\Utils\UrlHelper;

$service = new PasswordResetService();
$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'request') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $result = $service->requestReset($email);
            if (!empty($result['success'])) {
                $success = $result['message'] ?? 'Reset link sent.';
            } else {
                // Explicitly show whether email exists or not and provider send status
                if (isset($result['email_exists']) && $result['email_exists'] === false) {
                    $error = 'Email address not found.';
                } else {
                    $error = $result['error'] ?? 'Failed to send reset email.';
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'reset') {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        if ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            $result = $service->resetPassword($token, $password);
            if ($result['success']) {
                $success = 'Password reset successful. You can now log in.';
            } else {
                $error = $result['error'] ?? 'Failed to reset password.';
            }
        }
    }
}

$hasToken = !empty($token) && $service->verifyToken($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - 3DCart NetSuite Integration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container-box { background: white; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); max-width: 500px; width: 100%; overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; text-align: center; }
        .body { padding: 1.5rem; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
    </style>
</head>
<body>
<div class="container-box">
    <div class="header">
        <i class="fas fa-unlock-alt fa-2x mb-2"></i>
        <h4 class="mb-0">Password Reset</h4>
    </div>
    <div class="body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($hasToken): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="mb-3">
                    <label class="form-label" for="password">New Password</label>
                    <input class="form-control" type="password" id="password" name="password" placeholder="Enter new password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password2">Confirm Password</label>
                    <input class="form-control" type="password" id="password2" name="password2" placeholder="Confirm new password" required>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-key me-2"></i>Reset Password</button>
                </div>
            </form>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="request">
                <div class="mb-3">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-control" type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane me-2"></i>Send Reset Link</button>
                </div>
            </form>
        <?php endif; ?>

        <hr class="my-4">
        <div class="text-center"><a href="<?= UrlHelper::url('login.php') ?>">Back to login</a></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>