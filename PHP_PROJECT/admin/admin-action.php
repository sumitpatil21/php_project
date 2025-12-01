<?php
// api/admin_action.php

session_start();
require_once __DIR__ . '/../includes/db.php'; // Path to your database connection
// Set header to return JSON
header('Content-Type: application/json');

// --- Helper Functions for JSON file ---
function readProductsFromJSON()
{
    $jsonPath = __DIR__ . '/../pages/db.json';
    if (!file_exists($jsonPath)) {
        throw new Exception("db.json file not found.");
    }
    $jsonData = file_get_contents($jsonPath);
    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error decoding db.json: " . json_last_error_msg());
    }
    // Ensure 'products' key exists and is an array
    if (!isset($data['products']) || !is_array($data['products'])) {
        $data['products'] = [];
    }
    return $data;
}

function writeProductsToJSON($data)
{
    $jsonPath = __DIR__ . '/../pages/db.json';
    // Ensure 'products' key exists
    if (!isset($data['products'])) {
        $data['products'] = [];
    }
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonData === false) {
        throw new Exception("Failed to encode data for db.json.");
    }
    if (file_put_contents($jsonPath, $jsonData) === false) {
        throw new Exception("Failed to write to db.json. Check file permissions.");
    }
    return true;
}
// --- End Helper Functions ---


// --- Basic Security Check: Ensure user is logged-in Admin ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in as an administrator.']);
    exit;
}

// --- Get Data Sent via JavaScript (Expecting JSON) ---
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid request format. Expected JSON.']);
    exit;
}

$action = $input['action'] ?? null;
$entity = $input['entity'] ?? null;
$userId = $input['user_id'] ?? null; // For user actions
$productId = $input['product_id'] ?? null; // For product actions

if (!$action || !$entity) {
    echo json_encode(['success' => false, 'message' => 'Missing action or entity.']);
    exit;
}


// --- Process Actions ---
try {
    // === USER ACTIONS (Database) ===
    if ($entity === 'user') {
        // user_id is required for all user actions except 'create'
        if ($action !== 'create_user' && !$userId) {
            throw new Exception("User ID is required for this action.");
        }

        switch ($action) {
            case 'create_user':
                $username = trim(string: $input['username'] ?? '');
                $email = trim(string: $input['email'] ?? '');
                $fullName = trim(string: $input['full_name'] ?? '');
                $phone = trim(string: $input['phone'] ?? '');
                $password = $input['password'] ?? '';
                if (empty($username) || empty($email) || empty($fullName) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
                    throw new Exception("Invalid data. Fill all required fields, ensure valid email, and password is 6+ chars.");
                }
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $checkStmt->execute([$username, $email]);
                if ($checkStmt->fetch()) {
                    throw new Exception("Username or email already exists.");
                }

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, email, password, full_name, phone, is_active, email_verified, is_admin) VALUES (?, ?, ?, ?, ?, 1, 0, 0)";
                $stmt = $pdo->prepare(query: $sql);
                $stmt->execute(params: [$username, $email, $hashed_password, $fullName, $phone]);
                echo json_encode(value: ['success' => true, 'message' => 'User created successfully. Page will reload.']);
                break;

            case 'get_user_details':
                $stmt = $pdo->prepare(query: "SELECT id, username, email, full_name, is_active FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    throw new Exception("User not found.");
                }
                break;

            case 'update_user':
                $fullName = trim(string: $input['full_name'] ?? '');
                $email = trim($input['email'] ?? '');
                $isActive = isset($input['is_active']) ? (int) $input['is_active'] : null;
                if (empty($fullName) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $isActive === null || !in_array($isActive, [0, 1])) {
                    throw new Exception("Invalid data. Check Name, Email, and Status.");
                }
                $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkEmailStmt->execute([$email, $userId]);
                if ($checkEmailStmt->fetch()) {
                    throw new Exception("Email is already in use by another account.");
                }
                $updateStmt = $pdo->prepare(query: "UPDATE users SET full_name = ?, email = ?, is_active = ? WHERE id = ?");
                $success = $updateStmt->execute(params: [$fullName, $email, $isActive, $userId]);
                if ($success) {
                    echo json_encode(value: ['success' => true, 'message' => 'User updated successfully.']);
                } else {
                    throw new Exception(message: "Database update failed.");
                }
                break;

            case 'toggle_user_status':
                $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $currentStatus = $stmt->fetchColumn();
                if ($currentStatus === false)
                    throw new Exception(message: "User not found.");
                $newStatus = ($currentStatus == 1) ? 0 : 1;
                $updateStmt = $pdo->prepare(query: "UPDATE users SET is_active = ? WHERE id = ?");
                $success = $updateStmt->execute(params: [$newStatus, $userId]);
                if ($success) {
                    echo json_encode(value: ['success' => true, 'message' => 'User status updated.', 'newStatus' => $newStatus, 'newStatusText' => ($newStatus == 1 ? 'Active' : 'Inactive')]);
                } else {
                    throw new Exception(message: "Failed to update user status.");
                }
                break;

            case 'delete_user':
                $stmt = $pdo->prepare(query: "SELECT email FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $userEmail = $stmt->fetchColumn();
                if (defined(constant_name: 'ADMIN_EMAIL') && $userEmail === ADMIN_EMAIL) {
                    echo json_encode(['success' => false, 'message' => 'Error: Cannot delete the primary administrator account.']);
                    exit;
                }
                $deleteStmt = $pdo->prepare(query: "DELETE FROM users WHERE id = ?");
                $success = $deleteStmt->execute(params: [$userId]);
                if ($success && $deleteStmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
                } else {
                    throw new Exception(message: "Failed to delete user or user not found.");
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid user action.']);
                break;
        }
    }
    // === PRODUCT ACTIONS (JSON File) ===
    elseif ($entity === 'product') {
        // product_id is required for all product actions, but handled differently for create
        if ($action !== 'create_product' && !$productId) {
            throw new Exception("Product ID is required for this action.");
        }

        $data = readProductsFromJSON();
        $products = &$data['products']; // Get a reference to the products array

        switch ($action) {
            case 'create_product':
                $newProduct = [
                    'id' => trim($input['id'] ?? ''),
                    'brand' => trim(string: $input['brand'] ?? ''),
                    'price' => (float) ($input['price'] ?? 0),
                    'imageUrl' => trim($input['imageUrl'] ?? ''),
                    // Add optional fields
                    'frame_type' => trim($input['frame_type'] ?? ''),
                    'frame_shape' => trim($input['frame_shape'] ?? '')
                    // Add any other fields from your modal form here...
                ];
                // Clean up empty optional fields
                $newProduct = array_filter($newProduct, function ($value) {
                    return $value !== ''; });

                // Validation for create
                if (empty($newProduct['id']) || empty($newProduct['brand']) || empty($newProduct['price']) || empty($newProduct['imageUrl'])) {
                    throw new Exception("Product ID, Brand, Price, and Image URL are required.");
                }

                // Check if ID already exists
                foreach ($products as $p) {
                    if ($p['id'] === $newProduct['id']) {
                        throw new Exception("Product ID '{$newProduct['id']}' already exists.");
                    }
                }

                $products[] = $newProduct; // Add to array
                writeProductsToJSON($data);
                echo json_encode(['success' => true, 'message' => 'Product created successfully. Page will reload.']);
                break;

            case 'get_product_details':
                $foundProduct = null;
                foreach ($products as $p) {
                    if ($p['id'] === $productId) {
                        $foundProduct = $p;
                        break;
                    }
                }
                if ($foundProduct) {
                    echo json_encode(['success' => true, 'product' => $foundProduct]);
                } else {
                    throw new Exception("Product not found.");
                }
                break;

            case 'update_product':
                $productIndex = -1;
                $originalProductId = $input['original_id'] ?? $productId; // Get original ID

                foreach ($products as $index => $p) {
                    if ($p['id'] === $originalProductId) {
                        $productIndex = $index;
                        break;
                    }
                }
                if ($productIndex === -1) {
                    throw new Exception("Product not found.");
                }

                // Get updated data from form
                $updatedProduct = [
                    'id' => trim(string: $input['id'] ?? ''),
                    'brand' => trim($input['brand'] ?? ''),
                    'price' => (float) ($input['price'] ?? 0),
                    'imageUrl' => trim(string: $input['imageUrl'] ?? ''),
                    'frame_type' => trim(string: $input['frame_type'] ?? ''),
                    'frame_shape' => trim($input['frame_shape'] ?? '')
                    // Add other fields...
                ];

                // Validation for update
                if (empty($updatedProduct['id']) || empty($updatedProduct['brand']) || empty($updatedProduct['price']) || empty($updatedProduct['imageUrl'])) {
                    throw new Exception("Product ID, Brand, Price, and Image URL are required.");
                }

                // Check if new ID is unique (if it was changed)
                if ($originalProductId !== $updatedProduct['id']) {
                    foreach ($products as $p) {
                        if ($p['id'] === $updatedProduct['id']) {
                            throw new Exception("New Product ID '{$updatedProduct['id']}' already exists.");
                        }
                    }
                }

                // Merge old data with new data to preserve fields not in the edit form
                $products[$productIndex] = array_merge($products[$productIndex], $updatedProduct);

                writeProductsToJSON(data: $data);
                echo json_encode(value: ['success' => true, 'message' => 'Product updated successfully.']);
                break;

            case 'delete_product':
                $originalCount = count(value: $products);
                $products = array_filter($products, function ($p) use ($productId) {
                    return $p['id'] !== $productId; });
                $data['products'] = array_values(array: $products); // Re-index array

                if (count(value: $data['products']) === $originalCount) {
                    throw new Exception("Product not found.");
                }

                writeProductsToJSON(data: $data);
                echo json_encode(value: ['success' => true, 'message' => 'Product deleted successfully.']);
                break;

            default:
                echo json_encode(value: ['success' => false, 'message' => 'Invalid product action.']);
                break;
        }
    }
    // === ORDER ACTIONS (Database) ===
    elseif ($entity === 'order') {
        $orderId = $input['order_id'] ?? null;
        
        if ($action !== 'get_order_details' && !$orderId) {
            throw new Exception("Order ID is required for this action.");
        }

        switch ($action) {
            case 'get_order_details':
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($order) {
                    echo json_encode(['success' => true, 'order' => $order]);
                } else {
                    throw new Exception("Order not found.");
                }
                break;

            case 'update_order_status':
                $orderStatus = trim($input['order_status'] ?? '');
                $validStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
                
                if (!in_array($orderStatus, $validStatuses)) {
                    throw new Exception("Invalid order status. Valid statuses: " . implode(', ', $validStatuses));
                }
                
                $updateStmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
                $success = $updateStmt->execute([$orderStatus, $orderId]);
                
                if ($success && $updateStmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Order status updated successfully.']);
                } else {
                    throw new Exception("Failed to update order status or order not found.");
                }
                break;

            case 'update_order_details':
                $fullName = trim($input['full_name'] ?? '');
                $phone = trim($input['phone'] ?? '');
                $addressLine1 = trim($input['address_line1'] ?? '');
                $city = trim($input['city'] ?? '');
                $pincode = trim($input['pincode'] ?? '');
                $orderStatus = trim($input['order_status'] ?? '');
                
                $validStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
                
                if (empty($fullName) || empty($phone) || empty($addressLine1) || empty($city) || empty($pincode)) {
                    throw new Exception("All shipping details are required.");
                }
                
                if (!in_array($orderStatus, $validStatuses)) {
                    throw new Exception("Invalid order status.");
                }
                
                $updateStmt = $pdo->prepare(
                    "UPDATE orders SET full_name = ?, phone = ?, address_line1 = ?, city = ?, pincode = ?, order_status = ? WHERE id = ?"
                );
                $success = $updateStmt->execute([$fullName, $phone, $addressLine1, $city, $pincode, $orderStatus, $orderId]);
                
                if ($success && $updateStmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Order details updated successfully.']);
                } else {
                    throw new Exception("Failed to update order details or order not found.");
                }
                break;

            case 'delete_order':
                $deleteStmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $success = $deleteStmt->execute([$orderId]);
                
                if ($success && $deleteStmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Order deleted successfully.']);
                } else {
                    throw new Exception("Failed to delete order or order not found.");
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid order action.']);
                break;
        }
    } else {
        echo json_encode(value: ['success' => false, 'message' => 'Invalid entity specified.']);
    }

} catch (PDOException $e) {
    error_log(message: "Admin Action DB Error: " . $e->getMessage() . " Input: " . json_encode(value: $input));
    if ($e->getCode() == '23000') {
        echo json_encode(value: ['success' => false, 'message' => 'Database error: That email or username is already taken.']);
    } else {
        echo json_encode(value: ['success' => false, 'message' => 'A database error occurred. Please check logs.']);
    }
} catch (Exception $e) {
    error_log(message: "Admin Action General Error: " . $e->getMessage() . " Input: " . json_encode(value: $input));
    echo json_encode(value: ['success' => false, 'message' => $e->getMessage()]);
}

exit;
?>