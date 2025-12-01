<?php
// Enable full error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start the session to get a unique ID for the user's cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database Configuration ---
$host = 'localhost';
$dbname = 'php_project';
$username = 'root';
$password = '';

try {
    // --- Create PDO connection ---
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // This block runs if the database 'php_project' does not exist.
    if ($e->getCode() === 1049) { 
        try {
            // Connect to MySQL without specifying a database
            $temp_pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $temp_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create the database
            $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Reconnect to the newly created database
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // --- Create All Necessary Tables ---

            // 1. Users Table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT, `username` varchar(50) NOT NULL UNIQUE,
                `email` varchar(100) NOT NULL UNIQUE, `password` varchar(255) NOT NULL,
                'is_admin' tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;");

            // 2. Products Table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
                `id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `brand` varchar(100) NOT NULL,
                `price` decimal(10,2) NOT NULL, `image_url` varchar(500) NOT NULL, PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;");
            
            // 3. Cart Table (This was the missing part)
            $pdo->exec("CREATE TABLE IF NOT EXISTS `cart` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `session_id` varchar(255) NOT NULL COMMENT 'Stores the PHP session ID for guest carts',
                `product_id` int(11) NOT NULL,
                `quantity` int(11) NOT NULL DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `session_id_idx` (`session_id`),
                FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB;");
            
            // 4. Orders Table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT,
                `session_id` VARCHAR(255),
                `full_name` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(20) NOT NULL,
                `address_line1` TEXT NOT NULL,
                `city` VARCHAR(100) NOT NULL,
                `pincode` VARCHAR(10) NOT NULL,
                `items_json` TEXT NOT NULL,
                `total_amount` DECIMAL(10,2) NOT NULL,
                `order_status` VARCHAR(50) DEFAULT 'Pending',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB;");
            
            // Add user_id column to cart table if not exists
            $pdo->exec("ALTER TABLE `cart` ADD COLUMN IF NOT EXISTS `user_id` INT, ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");

            // --- Insert Dummy Products ---
            $stmt = $pdo->prepare("INSERT INTO `products` (`name`, `brand`, `price`, `image_url`) VALUES (?, ?, ?, ?)");
            $products_to_insert = [
                ['Vincent Chase Eyeglasses', 'Vincent Chase', 1500.00, 'https://static5.lenskart.com/media/catalog/product/pro/1/thumbnail/628x301/9df78eab33525d08d6e5fb8d27136e95//v/i/vincent-chase-vc-e13469-c3-eyeglasses_g_0339_02_02_22.jpg'],
                ['John Jacobs Eyeglasses', 'John Jacobs', 2200.00, 'https://static5.lenskart.com/media/catalog/product/pro/1/thumbnail/628x301/9df78eab33525d08d6e5fb8d27136e95//j/o/john-jacobs-jj-e13958-c1-eyeglasses_g_4671_02_02_22.jpg'],
            ];
            foreach ($products_to_insert as $product) { $stmt->execute($product); }

        } catch (PDOException $e2) {
            die("Fatal Error: Could not create the database and tables. " . $e2->getMessage());
        }
    } else {
        die("Fatal Error: Database connection failed. " . $e->getMessage());
    }
}
?>