<?php
namespace App\Controllers;

use App\Models\UserModel;
use App\Models\AuditModel;
use App\Helpers\RoleHelper;

class AuthController {
    private $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $_SESSION['error'] = 'Please enter username and password';
                $this->render('login', []);
                return;
            }

            $user = $this->userModel->verifyLogin($username, $password);

            if ($user) {
                if ($user['status'] == 0) {
                    $_SESSION['error'] = 'Account is inactive';
                    $this->render('login', []);
                    return;
                }

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['role'] = !empty($user['role']) ? $user['role'] : '';

                AuditModel::log($user['user_id'], 'LOGIN', 'auth', 'User logged in: ' . $user['username'], null, ['username' => $username], 'user', $user['user_id']);

                // Warehouse/QC users need a role to land on their role home;
                // without one they get the "no role assigned" holding page.
                if (in_array($user['department'], ['warehouse', 'qc'], true) && $_SESSION['role'] === '') {
                    header("Location: ?controller=auth&action=noRole");
                    exit;
                }

                header('Location: ' . RoleHelper::homeUrl());
                exit;
            } else {
                $_SESSION['error'] = 'Invalid username or password';
                $this->render('login', []);
                return;
            }
        }

        if (isset($_SESSION['user_id'])) {
            $department = $_SESSION['department'] ?? '';
            if (in_array($department, ['warehouse', 'qc'], true) && RoleHelper::currentRole() === null) {
                header("Location: ?controller=auth&action=noRole");
                exit;
            }
            header('Location: ' . RoleHelper::homeUrl());
            exit;
        }

        $this->render('login', []);
    }

    public function signup() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $department = $_POST['department'] ?? '';

            $errors = [];

            if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($department)) {
                $errors[] = 'All fields are required';
            }

            if (strlen($username) < 3) {
                $errors[] = 'Username must be at least 3 characters';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address';
            }

            if (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters';
            }

            if ($password !== $confirm_password) {
                $errors[] = 'Passwords do not match';
            }

            if ($this->userModel->getByUsername($username)) {
                $errors[] = 'Username already taken';
            }

            if ($this->userModel->getByEmail($email)) {
                $errors[] = 'Email already registered';
            }

            if (!empty($errors)) {
                $data['errors'] = $errors;
                $data['old'] = $_POST;
                $this->render('signup', $data);
                return;
            }

            $userId = $this->userModel->create([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'full_name' => $full_name,
                'department' => $department
            ]);

            AuditModel::log($userId, 'CREATE', 'auth', 'New user registered: ' . $_POST['username'], null, ['username' => $_POST['username'], 'department' => $_POST['department']], 'user', $userId);

            $_SESSION['success'] = 'Account created successfully. Please login.';
            header('Location: ?controller=auth&action=login');
            exit;
        }

        $this->render('signup', []);
    }

    public function logout() {
        AuditModel::log($_SESSION['user_id'], 'LOGOUT', 'auth', 'User logged out: ' . ($_SESSION['username'] ?? ''), null, null, 'user', $_SESSION['user_id'] ?? null);
        session_destroy();
        header('Location: ?controller=auth&action=login');
        exit;
    }

    /** Holding page for warehouse/QC accounts that have no role assigned yet. */
    public function noRole() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?controller=auth&action=login');
            exit;
        }
        $department = $_SESSION['department'] ?? '';
        if (!in_array($department, ['warehouse', 'qc'], true) || RoleHelper::currentRole() !== null) {
            header('Location: ' . RoleHelper::homeUrl());
            exit;
        }
        $data['page_title'] = 'No Role Assigned';
        $this->render('no_role', $data);
    }

    private function render($view, $data = []) {
        extract($data);
        $departments = ['admin', 'warehouse', 'production', 'finance', 'qc', 'qa', 'rnd'];
        include __DIR__ . "/../views/{$view}.php";
    }
}