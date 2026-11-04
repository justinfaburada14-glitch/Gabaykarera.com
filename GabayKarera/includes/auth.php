<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function user() {
        if (!self::isLoggedIn()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'full_name' => $_SESSION['user_name'] ?? 'User',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'applicant',
            'company_name' => $_SESSION['user_company'] ?? '',
            'avatar' => $_SESSION['user_avatar'] ?? 'default_avatar.png',
        ];
    }

    public static function requireAuth($allowedRoles = []) {
        if (!self::isLoggedIn()) {
            $_SESSION['flash_error'] = "Please log in to access this page.";
            header("Location: /GabayKarera/login.php");
            exit;
        }

        if (!empty($allowedRoles)) {
            $userRole = $_SESSION['user_role'] ?? '';
            if (!in_array($userRole, $allowedRoles)) {
                $_SESSION['flash_error'] = "Access denied. You do not have permission for this section.";
                if ($userRole === 'hr') {
                    header("Location: /GabayKarera/hr/dashboard.php");
                } elseif ($userRole === 'admin') {
                    header("Location: /GabayKarera/admin/dashboard.php");
                } else {
                    header("Location: /GabayKarera/applicant/dashboard.php");
                }
                exit;
            }
        }
    }

    public static function login($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_company'] = $user['company_name'] ?? '';
        $_SESSION['user_avatar'] = $user['avatar'] ?? 'default_avatar.png';
    }

    public static function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
?>
