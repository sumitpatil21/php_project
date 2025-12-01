<?php
session_start();

// Admin security check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../auth/signin.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

// Fetch orders with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter options
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "order_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR id LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM orders $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();

// Get orders
$sql = "SELECT * FROM orders $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$total_pages = ceil($total_orders / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .action-dropdown { position: absolute; right: 0; margin-top: 0.5rem; z-index: 10; }
        .modal-overlay { position: fixed; inset: 0; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; }
        .modal-content { position: relative; background: white; padding: 2rem; border-radius: 0.5rem; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; }
        .modal-close-btn { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.875rem; color: #374151; }
        .form-input, .form-select { display: block; width: 100%; border: 1px solid #d1d5db; border-radius: 0.375rem; padding: 0.5rem 0.75rem; }
        .form-input:focus, .form-select:focus { outline: none; border-color: #3b82f6; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <a href="admin_panel.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                        <h1 class="text-2xl font-bold text-gray-900">Order Management</h1>
                    </div>
                    <div class="text-sm text-gray-600">
                        Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-64">
                        <label for="search" class="form-label">Search Orders</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name, order ID, or phone..." class="form-input">
                    </div>
                    <div class="min-w-48">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Shipped" <?php echo $status_filter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="order_management.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-6 border-b">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-semibold">Orders (<?php echo $total_orders; ?> total)</h2>
                        <div class="text-sm text-gray-600">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($orders as $order): ?>
                            <tr id="order-row-<?php echo $order['id']; ?>" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-blue-600">
                                    #<?php echo $order['id']; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($order['full_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo htmlspecialchars($order['phone']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    ₹<?php echo number_format($order['total_amount']); ?>
                                </td>
                                <td class="px-6 py-4" data-status-badge>
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
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date("M d, Y", strtotime($order['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right relative">
                                    <button onclick="toggleDropdown('order-<?php echo $order['id']; ?>')" 
                                            class="p-1 rounded-md hover:bg-gray-100">
                                        <i class="fas fa-ellipsis-v text-gray-500"></i>
                                    </button>
                                    <div id="dropdown-order-<?php echo $order['id']; ?>" class="action-dropdown w-56 bg-white rounded-md shadow-lg border hidden">
                                        <div class="py-1">
                                            <button onclick="openEditModal(<?php echo $order['id']; ?>)" 
                                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-edit mr-2"></i> Edit Order
                                            </button>
                                            <div class="border-t my-1"></div>
                                            <div class="px-3 py-1 text-xs font-medium text-gray-500 uppercase">Quick Status</div>
                                            <?php 
                                            $statuses = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];
                                            foreach ($statuses as $status): 
                                                if ($status !== $order['order_status']): ?>
                                            <button onclick="quickUpdateStatus(<?php echo $order['id']; ?>, '<?php echo $status; ?>')" 
                                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-<?php echo $status === 'Processing' ? 'clock' : ($status === 'Shipped' ? 'truck' : ($status === 'Delivered' ? 'check-circle' : 'times-circle')); ?> mr-2"></i>
                                                Mark as <?php echo $status; ?>
                                            </button>
                                            <?php endif; endforeach; ?>
                                            <div class="border-t my-1"></div>
                                            <button onclick="deleteOrder(<?php echo $order['id']; ?>)" 
                                                    class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                                <i class="fas fa-trash-alt mr-2"></i> Delete Order
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    No orders found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t bg-gray-50">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_orders); ?> of <?php echo $total_orders; ?> orders
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
                               class="px-3 py-1 bg-white border rounded-md text-sm hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
                               class="px-3 py-1 border rounded-md text-sm <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'; ?>">
                               <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
                               class="px-3 py-1 bg-white border rounded-md text-sm hover:bg-gray-50">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Order Modal -->
    <div id="editOrderModal" class="modal-overlay hidden">
        <div class="modal-content">
            <button onclick="closeModal()" class="modal-close-btn"><i class="fas fa-times"></i></button>
            <h3 class="text-lg font-medium mb-4">Edit Order Details</h3>
            
            <div id="orderItemsDisplay" class="mb-6"></div>
            
            <form id="editOrderForm" onsubmit="updateOrder(event)">
                <input type="hidden" id="orderId" name="order_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Full Name</label>
                        <input type="text" id="fullName" name="full_name" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="tel" id="phone" name="phone" required class="form-input">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Address</label>
                        <input type="text" id="address" name="address_line1" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">City</label>
                        <input type="text" id="city" name="city" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Pincode</label>
                        <input type="text" id="pincode" name="pincode" required class="form-input">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Order Status</label>
                        <select id="orderStatus" name="order_status" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Update Order</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let openDropdownId = null;

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
            if (openDropdownId && !e.target.closest('.action-dropdown') && !e.target.closest('button[onclick^="toggleDropdown"]')) {
                document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden');
                openDropdownId = null;
            }
        });

        async function openEditModal(orderId) {
            try {
                const response = await fetch('../admin/admin-action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({entity: 'order', action: 'get_order_details', order_id: orderId})
                });
                
                const result = await response.json();
                if (result.success) {
                    const order = result.order;
                    
                    // Populate form
                    document.getElementById('orderId').value = order.id;
                    document.getElementById('fullName').value = order.full_name || '';
                    document.getElementById('phone').value = order.phone || '';
                    document.getElementById('address').value = order.address_line1 || '';
                    document.getElementById('city').value = order.city || '';
                    document.getElementById('pincode').value = order.pincode || '';
                    document.getElementById('orderStatus').value = order.order_status;
                    
                    // Display order items
                    let itemsHTML = '';
                    if (order.items_json) {
                        try {
                            const items = JSON.parse(order.items_json);
                            itemsHTML = '<div class="bg-gray-50 p-4 rounded-lg"><h4 class="font-medium mb-3">Order Items</h4>';
                            items.forEach(item => {
                                itemsHTML += `<div class="flex justify-between py-2 border-b">
                                    <span>${item.name || 'Product'}</span>
                                    <span>Qty: ${item.qty} × ₹${item.price || 0}</span>
                                </div>`;
                            });
                            itemsHTML += `<div class="font-semibold text-right mt-2 pt-2 border-t">Total: ₹${order.total_amount}</div></div>`;
                        } catch(e) {
                            itemsHTML = '<div class="bg-red-50 p-4 rounded-lg text-red-600">Error loading order items</div>';
                        }
                    }
                    document.getElementById('orderItemsDisplay').innerHTML = itemsHTML;
                    
                    document.getElementById('editOrderModal').classList.remove('hidden');
                } else {
                    alert('Error loading order details: ' + result.message);
                }
            } catch (error) {
                alert('Error loading order details');
            }
            
            if (openDropdownId) {
                document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden');
                openDropdownId = null;
            }
        }

        async function updateOrder(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('../admin/admin-action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({entity: 'order', action: 'update_order_details', ...data})
                });
                
                const result = await response.json();
                if (result.success) {
                    alert('Order updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating order: ' + result.message);
                }
            } catch (error) {
                alert('Error updating order');
            }
        }

        async function quickUpdateStatus(orderId, newStatus) {
            try {
                const response = await fetch('../admin/admin-action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({entity: 'order', action: 'update_order_status', order_id: orderId, order_status: newStatus})
                });
                
                const result = await response.json();
                if (result.success) {
                    // Update status badge
                    const row = document.getElementById(`order-row-${orderId}`);
                    const statusBadge = row.querySelector('[data-status-badge] span');
                    statusBadge.textContent = newStatus;
                    
                    let colorClass = 'bg-gray-100 text-gray-800';
                    if (newStatus === 'Delivered') colorClass = 'bg-green-100 text-green-800';
                    else if (newStatus === 'Shipped') colorClass = 'bg-blue-100 text-blue-800';
                    else if (newStatus === 'Processing') colorClass = 'bg-yellow-100 text-yellow-800';
                    else if (newStatus === 'Cancelled') colorClass = 'bg-red-100 text-red-800';
                    
                    statusBadge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}`;
                    
                    alert('Order status updated successfully!');
                } else {
                    alert('Error updating status: ' + result.message);
                }
            } catch (error) {
                alert('Error updating status');
            }
            
            if (openDropdownId) {
                document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden');
                openDropdownId = null;
            }
        }

        async function deleteOrder(orderId) {
            if (!confirm('Are you sure you want to delete this order? This action cannot be undone.')) return;
            
            try {
                const response = await fetch('../admin/admin-action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({entity: 'order', action: 'delete_order', order_id: orderId})
                });
                
                const result = await response.json();
                if (result.success) {
                    document.getElementById(`order-row-${orderId}`).remove();
                    alert('Order deleted successfully!');
                } else {
                    alert('Error deleting order: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting order');
            }
            
            if (openDropdownId) {
                document.getElementById('dropdown-' + openDropdownId)?.classList.add('hidden');
                openDropdownId = null;
            }
        }

        function closeModal() {
            document.getElementById('editOrderModal').classList.add('hidden');
        }
    </script>
</body>
</html>