<?php
session_start();

// --- 1. ADMIN SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../auth/signin.php'); // Redirect non-admins
    exit;
}

// --- 2. INCLUDE FILES ---
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/product.php'; // For getProducts()

// --- 3. DATA FETCHING ---
$stats = [ 'totalUsers' => 0, 'totalOrders' => 0, 'totalProducts' => 0, 'revenue' => 0];
$users_from_db = []; $orders_from_db = []; $db_error = null; $all_products = [];

try {
    // Fetch DB Stats
    $stats['totalUsers'] = $pdo->query(query: "SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['totalOrders'] = $pdo->query(query: "SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['revenue'] = $pdo->query(query: "SELECT SUM(total_amount) FROM orders WHERE payment_status = 'Paid'")->fetchColumn() ?? 0;
    
    // Fetch DB Data
    $user_stmt = $pdo->query(query: "SELECT id, username, email, full_name, is_active, is_admin FROM users ORDER BY created_at DESC");
    $users_from_db = $user_stmt->fetchAll();
    $order_stmt = $pdo->query(query: "SELECT id, user_id, full_name, total_amount, order_status, created_at, items_json FROM orders ORDER BY created_at DESC LIMIT 50");
    $orders_from_db = $order_stmt->fetchAll();

    // Fetch JSON Data (or DB data if migrated)
    $all_products = getProducts(); 
    $stats['totalProducts'] = count(value: $all_products);

} catch (PDOException $e) { $db_error = "Database Error: " . $e->getMessage(); error_log($db_error); }
  catch (Exception $e) { $db_error = "File Error: " . $e->getMessage(); error_log($db_error); }

// --- 4. STATE: Get active tab ---
$activeTab = $_GET['tab'] ?? 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .action-dropdown { position: absolute; right: 0; margin-top: 0.5rem; z-index: 10; }
        .modal-overlay { position: fixed; inset: 0; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; }
        .modal-content { position: relative; background: white; padding: 2rem; border-radius: 0.5rem; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-close-btn { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.875rem; color: #374151; }
        .form-input, .form-select { display: block; width: 100%; border: 1px solid #d1d5db; border-radius: 0.375rem; padding: 0.5rem 0.75rem; box-shadow: sm; }
        .form-input:focus, .form-select:focus { outline: none; ring: 2px; ring-offset: 1px; border-color: #3b82f6; ring-color: #3b82f6; }
        .modal-actions { margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid #e5e7eb; padding-top: 1rem; }
    </style>
</head>
<body class="bg-gray-100">

    <div class="w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
      <div class="mb-6 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="/PHP_PROJECT/index.php" class="text-gray-600 hover:text-gray-800 transition-colors" title="Back to Home">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2"> Admin Dashboard </h1>
                <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>!</p>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-2"> Admin Access </span>
            </div>
        </div>
         <div> <a href="../controllers/auth.php?action=logout" class="text-sm text-red-600 hover:text-red-800 font-medium">Logout</a> </div>
      </div>

       <div id="apiAlert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 hidden" role="alert"></div>
       <?php if (isset($db_error)): ?> <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert"> <strong class="font-bold">Error!</strong> <span class="block sm:inline"><?php echo htmlspecialchars($db_error); ?></span> </div> <?php endif; ?>

      <!-- Realtime Stats Dashboard -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
         <div class="bg-white rounded-lg border border-gray-200 shadow-sm"> 
            <div class="p-4 pb-2"> 
               <h3 class="text-sm font-medium text-gray-600">Total Users</h3> 
               <div class="flex items-center mt-1">
                  <span class="text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">Live</span>
               </div>
            </div> 
            <div class="px-4 pb-4"> 
               <div class="text-2xl font-bold" id="totalUsers"><?php echo number_format($stats['totalUsers']); ?></div> 
            </div> 
         </div>
         <div class="bg-white rounded-lg border border-gray-200 shadow-sm"> 
            <div class="p-4 pb-2"> 
               <h3 class="text-sm font-medium text-gray-600">Total Orders</h3> 
               <div class="flex items-center mt-1">
                  <span class="text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">Live</span>
               </div>
            </div> 
            <div class="px-4 pb-4"> 
               <div class="text-2xl font-bold" id="totalOrders"><?php echo number_format($stats['totalOrders']); ?></div> 
            </div> 
         </div>
         <div class="bg-white rounded-lg border border-gray-200 shadow-sm"> 
            <div class="p-4 pb-2"> 
               <h3 class="text-sm font-medium text-gray-600">Products</h3> 
               <div class="flex items-center mt-1">
                  <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">Static</span>
               </div>
            </div> 
            <div class="px-4 pb-4"> 
               <div class="text-2xl font-bold" id="totalProducts"><?php echo number_format($stats['totalProducts']); ?></div> 
            </div> 
         </div>
         <div class="bg-white rounded-lg border border-gray-200 shadow-sm"> 
            <div class="p-4 pb-2"> 
               <h3 class="text-sm font-medium text-gray-600">Revenue (Paid)</h3> 
               <div class="flex items-center mt-1">
                  <span class="text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">Live</span>
               </div>
            </div> 
            <div class="px-4 pb-4"> 
               <div class="text-2xl font-bold" id="totalRevenue">₹<?php echo number_format($stats['revenue']); ?></div> 
            </div> 
         </div>
      </div>
      
      <!-- Realtime Activity Feed -->
      <div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-8">
         <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
            <p class="text-sm text-gray-600 mt-1">Live updates from your store</p>
         </div>
         <div class="p-6">
            <div id="activityFeed" class="space-y-3">
               <div class="text-center text-gray-500 py-4">Loading recent activities...</div>
            </div>
         </div>
      </div>

      <div class="w-full">
        <div class="grid w-full grid-cols-1 sm:grid-cols-4 mb-6 bg-gray-200 rounded-lg p-1">
          <a href="?tab=users" class="px-4 py-2 text-sm font-medium rounded-md transition-colors text-center <?php echo ($activeTab === 'users' || $activeTab === 'add_user') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>"> Users </a>
          <a href="?tab=orders" class="px-4 py-2 text-sm font-medium rounded-md transition-colors text-center <?php echo ($activeTab === 'orders') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>"> Orders </a>
          <a href="?tab=products" class="px-4 py-2 text-sm font-medium rounded-md transition-colors text-center <?php echo ($activeTab === 'products') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>"> Products </a>
          <a href="?tab=settings" class="px-4 py-2 text-sm font-medium rounded-md transition-colors text-center <?php echo ($activeTab === 'settings') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>"> Settings </a>
        </div>

        <?php if ($activeTab === 'users'): ?>
          <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
             <div class="p-6 border-b border-gray-200"> <h2 class="text-lg font-semibold text-gray-900"> User Management </h2> <p class="text-sm text-gray-600 mt-1"> Manage user accounts and permissions </p> </div>
            <div class="p-6">
              <div class="space-y-4">
                 <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"> <div> <h3 class="font-medium">All Users</h3> <p class="text-sm text-gray-600"> Manage user accounts and status </p> </div> <a href="?tab=add_user" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"> <i class="fas fa-plus mr-2"></i> Add New User </a> </div>
                <div class="border rounded-lg overflow-x-auto">
                  <table class="w-full min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"> <tr> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Name </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Email </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Status </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12 relative"> Actions </th> </tr> </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <?php foreach ($users_from_db as $user): ?>
                        <tr id="user-row-<?php echo $user['id']; ?>" class="hover:bg-gray-50">
                          <td data-field="name" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"> <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?> </td>
                          <td data-field="email" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"> <?php echo htmlspecialchars($user['email']); ?> </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <?php $isActive = ($user['is_active'] == 1); $statusText = $isActive ? 'Active' : 'Inactive'; $statusClass = $isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>
                            <span data-status-badge class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>"> <?php echo $statusText; ?> </span>
                          </td>
                           <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium relative">
                                <?php if (isset($user['is_admin']) && $user['is_admin'] == 1): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"> <i class="fas fa-user-shield mr-1"></i> Admin </span>
                                <?php else: ?>
                                    <button onclick="toggleDropdown('user-<?php echo $user['id']; ?>')" class="p-1 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"> <i class="fas fa-ellipsis-v text-gray-500"></i> </button>
                                    <div id="dropdown-user-<?php echo $user['id']; ?>" class="action-dropdown w-48 bg-white rounded-md shadow-lg border border-gray-200 hidden">
                                        <div class="py-1" role="menu">
                                            <button onclick="openEditModal('user', <?php echo $user['id']; ?>)" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem"> <i class="fas fa-edit w-4 h-4 mr-2 text-gray-500"></i> Edit </button>
                                            <button onclick="handleAction('user', <?php echo $user['id']; ?>, 'toggle_user_status', this)" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem"> <i class="fas fa-toggle-<?php echo $isActive ? 'on text-green-500' : 'off text-red-500'; ?> w-4 h-4 mr-2"></i> <span data-status-text><?php echo $isActive ? 'Deactivate' : 'Activate'; ?></span> </button>
                                            <button onclick="handleAction('user', <?php echo $user['id']; ?>, 'delete_user', this)" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-gray-100" role="menuitem"> <i class="fas fa-trash-alt w-4 h-4 mr-2"></i> Delete </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                      <?php endforeach; ?>
                      <?php if (empty($users_from_db)): ?> <tr><td colspan="4" class="text-center py-4 text-gray-500">No users found.</td></tr> <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
        
        <?php if ($activeTab === 'add_user'): ?>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6 border-b border-gray-200"> <h2 class="text-lg font-semibold text-gray-900"> Add New User </h2> <p class="text-sm text-gray-600 mt-1"> Create a new user account </p> </div>
                <div class="p-6">
                    <form id="addUserForm" onsubmit="submitAddUser(event)" class="space-y-6 max-w-lg">
                        <div class="space-y-2"> <label for="addUsername" class="form-label">Username</label> <input id="addUsername" name="username" type="text" required class="form-input"/> </div>
                        <div class="space-y-2"> <label for="addFullName" class="form-label">Full Name</label> <input id="addFullName" name="full_name" type="text" required class="form-input"/> </div>
                        <div class="space-y-2"> <label for="addEmail" class="form-label">Email</label> <input id="addEmail" name="email" type="email" required class="form-input"/> </div>
                        <div class="space-y-2"> <label for="addPhone" class="form-label">Phone (Optional)</label> <input id="addPhone" name="phone" type="tel" class="form-input"/> </div>
                        <div class="space-y-2"> <label for="addPassword" class="form-label">Password</label> <input id="addPassword" name="password" type="password" required minlength="6" class="form-input"/> </div>
                        <div class="flex gap-4 pt-4 border-t mt-6">
                            <button type="submit" id="addUserButton" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"> <i class="fas fa-plus mr-2"></i> Create User </button>
                            <a href="?tab=users" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors"> Cancel </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'orders'): ?>
             <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                 <div class="p-6 border-b border-gray-200"> <h2 class="text-lg font-semibold text-gray-900"> Order Management </h2> <p class="text-sm text-gray-600 mt-1"> View and manage customer orders </p> </div>
                <div class="p-6">
                  <div class="space-y-4">
                     <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"> <div> <h3 class="font-medium">Recent Orders</h3> <p class="text-sm text-gray-600"> Latest customer orders </p> </div> <div class="flex gap-2"> <a href="order_management.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"> <i class="fas fa-cog mr-2"></i> Advanced Order Management </a> <button class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors"> <i class="fas fa-file-export mr-2"></i> Export Orders </button> </div> </div>
                    <div class="border rounded-lg overflow-x-auto">
                      <table class="w-full min-w-full divide-y divide-gray-200">
                         <thead class="bg-gray-50"> <tr> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Order ID </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Customer </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Items </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Total </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Status </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"> Date </th> <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12 relative"> Actions </th> </tr> </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
                          <?php foreach ($orders_from_db as $order): ?>
                            <tr id="order-row-<?php echo $order['id']; ?>" class="hover:bg-gray-50">
                              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600 hover:underline">
                                <a href="#" onclick="openEditModal('order', <?php echo $order['id']; ?>)"><?php echo htmlspecialchars($order['id']); ?></a>
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"> <?php echo htmlspecialchars($order['full_name']); ?> </td>
                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"> <?php $items = json_decode($order['items_json'] ?? '[]', true); echo is_array($items) ? count($items) . ' item(s)' : 'N/A'; ?> </td>
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"> ₹<?php echo number_format($order['total_amount']); ?> </td>
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-status-badge> 
                                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                  <?php 
                                  $status = $order['order_status'];
                                  if ($status == 'Delivered') echo 'bg-green-100 text-green-800';
                                  elseif ($status == 'Shipped') echo 'bg-blue-100 text-blue-800';
                                  elseif ($status == 'Processing') echo 'bg-yellow-100 text-yellow-800';
                                  elseif ($status == 'Cancelled') echo 'bg-red-100 text-red-800';
                                  else echo 'bg-gray-100 text-gray-800';
                                  ?>">
                                  <?php echo htmlspecialchars($order['order_status']); ?>
                                  </span>
                              </td>
                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"> <?php echo date("M d, Y", strtotime($order['created_at'])); ?> </td>
                              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium relative">
                                   <button onclick="toggleDropdown('order-<?php echo $order['id']; ?>')" class="p-1 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"> <i class="fas fa-ellipsis-v text-gray-500"></i> </button>
                                   <div id="dropdown-order-<?php echo $order['id']; ?>" class="action-dropdown w-56 bg-white rounded-md shadow-lg border border-gray-200 hidden">
                                     <div class="py-1" role="menu" aria-orientation="vertical">
                                         <button onclick="openEditModal('order', <?php echo $order['id']; ?>)" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem"> <i class="fas fa-edit w-4 h-4 mr-2 text-gray-500"></i> Edit Order Details </button>
                                         <div class="border-t my-1"></div>
                                         <div class="px-3 py-1 text-xs font-medium text-gray-500 uppercase tracking-wider">Quick Status Update</div>
                                         <?php 
                                         $statuses = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];
                                         foreach ($statuses as $status): 
                                             if ($status !== $order['order_status']): ?>
                                         <button onclick="quickUpdateStatus(<?php echo $order['id']; ?>, '<?php echo $status; ?>', this)" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
                                             <i class="fas fa-<?php echo $status === 'Processing' ? 'clock' : ($status === 'Shipped' ? 'truck' : ($status === 'Delivered' ? 'check-circle' : 'times-circle')); ?> w-4 h-4 mr-2 text-gray-500"></i> 
                                             Mark as <?php echo $status; ?>
                                         </button>
                                         <?php endif; endforeach; ?>
                                         <div class="border-t my-1"></div>
                                         <button onclick="handleAction('order', <?php echo $order['id']; ?>, 'delete_order', this)" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-gray-100" role="menuitem"> <i class="fas fa-trash-alt w-4 h-4 mr-2"></i> Delete Order </button>
                                     </div>
                                   </div>
                               </td>
                            </tr>
                          <?php endforeach; ?>
                           <?php if (empty($orders_from_db)): ?> <tr><td colspan="7" class="text-center py-4 text-gray-500">No orders found.</td></tr> <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
             </div>
        <?php endif; ?>

        <?php if ($activeTab === 'products'): ?>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6 border-b border-gray-200"> <h2 class="text-lg font-semibold text-gray-900"> Product Management (db.json) </h2> <p class="text-sm text-gray-600 mt-1"> Add, edit, and delete products from <code>db.json</code> </p> </div>
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div> <h3 class="font-medium">All Products</h3> <p class="text-sm text-gray-600"> Products from <code>db.json</code> </p> </div>
                        <button onclick="openEditModal('product', null)" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"> <i class="fas fa-plus mr-2"></i> Add New Product </button>
                    </div>
                    <div class="border rounded-lg overflow-x-auto">
                        <table class="w-full min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12 relative">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($all_products as $product): 
                                    $safe_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $product['id']);
                                ?>
                                    <tr id="product-row-<?php echo $safe_id; ?>" class="hover:bg-gray-50">
                                        <td data-field="id" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['id']); ?></td>
                                        <td data-field="brand" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($product['brand']); ?></td>
                                        <td data-field="price" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₹<?php echo number_format($product['price']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium relative">
                                            <button onclick="toggleDropdown('product-<?php echo $safe_id; ?>')" class="p-1 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"> <i class="fas fa-ellipsis-v text-gray-500"></i> </button>
                                            <div id="dropdown-product-<?php echo $safe_id; ?>" class="action-dropdown w-48 bg-white rounded-md shadow-lg border border-gray-200 hidden">
                                                <div class="py-1" role="menu">
                                                    <button onclick="openEditModal('product', '<?php echo htmlspecialchars($product['id']); ?>')" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem"> <i class="fas fa-edit w-4 h-4 mr-2 text-gray-500"></i> Edit </button>
                                                    <button onclick="handleAction('product', '<?php echo htmlspecialchars($product['id']); ?>', 'delete_product', this)" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-gray-100" role="menuitem"> <i class="fas fa-trash-alt w-4 h-4 mr-2"></i> Delete </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($all_products)): ?> <tr><td colspan="4" class="text-center py-4 text-gray-500">No products found in db.json.</td></tr> <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
<?php if ($activeTab === 'settings'): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
        <div class="md:col-span-1">
            <h2 class="text-lg font-semibold text-gray-900">System Settings</h2>
            <p class="text-sm text-gray-600 mt-1">
                Manage your site's core configuration, security, and payment gateways.
            </p>
        </div>

        <div class="md:col-span-2 space-y-6">

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-6">
                    <h3 class="text-base font-semibold leading-6 text-gray-900">General</h3>
                    <p class="mt-1 text-sm text-gray-500">Update your site's main settings.</p>
                </div>
                <ul class="divide-y divide-gray-200">
                    <li class="flex items-center justify-between py-4 px-6">
                        <div>
                            <span class="text-sm font-medium text-gray-900">Site Maintenance Mode</span>
                            <p class="text-xs text-gray-500">Temporarily take the site offline for visitors.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" value="" class="sr-only peer" id="maintenanceToggle" onchange="handleToggle('maintenance')">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </li>
                    <li class="flex items-center justify-between py-4 px-6">
                        <div>
                            <span class="text-sm font-medium text-gray-900">Email Notifications</span>
                            <p class="text-xs text-gray-500">Configure admin and user email settings.</p>
                        </div>
                        <button class="inline-flex items-center px-3 py-1 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors">
                            Configure
                        </button>
                    </li>
                </ul>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6">
                    <h3 class="text-base font-semibold leading-6 text-gray-900">Payment Gateway</h3>
                    <p class="mt-1 text-sm text-gray-500">Manage API keys for payment processing.</p>
                    <form class="mt-6 space-y-4">
                        <div>
                            <label for="razorpay_key" class="form-label">Razorpay Key ID</label>
                            <input type="text" id="razorpay_key" name="razorpay_key" value="rzp_test_..." class="form-input">
                        </div>
                        <div>
                            <label for="razorpay_secret" class="form-label">Razorpay Key Secret</label>
                            <input type="password" id="razorpay_secret" name="razorpay_secret" value="************" class="form-input">
                        </div>
                        <div class="text-right pt-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Keys
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6">
                    <h3 class="text-base font-semibold leading-6 text-gray-900">Security</h3>
                    <div class="flex items-center justify-between mt-4">
                         <div>
                             <span class="text-sm font-medium text-gray-900">Admin Password</span>
                             <p class="text-xs text-gray-500">Change the password for your admin account.</p>
                         </div>
                         <button class="inline-flex items-center px-3 py-1 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors">
                           Change Password
                         </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
<?php endif; ?>
      </div>
    </div>

    <div id="editUserModal" class="modal-overlay hidden">
        <div class="modal-content relative">
             <button onclick="closeEditModal('user')" class="modal-close-btn" title="Close"><i class="fas fa-times"></i></button>
             <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit User</h3>
             <form id="editUserForm" onsubmit="submitEditForm('user', event)">
                 <input type="hidden" id="editUserId" name="user_id">
                 <div class="space-y-4">
                     <div> <label for="editUserFullName" class="form-label">Full Name</label> <input type="text" id="editUserFullName" name="full_name" required class="form-input"> </div>
                     <div> <label for="editUserEmail" class="form-label">Email</label> <input type="email" id="editUserEmail" name="email" required class="form-input"> </div>
                      <div>
                         <label class="form-label">Status</label>
                         <div class="flex items-center space-x-4 mt-1">
                             <label class="inline-flex items-center"> <input type="radio" id="editUserStatusActive" name="is_active" value="1" class="form-radio h-4 w-4 text-blue-600"> <span class="ml-2 text-sm text-gray-700">Active</span> </label>
                             <label class="inline-flex items-center"> <input type="radio" id="editUserStatusInactive" name="is_active" value="0" class="form-radio h-4 w-4 text-red-600"> <span class="ml-2 text-sm text-gray-700">Inactive</span> </label>
                         </div>
                     </div>
                 </div>
                 <div class="modal-actions">
                     <button type="button" onclick="closeEditModal('user')" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:text-sm">Cancel</button>
                     <button type="submit" id="editUserSaveButton" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:text-sm">Save Changes</button>
                 </div>
                 <div id="editUserError" class="text-red-600 text-sm mt-2 hidden"></div>
             </form>
        </div>
    </div>

    <div id="editProductModal" class="modal-overlay hidden">
         <div class="modal-content relative">
             <button onclick="closeEditModal('product')" class="modal-close-btn" title="Close"><i class="fas fa-times"></i></button>
             <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4" id="editProductModalTitle">Add/Edit Product</h3>
             <form id="editProductForm" onsubmit="submitEditForm('product', event)">
                 <input type="hidden" id="editProductAction" name="action">
                 <input type="hidden" id="editProductOriginalId" name="original_id"> <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div> <label for="editProductId" class="form-label">Product ID (Unique)</label> <input type="text" id="editProductId" name="id" required class="form-input"> </div>
                     <div> <label for="editProductBrand" class="form-label">Brand</label> <input type="text" id="editProductBrand" name="brand" required class="form-input"> </div>
                     <div> <label for="editProductPrice" class="form-label">Price</label> <input type="number" step="0.01" id="editProductPrice" name="price" required class="form-input"> </div>
                     <div> <label for="editProductImageUrl" class="form-label">Image URL 1</label> <input type="text" id="editProductImageUrl" name="imageUrl" required class="form-input"> </div>
                     <div> <label for="editProductImageUrl2" class="form-label">Image URL 2 (Optional)</label> <input type="text" id="editProductImageUrl2" name="imageUrl2" class="form-input"> </div>
                     <div> <label for="editProductRating" class="form-label">Rating (Optional)</label> <input type="text" id="editProductRating" name="rating" class="form-input"> </div>
                     <div> <label for="editProductReviews" class="form-label">Reviews (Optional)</label> <input type="text" id="editProductReviews" name="reviews" class="form-input"> </div>
                     <div> <label for="editProductSize" class="form-label">Size (Optional)</label> <input type="text" id="editProductSize" name="sizeCollection" class="form-input"> </div>
                     <div> <label for="editProductFrameType" class="form-label">Frame Type (Optional)</label> <input type="text" id="editProductFrameType" name="frame_type" class="form-input"> </div>
                     <div> <label for="editProductFrameShape" class="form-label">Frame Shape (Optional)</label> <input type="text" id="editProductFrameShape" name="frame_shape" class="form-input"> </div>
                     <div> <label for="editProductAge" class="form-label">Age Group (Optional)</label> <input type="text" id="editProductAge" name="age_group" class="form-input"> </div>
                     <div> <label for="editProductGender" class="form-label">Gender (Optional)</label> <input type="text" id="editProductGender" name="gender" class="form-input"> </div>
                 </div>
                 <div class="modal-actions">
                     <button type="button" onclick="closeEditModal('product')" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:text-sm">Cancel</button>
                     <button type="submit" id="editProductSaveButton" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:text-sm">Save Changes</button>
                 </div>
                 <div id="editProductError" class="text-red-600 text-sm mt-2 hidden"></div>
             </form>
        </div>
    </div>
    
    <div id="editOrderModal" class="modal-overlay hidden">
         <div class="modal-content relative" style="max-width: 700px;">
             <button onclick="closeEditModal('order')" class="modal-close-btn" title="Close"><i class="fas fa-times"></i></button>
             <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4" id="editOrderModalTitle">Order Details</h3>
             
             <div id="orderDetailsContent" class="space-y-4 mb-6">
                 <!-- Order items will be displayed here -->
             </div>
             
             <form id="editOrderForm" onsubmit="submitEditForm('order', event)" class="space-y-4">
                 <input type="hidden" id="editOrderId" name="order_id">
                 
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div>
                         <label for="editOrderFullName" class="form-label">Full Name</label>
                         <input type="text" id="editOrderFullName" name="full_name" required class="form-input">
                     </div>
                     <div>
                         <label for="editOrderPhone" class="form-label">Phone</label>
                         <input type="tel" id="editOrderPhone" name="phone" required class="form-input">
                     </div>
                     <div class="md:col-span-2">
                         <label for="editOrderAddress" class="form-label">Address</label>
                         <input type="text" id="editOrderAddress" name="address_line1" required class="form-input">
                     </div>
                     <div>
                         <label for="editOrderCity" class="form-label">City</label>
                         <input type="text" id="editOrderCity" name="city" required class="form-input">
                     </div>
                     <div>
                         <label for="editOrderPincode" class="form-label">Pincode</label>
                         <input type="text" id="editOrderPincode" name="pincode" required class="form-input">
                     </div>
                     <div class="md:col-span-2">
                         <label for="editOrderStatus" class="form-label">Order Status</label>
                         <select id="editOrderStatus" name="order_status" class="form-select">
                             <option value="Pending">Pending</option>
                             <option value="Processing">Processing</option>
                             <option value="Shipped">Shipped</option>
                             <option value="Delivered">Delivered</option>
                             <option value="Cancelled">Cancelled</option>
                         </select>
                     </div>
                 </div>
                 
                 <div class="modal-actions">
                     <button type="button" onclick="closeEditModal('order')" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:text-sm">Cancel</button>
                     <button type="submit" id="editOrderSaveButton" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:text-sm">Update Order</button>
                 </div>
                 <div id="editOrderError" class="text-red-600 text-sm mt-2 hidden"></div>
             </form>
        </div>
    </div>
    <script>
        let openDropdownId = null;
        const apiUrl = 'admin-action.php'; // Corrected path
        const apiAlert = document.getElementById('apiAlert');

        function toggleDropdown(id) {
            if (openDropdownId && openDropdownId !== id) {
                document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden');
            }
            const dropdown = document.getElementById('dropdown-' + id);
            if (dropdown) {
                dropdown.classList.toggle('hidden');
                openDropdownId = dropdown.classList.contains('hidden') ? null : id;
            }
        }
        window.addEventListener('click', function(e) {
            const dropdownButton = e.target.closest('button[onclick^="toggleDropdown"]');
            const dropdownMenu = e.target.closest('.action-dropdown');
            if (openDropdownId && !dropdownButton && !dropdownMenu) {
                document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden');
                openDropdownId = null;
            }
        });

        function showAlert(message, type = 'error') {
            apiAlert.textContent = message;
            apiAlert.className = `border px-4 py-3 rounded relative mb-6 ${type === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'}`;
            apiAlert.classList.remove('hidden');
            setTimeout(() => apiAlert.classList.add('hidden'), 5000);
        }
        
        function capitalizeFirstLetter(string) { return string.charAt(0).toUpperCase() + string.slice(1); }

        async function handleAction(entity, id, action, buttonElement, data = null) {
            let confirmationMessage = "Are you sure?";
            let needsConfirmation = true;

             if (action.startsWith('get_')) { needsConfirmation = false; }
             else if (action.startsWith('create_')) { needsConfirmation = false; }
             else if (entity === 'user') {
                if (action === 'delete_user') { confirmationMessage = "Are you sure you want to PERMANENTLY DELETE this user?"; }
                else if (action === 'toggle_user_status') { const currentActionText = buttonElement.querySelector('[data-status-text]').textContent.trim(); confirmationMessage = `Are you sure you want to ${currentActionText.toLowerCase()} this user?`; }
            } else if (entity === 'order') {
                 if (action === 'delete_order') { confirmationMessage = "Are you sure you want to PERMANENTLY DELETE this order?"; }
            } else if (entity === 'product') {
                if (action === 'delete_product') { confirmationMessage = "Are you sure you want to PERMANENTLY DELETE this product from db.json?"; }
            }

            if (needsConfirmation && !confirm(confirmationMessage)) {
                 if (openDropdownId) { document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden'); openDropdownId = null; }
                return null;
            }
            
            const safeId = String(id).replace(/\./g, '_');
            const row = document.getElementById(`${entity}-row-${safeId}`);
            const saveButton = document.getElementById(`edit${capitalizeFirstLetter(entity)}SaveButton`);
            const createButton = document.getElementById(`add${capitalizeFirstLetter(entity)}Button`);

            try {
                if(buttonElement) buttonElement.disabled = true;
                if(saveButton) saveButton.disabled = true;
                if(createButton) createButton.disabled = true;
                if(buttonElement?.innerHTML) buttonElement.innerHTML += ' <i class="fas fa-spinner fa-spin"></i>';
                if(saveButton) saveButton.innerHTML = 'Saving... <i class="fas fa-spinner fa-spin ml-2"></i>';
                if(createButton) createButton.innerHTML = 'Creating... <i class="fas fa-spinner fa-spin ml-2"></i>';

                const payload = { entity: entity, action: action, ...data };
                if (!action.startsWith('create_')) {
                    payload[entity + '_id'] = id;
                }

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json','Accept': 'application/json'},
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (result.success) {
                    if (action.startsWith('get_')) {
                        return result[entity]; // Return user/product/order data
                    } 
                    
                    showAlert(result.message || 'Action successful!', 'success');
                    
                    if (action.startsWith('delete_')) {
                        row?.remove();
                    } 
                    else if (action === 'toggle_user_status') {
                        const statusBadge = row.querySelector('[data-status-badge]'); const statusTextElement = buttonElement.querySelector('[data-status-text]'); const statusIcon = buttonElement.querySelector('i');
                        if (statusBadge && statusTextElement && statusIcon) { statusBadge.textContent = result.newStatusText; statusBadge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${(result.newStatus == 1) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`; statusTextElement.textContent = (result.newStatus == 1) ? 'Deactivate' : 'Activate'; statusIcon.className = `fas fa-toggle-${(result.newStatus == 1) ? 'on text-green-500' : 'off text-red-500'} w-4 h-4 mr-2`; }
                    } 
                    else if (action === 'update_user') {
                         if (row) {
                             row.querySelector('[data-field="name"]').textContent = data.full_name;
                             row.querySelector('[data-field="email"]').textContent = data.email;
                             const statusBadge = row.querySelector('[data-status-badge]');
                             if(statusBadge) { statusBadge.textContent = data.is_active == 1 ? 'Active' : 'Inactive'; statusBadge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${(data.is_active == 1) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`; }
                             const toggleButton = row.querySelector('button[onclick*="toggle_user_status"]');
                              if (toggleButton) { toggleButton.querySelector('[data-status-text]').textContent = data.is_active == 1 ? 'Deactivate' : 'Activate'; toggleButton.querySelector('i').className = `fas fa-toggle-${(data.is_active == 1) ? 'on text-green-500' : 'off text-red-500'} w-4 h-4 mr-2`; }
                         }
                         closeEditModal('user');
                    }
                    else if (action === 'update_product') {
                         if (row) {
                             row.querySelector('[data-field="id"]').textContent = data.id;
                             row.querySelector('[data-field="brand"]').textContent = data.brand;
                             row.querySelector('[data-field="price"]').textContent = '₹' + parseFloat(data.price).toFixed(2);
                             const newSafeId = String(data.id).replace(/\./g, '_');
                             row.id = `product-row-${newSafeId}`;
                             row.querySelector('button[onclick^="toggleDropdown"]').setAttribute('onclick', `toggleDropdown('product-${newSafeId}')`);
                             row.querySelector('div[id^="dropdown-product-"]').id = `dropdown-product-${newSafeId}`;
                             row.querySelector('button[onclick*="openEditModal"]').setAttribute('onclick', `openEditModal('product', '${data.id}')`);
                             row.querySelector('button[onclick*="handleAction"]').setAttribute('onclick', `handleAction('product', '${data.id}', 'delete_product', this)`);
                         }
                         closeEditModal('product');
                    }
                    else if (action === 'update_order_status' || action === 'update_order_details') {
                        if (row) {
                            const statusBadge = row.querySelector('[data-status-badge] span');
                            if (statusBadge) {
                                statusBadge.textContent = data.order_status;
                                // Update badge color based on status
                                let colorClass = 'bg-gray-100 text-gray-800';
                                if (data.order_status === 'Delivered') colorClass = 'bg-green-100 text-green-800';
                                else if (data.order_status === 'Shipped') colorClass = 'bg-blue-100 text-blue-800';
                                else if (data.order_status === 'Processing') colorClass = 'bg-yellow-100 text-yellow-800';
                                else if (data.order_status === 'Cancelled') colorClass = 'bg-red-100 text-red-800';
                                statusBadge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}`;
                            }
                        }
                        closeEditModal('order');
                    }
                    else if (action === 'create_user' || action === 'create_product') {
                         setTimeout(() => { window.location.reload(); }, 1500);
                    }
                     return true; 
                } else { // API returned success: false
                    const errorMessage = 'Error: ' + (result.message || 'An unknown error occurred.');
                    if (action.startsWith('update_') || action.startsWith('create_')) {
                         const errorDivId = `edit${capitalizeFirstLetter(entity)}Error`;
                         const errorDiv = document.getElementById(errorDivId) || apiAlert;
                         errorDiv.textContent = errorMessage;
                         errorDiv.classList.remove('hidden');
                    } else {
                        showAlert(errorMessage, 'error');
                    }
                    return false;
                }
            } catch (error) {
                console.error('Action failed:', error);
                 const errorMessage = 'Request Error: Could not save changes. Check console.';
                 if (action.startsWith('update_') || action.startsWith('create_')) {
                     const errorDivId = `edit${capitalizeFirstLetter(entity)}Error`;
                     const errorDiv = document.getElementById(errorDivId) || apiAlert;
                     errorDiv.textContent = errorMessage;
                     errorDiv.classList.remove('hidden');
                 } else {
                     showAlert(errorMessage, 'error');
                 }
                return false;
            } finally {
                if(buttonElement) buttonElement.disabled = false;
                if(saveButton) saveButton.disabled = false;
                if(createButton) createButton.disabled = false;
                buttonElement?.querySelector('.fa-spinner')?.remove();
                if(saveButton) saveButton.innerHTML = 'Save Changes';
                if(createButton) createButton.innerHTML = 'Create User';
                 if (action !== 'get_user_details' && action !== 'get_product_details' && action !== 'get_order_details' && openDropdownId) {
                    document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden');
                    openDropdownId = null;
                 }
            }
        }

        // --- Modal Functions (Generic) ---
        function closeEditModal(entity) { document.getElementById(`edit${capitalizeFirstLetter(entity)}Modal`).classList.add('hidden'); document.getElementById(`edit${capitalizeFirstLetter(entity)}Form`).reset(); }

        // --- Modal Open/Populate Function (Generic) ---
        async function openEditModal(entity, id) {
            const modal = document.getElementById(`edit${capitalizeFirstLetter(entity)}Modal`);
            const form = document.getElementById(`edit${capitalizeFirstLetter(entity)}Form`);
            const errorDiv = document.getElementById(`edit${capitalizeFirstLetter(entity)}Error`);
            const title = modal.querySelector('h3');
            
            errorDiv.classList.add('hidden');
            form.reset();
            
            if (id === null) { // "Add New" action
                title.textContent = `Add New ${capitalizeFirstLetter(entity)}`;
                if (entity === 'product') {
                    form.querySelector(`input[name="action"]`).value = 'create_product';
                    form.querySelector(`input[name="original_id"]`).value = '';
                }
                modal.classList.remove('hidden');
            } else { // "Edit" action
                title.textContent = `Edit ${capitalizeFirstLetter(entity)}`;
                const data = await handleAction(entity, id, `get_${entity}_details`, null);
                if (data) {
                    // Populate form
                    if(entity === 'user') {
                        form.querySelector(`input[name="user_id"]`).value = data.id;
                        form.querySelector(`input[name="full_name"]`).value = data.full_name || '';
                        form.querySelector(`input[name="email"]`).value = data.email || '';
                        if (data.is_active == 1) { form.querySelector(`input[name="is_active"][value="1"]`).checked = true; } 
                        else { form.querySelector(`input[name="is_active"][value="0"]`).checked = true; }
                    } else if (entity === 'product') {
                        form.querySelector(`input[name="action"]`).value = 'update_product';
                        form.querySelector(`input[name="original_id"]`).value = data.id;
                        form.querySelector(`input[name="id"]`).value = data.id || '';
                        form.querySelector(`input[name="brand"]`).value = data.brand || '';
                        form.querySelector(`input[name="price"]`).value = data.price || '';
                        form.querySelector(`input[name="imageUrl"]`).value = data.imageUrl || '';
                        form.querySelector(`input[name="imageUrl2"]`).value = data.imageUrl2 || '';
                        form.querySelector(`input[name="rating"]`).value = data.rating || '';
                        form.querySelector(`input[name="reviews"]`).value = data.reviews || '';
                        form.querySelector(`input[name="sizeCollection"]`).value = data.sizeCollection || '';
                        form.querySelector(`input[name="frame_type"]`).value = data.frame_type || '';
                        form.querySelector(`input[name="frame_shape"]`).value = data.frame_shape || '';
                        form.querySelector(`input[name="age_group"]`).value = data.age_group || '';
                        form.querySelector(`input[name="gender"]`).value = data.gender || '';
                    } else if (entity === 'order') {
                        title.textContent = `Edit Order #${data.id}`;
                        form.querySelector(`input[name="order_id"]`).value = data.id;
                        form.querySelector(`input[name="full_name"]`).value = data.full_name || '';
                        form.querySelector(`input[name="phone"]`).value = data.phone || '';
                        form.querySelector(`input[name="address_line1"]`).value = data.address_line1 || '';
                        form.querySelector(`input[name="city"]`).value = data.city || '';
                        form.querySelector(`input[name="pincode"]`).value = data.pincode || '';
                        form.querySelector(`select[name="order_status"]`).value = data.order_status;
                        
                        // Build and display order items summary
                        const contentDiv = document.getElementById('orderDetailsContent');
                        let itemsHTML = '';
                        if (data.items_json) {
                            try {
                                const items = JSON.parse(data.items_json);
                                itemsHTML = '<div class="bg-gray-50 p-4 rounded-lg"><h4 class="font-medium text-gray-800 mb-3">Order Items</h4><div class="space-y-2">';
                                items.forEach(item => {
                                    itemsHTML += `<div class="flex justify-between items-center text-sm border-b pb-2">
                                        <span class="font-medium">${item.name || 'Product'}</span>
                                        <span class="text-gray-600">Qty: ${item.qty} × ₹${item.price || 0}</span>
                                        <span class="font-semibold">₹${(item.qty * (item.price || 0)).toFixed(2)}</span>
                                    </div>`;
                                });
                                itemsHTML += `</div><div class="mt-3 pt-2 border-t font-semibold text-right">Total: ₹${data.total_amount || 0}</div></div>`;
                            } catch(e) { itemsHTML = '<div class="bg-red-50 p-4 rounded-lg text-red-600">Error reading order items.</div>'; }
                        }
                        
                        contentDiv.innerHTML = itemsHTML || '<div class="bg-gray-50 p-4 rounded-lg text-gray-500">No items found in this order.</div>';
                    }
                    modal.classList.remove('hidden');
                } else { showAlert(`Could not fetch ${entity} details.`, 'error'); }
            }
             if (openDropdownId) { document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden'); openDropdownId = null; }
        }

        async function submitEditForm(entity, event) {
            event.preventDefault();
            const form = document.getElementById(`edit${capitalizeFirstLetter(entity)}Form`);
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            let id, action;
            
            if (entity === 'user') {
                id = data.user_id;
                action = 'update_user';
            } else if (entity === 'product') {
                action = data.action; // 'create_product' or 'update_product'
                id = (action === 'create_product') ? data.id : data.original_id;
            } else if (entity === 'order') {
                id = data.order_id;
                action = 'update_order_details'; // Updated to handle full order details
            }
            
            await handleAction(entity, id, action, null, data);
        }
        
         async function submitAddUser(event) {
            event.preventDefault();
            const form = document.getElementById('addUserForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            await handleAction('user', 0, 'create_user', document.getElementById('addUserButton'), data);
         }
         
         // Quick status update function for orders
         async function quickUpdateStatus(orderId, newStatus, buttonElement) {
             const data = { order_status: newStatus };
             const success = await handleAction('order', orderId, 'update_order_status', buttonElement, data);
             if (success) {
                 // Update the status badge in the table
                 const row = document.getElementById(`order-row-${orderId}`);
                 if (row) {
                     const statusBadge = row.querySelector('[data-status-badge] span');
                     if (statusBadge) {
                         statusBadge.textContent = newStatus;
                         let colorClass = 'bg-gray-100 text-gray-800';
                         if (newStatus === 'Delivered') colorClass = 'bg-green-100 text-green-800';
                         else if (newStatus === 'Shipped') colorClass = 'bg-blue-100 text-blue-800';
                         else if (newStatus === 'Processing') colorClass = 'bg-yellow-100 text-yellow-800';
                         else if (newStatus === 'Cancelled') colorClass = 'bg-red-100 text-red-800';
                         statusBadge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}`;
                     }
                 }
             }
         }
    </script>
</body>
</html>