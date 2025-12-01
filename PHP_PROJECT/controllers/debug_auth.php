<?php
// Debug script to identify and fix authentication issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Authentication System Debug</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Test 1: Check if database exists
echo "<h2>1. Database Connection Test</h2>";
$host = 'localhost';
$dbname = 'php_project';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✓ Database connection successful</div>";
} catch(PDOException $e) {
    echo "<div class='error'>✗ Database connection failed: " . $e->getMessage() . "</div>";
    echo "<div class='info'>Try creating the database first:</div>";
    echo "<pre>CREATE DATABASE php_project;</pre>";
    exit;
}

// Test 2: Check if users table exists
echo "<h2>2. Table Structure Check</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "<div class='error'>✗ Users table doesn't exist</div>";
        echo "<div class='info'>Creating users table...</div>";
        
        $createTable = "CREATE TABLE `users` (
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
        echo "<div class='success'>✓ Users table created successfully</div>";
    } else {
        echo "<div class='success'>✓ Users table exists</div>";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        echo "<div class='info'>Table structure:</div>";
        echo "<pre>";
        foreach($columns as $col) {
            echo $col['Field'] . " - " . $col['Type'] . "\n";
        }
        echo "</pre>";
    }
} catch(PDOException $e) {
    echo "<div class='error'>✗ Table check failed: " . $e->getMessage() . "</div>";
}

// Test 3: Test direct insertion
echo "<h2>3. Direct Database Insertion Test</h2>";
try {
    // Remove existing test user
    $stmt = $pdo->prepare("DELETE FROM users WHERE username = 'debug_test' OR email = 'debug@test.com'");
    $stmt->execute();
    
    // Insert test user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $result = $stmt->execute(['debug_test', 'debug@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Debug Test User', '1234567890']);
    
    if ($result) {
        echo "<div class='success'>✓ Direct insertion successful</div>";
        echo "<div class='info'>Test user created: debug_test / test123</div>";
    } else {
        echo "<div class='error'>✗ Direct insertion failed</div>";
    }
} catch(PDOException $e) {
    echo "<div class='error'>✗ Insertion error: " . $e->getMessage() . "</div>";
}

// Test 4: Test auth.php endpoint
echo "<h2>4. Testing auth.php Registration Endpoint</h2>";
echo "<div class='info'>Testing registration via HTTP request...</div>";

// Simulate POST data
$_POST = [
    'username' => 'http_test_user',
    'email' => 'http@test.com',
    'password' => 'test123',
    'confirm_password' => 'test123',
    'full_name' => 'HTTP Test User',
    'phone' => '9876543210'
];
$_GET['action'] = 'register';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Remove existing test user
try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE username = 'http_test_user' OR email = 'http@test.com'");
    $stmt->execute();
} catch(Exception $e) {}

// Capture output
ob_start();
include_once 'controllers/auth.php';
$output = ob_get_clean();

echo "<div class='info'>auth.php response:</div>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Check if user was created
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'http_test_user'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<div class='success'>✓ User created via auth.php</div>";
    } else {
        echo "<div class='error'>✗ User not created via auth.php</div>";
    }
} catch(Exception $e) {
    echo "<div class='error'>Error checking user: " . $e->getMessage() . "</div>";
}

// Test 5: Show all current users
echo "<h2>5. Current Users in Database</h2>";
try {
    $stmt = $pdo->query("SELECT id, username, email, full_name, created_at FROM users ORDER BY id DESC LIMIT 10");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Created</th></tr>";
        foreach($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "<td>" . $user['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No users found in database</div>";
    }
} catch(Exception $e) {
    echo "<div class='error'>Error fetching users: " . $e->getMessage() . "</div>";
}

// Test 6: Check file permissions and paths
echo "<h2>6. File System Check</h2>";
$files_to_check = [
    'includes/db.php',
    'controllers/auth.php',
    'auth/signup.php',
    'auth/signin.php'
];

foreach($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✓ $file exists</div>";
        if (is_readable($file)) {
            echo "<div class='success'>✓ $file is readable</div>";
        } else {
            echo "<div class='error'>✗ $file is not readable</div>";
        }
    } else {
        echo "<div class='error'>✗ $file does not exist</div>";
    }
}

echo "<h2>Debug Complete</h2>";
echo "<div class='info'>If you see any red errors above, those need to be fixed first.</div>";
?>

<script>
// Also test the frontend registration
console.log('Testing frontend registration...');

function testRegistration() {
    const formData = new FormData();
    formData.append('username', 'js_test_user');
    formData.append('email', 'js@test.com');
    formData.append('password', 'test123');
    formData.append('confirm_password', 'test123');
    formData.append('full_name', 'JavaScript Test User');
    formData.append('phone', '5555555555');
    
    fetch('controllers/auth.php?action=register', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        console.log('Registration response:', data);
        document.getElementById('jsTest').innerHTML = '<h3>Frontend Test Result:</h3><pre>' + data + '</pre>';
    })
    .catch(error => {
        console.error('Registration error:', error);
        document.getElementById('jsTest').innerHTML = '<h3>Frontend Test Error:</h3><pre>' + error + '</pre>';
    });
}

// Run test after page loads
setTimeout(testRegistration, 1000);
</script>

<div id="jsTest" style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-radius: 5px;">
    <p>Running JavaScript registration test...</p>
</div>