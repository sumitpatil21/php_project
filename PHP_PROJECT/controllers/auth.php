<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Database connection with multiple path attempts
$possible_paths = [
    '../includes/db.php',           
    'includes/db.php',             
    './includes/db.php',           
    dirname(__FILE__) . '/../includes/db.php'  
];

$db_found = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_found = true;
        break;
    }
}

if (!$db_found) {
    // Inline database connection
    $host = 'localhost';
    $dbname = 'php_project';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// Set proper headers for JSON response
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Debug: Log the request
error_log("Auth request: action=$action, method=" . $_SERVER['REQUEST_METHOD']);

try {
    switch ($action) {
        case 'register':
            handleRegister();
            break;
        case 'login':
            handleLogin();
            break;
        case 'logout':
            handleLogout();
            break;
        case 'check':
            handleCheckAuth();
            break;
        case 'test':
            handleTest();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("Auth error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function handleTest() {
    echo json_encode(['success' => true, 'message' => 'Auth controller is working', 'timestamp' => date('Y-m-d H:i:s')]);
}

function handleCheckAuth() {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'full_name' => $_SESSION['full_name']
            ]
        ]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
}

function handleRegister() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        return;
    }

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        return;
    }

    try {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            return;
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }

        // Hash password and insert user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, full_name, phone, is_active, email_verified) VALUES (?, ?, ?, ?, ?, 1, 0)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$username, $email, $hashed_password, $full_name, $phone]);
        
        if ($result) {
            $user_id = $pdo->lastInsertId();
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! You can now login.',
                'user_id' => $user_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }

    } catch(PDOException $e) {
        if ($e->getCode() == '42S02') { // Table doesn't exist
            // Auto-create table and retry
            try {
                $createTable = "CREATE TABLE IF NOT EXISTS `users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(50) NOT NULL UNIQUE,
                    `email` varchar(100) NOT NULL UNIQUE,
                    `password` varchar(255) NOT NULL,
                    `full_name` varchar(100) NOT NULL,
                    `phone` varchar(20) DEFAULT NULL,
                    `is_active` tinyint(1) DEFAULT 1,
                    `email_verified` tinyint(1) DEFAULT 0,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                
                $pdo->exec($createTable);
                
                // Retry insert
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$username, $email, $hashed_password, $full_name, $phone]);
                
                if ($result) {
                    $user_id = $pdo->lastInsertId();
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Registration successful! (Table created automatically)',
                        'user_id' => $user_id
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Registration failed after creating table.']);
                }
                
            } catch(PDOException $e2) {
                echo json_encode(['success' => false, 'message' => 'Table creation failed: ' . $e2->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}

function handleLogin() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        return;
    }

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = $_POST['password'] ?? '';

    // Debug: Log login attempt
    error_log("Login attempt for username: $username");

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        return;
    }

    try {
        // Check user exists (can login with username or email)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if user is active
            if ($user['is_active'] == 1) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                
                // --- THIS IS THE CRITICAL FIX ---
                // Check the database column and save it to the session
                $isAdmin = (isset($user['is_admin']) && $user['is_admin'] == 1);

                $_SESSION['is_admin'] = $isAdmin;
                // --- END FIX ---

                // Determine where to redirect
            $redirect_url = $isAdmin ? '../admin/admin_panel.php' : '../index.php';
                
                // Debug: Log successful login
                error_log("User logged in successfully: ID=" . $user['id'] . ", IsAdmin=" . $isAdmin);
                error_log("Session data: " . print_r($_SESSION, true));
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Login successful! Redirecting...',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'is_admin' => $isAdmin // Send to JS
                    ],
                    'redirect_url' => $redirect_url, // Send correct redirect
                    'session_id' => session_id()
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Your account is deactivated. Please contact support.']);
            }
        } else {
            error_log("Login failed for username: $username - Invalid credentials");
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }

    } catch(PDOException $e) {
        error_log("Login database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Login failed: ' . $e->getMessage()]);
    }
}

function handleLogout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to home page
    header('Location: ../index.php');
    exit();
}
?>