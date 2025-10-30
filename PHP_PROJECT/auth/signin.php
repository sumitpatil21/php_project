<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Lenskart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: #f8f9fa;
        }
        
        .auth-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .auth-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 28px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-primary:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .auth-links a {
            color: #007bff;
            text-decoration: none;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            text-align: center;
            display: none;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        
        .forgot-password {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .forgot-password a {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin: 10px 0;
        }
        
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="auth-container">
            <h2>Sign In to Your Account</h2>
            
            <form id="loginForm" method="POST">
                <div class="form-group">
                    <label for="username">Username or Email:</label>
                    <input type="text" id="username" name="username" required placeholder="Enter your username or email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <div class="forgot-password">
                    <a href="#" onclick="alert('Password reset feature coming soon!')">Forgot Password?</a>
                </div>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    Signing in...
                </div>
                
                <button type="submit" class="btn-primary" id="submitBtn">Sign In</button>
                
                <div id="loginMessage" class="message"></div>
            </form>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('loginMessage');
            const submitBtn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            
            // Basic validation
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                showMessage('error', 'Please enter both username and password');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            loading.style.display = 'block';
            messageDiv.style.display = 'none';
            
            fetch('../controllers/auth.php?action=login', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text(); // Get as text first to debug
            })
            .then(text => {
                console.log('Raw response:', text);
                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
                
                loading.style.display = 'none';
                submitBtn.disabled = false;
                
                if (data.success) {
                    showMessage('success', data.message);
                    console.log('Login successful, redirecting...');
                    
                    // Force redirect immediately
                    setTimeout(() => {
                        console.log('Attempting redirect to ../index.php');
                        window.location.href = '../index.php';
                    }, 500); // Reduced timeout for faster redirect
                } else {
                    showMessage('error', data.message);
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                submitBtn.disabled = false;
                console.error('Login error:', error);
                showMessage('error', 'An error occurred: ' + error.message);
            });
        });
        
        function showMessage(type, text) {
            const messageDiv = document.getElementById('loginMessage');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = text;
            messageDiv.style.display = 'block';
            
            // Auto hide after 5 seconds for error messages
            if (type === 'error') {
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000);
            }
        }
        
        // Check if we're coming from registration
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('registered') === 'success') {
                showMessage('success', 'Registration successful! Please sign in.');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            // Test if we can reach the auth controller
            console.log('Testing auth controller accessibility...');
            fetch('../controllers/auth.php?action=test', {
                method: 'GET'
            })
            .then(response => response.text())
            .then(data => {
                console.log('Auth controller test:', data);
            })
            .catch(error => {
                console.error('Auth controller not accessible:', error);
            });
        });
    </script>
</body>
</html>