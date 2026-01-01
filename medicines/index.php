<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build SQL query with search and filter
$sql = "SELECT m.*, s.name as supplier_name 
        FROM medicines m 
        LEFT JOIN suppliers s ON m.supplier_id = s.id 
        WHERE 1=1";
$params = [];

if(!empty($search)) {
    $sql .= " AND (m.name LIKE ? OR m.category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if(!empty($category_filter) && $category_filter !== 'all') {
    $sql .= " AND m.category = ?";
    $params[] = $category_filter;
}

$sql .= " ORDER BY m.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// Get distinct categories for filter dropdown
$catStmt = $pdo->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $catStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines - Pharmacy Management</title>
    <style>
        /* Reuse dashboard styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
        }
        
        .navbar {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #555;
            padding: 8px 15px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: #f0f2ff;
            color: #667eea;
        }
        
        .user-info {
            color: #666;
        }
        
        .logout-btn {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            color: #666;
            text-decoration: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title {
            font-size: 28px;
            color: #333;
        }
        
        .add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .search-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input, .filter-box select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .search-box input:focus, .filter-box select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-box {
            min-width: 200px;
        }
        
        .search-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 18px 15px;
            text-align: left;
            color: #555;
            font-weight: 600;
            border-bottom: 1px solid #eee;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .table tr:hover {
            background: #f9faff;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .stock-low {
            color: #dc3545;
            font-weight: 600;
        }
        
        .stock-medium {
            color: #ffc107;
            font-weight: 600;
        }
        
        .stock-high {
            color: #28a745;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit, .btn-delete, .btn-view {
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background: #e7f1ff;
            color: #0069d9;
            border: 1px solid #b3d7ff;
        }
        
        .btn-edit:hover {
            background: #0069d9;
            color: white;
        }
        
        .btn-delete {
            background: #fdeaea;
            color: #dc3545;
            border: 1px solid #f5c6cb;
        }
        
        .btn-delete:hover {
            background: #dc3545;
            color: white;
        }
        
        .btn-view {
            background: #e8f5e9;
            color: #28a745;
            border: 1px solid #c3e6cb;
        }
        
        .btn-view:hover {
            background: #28a745;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .expired {
            background-color: #fff0f0;
        }
        
        .expired-badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="../dashboard.php" class="logo">Pharmacy Management</a>
        <div class="nav-links">
            <a href="../dashboard.php">Dashboard</a>
            <a href="index.php" style="background: #f0f2ff; color: #667eea;">Medicines</a>
            <a href="../sales/">Sales</a>
            <a href="../suppliers/">Suppliers</a>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="user-info">
                <?php echo htmlspecialchars($_SESSION['username']); ?> 
                (<?php echo htmlspecialchars($_SESSION['role']); ?>)
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <!-- Header -->
        <div class="header-actions">
            <h1 class="page-title">💊 Medicine Inventory</h1>
            <a href="add.php" class="add-btn">＋ Add New Medicine</a>
        </div>
        
        <!-- Search and Filter -->
        <form method="GET" action="" class="search-filter">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search by name or category..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-box">
                <select name="category">
                    <option value="all">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                            <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="search-btn">Search</button>
            <?php if(!empty($search) || !empty($category_filter)): ?>
                <a href="index.php" style="padding: 12px 15px; color: #666; text-decoration: none;">Clear</a>
            <?php endif; ?>
        </form>
        
        <!-- Medicines Table -->
        <div class="table-container">
            <?php if(count($medicines) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Price (Birr)</th>
                            <th>Stock</th>
                            <th>Expiry Date</th>
                            <th>Supplier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($medicines as $medicine): 
                            $is_expired = !empty($medicine['expiry_date']) && 
                                         strtotime($medicine['expiry_date']) < time();
                            $row_class = $is_expired ? 'expired' : '';
                            
                            // Stock status
                            if($medicine['quantity'] <= 10) {
                                $stock_class = 'stock-low';
                                $stock_status = 'Low';
                            } elseif($medicine['quantity'] <= 30) {
                                $stock_class = 'stock-medium';
                                $stock_status = 'Medium';
                            } else {
                                $stock_class = 'stock-high';
                                $stock_status = 'Good';
                            }
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>#<?php echo htmlspecialchars($medicine['id']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($medicine['name']); ?>
                                    <?php if($is_expired): ?>
                                        <span class="expired-badge">EXPIRED</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($medicine['category'])): ?>
                                        <span class="badge badge-success">
                                            <?php echo htmlspecialchars($medicine['category']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>Br<?php echo htmlspecialchars(number_format($medicine['price'], 2)); ?></td>
                                <td class="<?php echo $stock_class; ?>">
                                    <?php echo htmlspecialchars($medicine['quantity']); ?>
                                    <small>(<?php echo $stock_status; ?>)</small>
                                </td>
                                <td>
                                    <?php if(!empty($medicine['expiry_date'])): ?>
                                        <?php 
                                            $expiry_date = date('d M Y', strtotime($medicine['expiry_date']));
                                            echo htmlspecialchars($expiry_date);
                                            
                                            // Calculate days to expiry
                                            $today = new DateTime();
                                            $expiry = new DateTime($medicine['expiry_date']);
                                            $diff = $today->diff($expiry);
                                            $days_left = $diff->days;
                                            $days_left = $diff->invert ? -$days_left : $days_left;
                                            
                                            if($days_left < 0) {
                                                echo '<br><small style="color: #dc3545;">Expired ' . abs($days_left) . ' days ago</small>';
                                            } elseif($days_left <= 30) {
                                                echo '<br><small style="color: #ffc107;">' . $days_left . ' days left</small>';
                                            } else {
                                                echo '<br><small style="color: #28a745;">' . $days_left . ' days left</small>';
                                            }
                                        ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($medicine['supplier_name'])): ?>
                                        <?php echo htmlspecialchars($medicine['supplier_name']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit.php?id=<?php echo $medicine['id']; ?>" class="btn-edit">Edit</a>
                                        <a href="delete.php?id=<?php echo $medicine['id']; ?>" 
                                           class="btn-delete"
                                           onclick="return confirm('Are you sure you want to delete this medicine?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No medicines found</h3>
                    <p>Start by adding your first medicine to the inventory.</p>
                    <a href="add.php" class="add-btn" style="margin-top: 20px;">＋ Add Your First Medicine</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Stats Summary -->
        <div style="margin-top: 20px; color: #666; font-size: 14px;">
            Total: <?php echo count($medicines); ?> medicine(s) found
        </div>
    </div>
    
    <script>
        // JavaScript for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus search input
            const searchInput = document.querySelector('input[name="search"]');
            if(searchInput && !searchInput.value) {
                searchInput.focus();
            }
            
            // Filter change auto-submit (optional)
            document.querySelector('select[name="category"]').addEventListener('change', function() {
                if(this.value !== 'all') {
                    this.form.submit();
                }
            });
        });
    </script>
</body>
</html>