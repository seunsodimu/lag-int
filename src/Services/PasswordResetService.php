<?php

namespace Laguna\Integration\Services;

use PDO;
use PDOException;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Utils\UrlHelper;

/**
 * Password Reset Service
 *
 * Handles password reset token generation, validation, and password updates.
 */
class PasswordResetService {
    private $pdo;
    private $logger;
    private $config;

    const RESET_TOKEN_EXPIRY_MINUTES = 60; // 1 hour

    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->initializeDatabase();
    }

    private function initializeDatabase() {
        try {
            $dbConfig = $this->config['database'];
            if (!$dbConfig['enabled']) {
                throw new \Exception('Database is not enabled in configuration');
            }
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $this->logger->error('Failed to connect to database for password reset', ['error' => $e->getMessage()]);
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Request a password reset by email.
     * Returns explicit results about email existence and provider send status (per user request).
     */
    public function requestReset(string $email): array {
        try {
            $this->ensurePasswordResetTable();

            $user = $this->getUserByEmail($email);
            if (!$user || (int)($user['is_active'] ?? 1) !== 1) {
                return ['success' => false, 'error' => 'Email not found or inactive', 'email_exists' => false];
            }

            // Generate token and store hash
            $token = bin2hex(random_bytes(32)); // 64 chars
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + self::RESET_TOKEN_EXPIRY_MINUTES * 60);

            $stmt = $this->pdo->prepare("INSERT INTO user_password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $tokenHash, $expiresAt]);

            // Send email with reset link and report provider result
            $emailResult = $this->sendResetEmail($user, $token, $expiresAt);

            if (!empty($emailResult['success'])) {
                $this->logActivity($user['id'], 'password_reset_requested', 'Password reset email sent');
                return ['success' => true, 'message' => $emailResult['message'] ?? 'Reset link sent'];
            } else {
                $this->logActivity($user['id'], 'password_reset_request_failed', 'Password reset email failed to send');
                $errMsg = $emailResult['message'] ?? $emailResult['error'] ?? 'Failed to send reset email';
                return ['success' => false, 'error' => $errMsg, 'email_exists' => true];
            }
        } catch (\Exception $e) {
            $this->logger->error('Password reset request failed', ['email' => $email, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to process reset request'];
        }
    }

    /**
     * Verify a password reset token.
     */
    public function verifyToken(string $token): ?array {
        $tokenHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare("SELECT r.*, u.username, u.email FROM user_password_resets r JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() LIMIT 1");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Reset the password using a valid token.
     */
    public function resetPassword(string $token, string $newPassword): array {
        try {
            if (strlen($newPassword) < AuthService::PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'error' => 'Password must be at least ' . AuthService::PASSWORD_MIN_LENGTH . ' characters long'];
            }

            $record = $this->verifyToken($token);
            if (!$record) {
                return ['success' => false, 'error' => 'Invalid or expired token'];
            }

            // Update user password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$passwordHash, $record['user_id']]);

            // Mark token used
            $stmt = $this->pdo->prepare('UPDATE user_password_resets SET used_at = NOW() WHERE id = ?');
            $stmt->execute([$record['id']]);

            $this->pdo->commit();

            $this->logActivity($record['user_id'], 'password_reset', 'Password reset via email link');

            return ['success' => true, 'message' => 'Password has been reset. You can now log in.'];
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Password reset failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to reset password'];
        }
    }

    private function sendResetEmail(array $user, string $token, string $expiresAt): array {
        $emailService = new EnhancedEmailService();

        // Build absolute URL (fallback to relative if host missing)
        $path = UrlHelper::url('reset-password.php');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $baseUrl = $host ? ($scheme . $host) : '';
        $resetUrl = $baseUrl . $path . '?token=' . urlencode($token);

        return $emailService->sendPasswordResetEmail($user['email'], $user['username'], $resetUrl, $expiresAt);
    }

    private function ensurePasswordResetTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS user_password_resets (\n"
             . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
             . "  user_id INT NOT NULL,\n"
             . "  token_hash CHAR(64) NOT NULL,\n"
             . "  expires_at TIMESTAMP NOT NULL,\n"
             . "  used_at TIMESTAMP NULL,\n"
             . "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
             . "  INDEX idx_user_id (user_id),\n"
             . "  UNIQUE INDEX idx_token_hash (token_hash),\n"
             . "  INDEX idx_expires (expires_at),\n"
             . "  CONSTRAINT fk_upr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n"
             . ")";
        $this->pdo->exec($sql);
    }

    private function getUserByEmail($email) {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    private function logActivity($userId, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO user_activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log password reset activity', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }
}