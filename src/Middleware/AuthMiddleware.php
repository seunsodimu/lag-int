<?php

namespace Laguna\Integration\Middleware;

use Laguna\Integration\Services\AuthService;
use Laguna\Integration\Utils\UrlHelper;

/**
 * Authentication Middleware
 * 
 * Handles authentication checks for protected pages
 */
class AuthMiddleware {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }
    
    /**
     * Check if user is authenticated
     */
    public function requireAuth($requiredRole = null) {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        
        $sessionId = $_SESSION['session_id'] ?? $_COOKIE['session_id'] ?? null;
        
        if (!$sessionId) {
            $this->redirectToLogin();
            return false;
        }
        
        $user = $this->authService->validateSession($sessionId);
        
        if (!$user) {
            $this->clearSession();
            $this->redirectToLogin();
            return false;
        }
        
        // Check role if required
        if ($requiredRole && $user['role'] !== $requiredRole && $user['role'] !== 'admin') {
            $this->accessDenied();
            return false;
        }
        
        // Store user info in session for easy access
        $_SESSION['user'] = $user;
        $_SESSION['session_id'] = $sessionId;
        
        return $user;
    }
    
    /**
     * Check if user is admin
     */
    public function requireAdmin() {
        return $this->requireAuth('admin');
    }
    
    /**
     * Get current authenticated user
     */
    public function getCurrentUser() {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        return $_SESSION['user'] ?? null;
    }
    
    /**
     * Check if user is authenticated (without redirect)
     */
    public function isAuthenticated() {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        
        $sessionId = $_SESSION['session_id'] ?? $_COOKIE['session_id'] ?? null;
        
        if (!$sessionId) {
            return false;
        }
        
        $user = $this->authService->validateSession($sessionId);
        
        if ($user) {
            $_SESSION['user'] = $user;
            $_SESSION['session_id'] = $sessionId;
            return true;
        }
        
        return false;
    }
    
    /**
     * Redirect to login page
     */
    private function redirectToLogin() {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        $params = [];
        
        $loginUrl = UrlHelper::url('login.php');
        if ($currentUrl !== $loginUrl && !empty($currentUrl)) {
            $params['redirect'] = $currentUrl;
        }
        
        UrlHelper::redirect('login.php', $params);
    }
    
    /**
     * Show access denied page
     */
    private function accessDenied() {
        http_response_code(403);
        include __DIR__ . '/../../public/access-denied.php';
        exit;
    }
    
    /**
     * Clear session data
     */
    private function clearSession() {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        
        // Clear session cookie we set for remember me
        if (isset($_COOKIE['session_id'])) {
            setcookie('session_id', '', time() - 3600, '/');
        }
    }
    
    /**
     * Login user
     */
    public function login($username, $password, $rememberMe = false) {
        $result = $this->authService->login($username, $password, $rememberMe);
        
        if ($result['success']) {
            if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
            $_SESSION['user'] = $result['user'];
            $_SESSION['session_id'] = $result['session_id'];
            
            // Set cookie if remember me is checked
            if ($rememberMe) {
                setcookie('session_id', $result['session_id'], time() + (30 * 24 * 3600), '/'); // 30 days
            }
        }
        
        return $result;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        
        $sessionId = $_SESSION['session_id'] ?? null;
        
        if ($sessionId) {
            $this->authService->logout($sessionId);
        }
        
        $this->clearSession();
        
        return ['success' => true];
    }
}