<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build SQL query with search
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM medicines WHERE supplier_id = s.id) as medicine_count
        FROM suppliers s 
        WHERE 1=1";
$params = [];

if(!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY s.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - Pharmacy Management</title>
    <style>
        /* Reuse styles from medicines */
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
        
        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
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
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
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
        
        .contact-info {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .contact-info a {
            color: #667eea;
            text-decoration: none;
        }
        
        .medicine-count {
            font-weight: 600;
            color: #333;
        }
          .model{
           color:red;
           font-weight:600px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="../dashboard.php" class="logo"><span class="model">Model</span> Pharmacy</a>
        <div class="nav-links">
            <a href="../dashboard.php">Dashboard</a>
            <a href="../medicines/">Medicines</a>
            <a href="../sales/">Sales</a>
            <a href="index.php" style="background: #f0f2ff; color: #667eea;">Suppliers</a>
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
            <h1 class="page-title">🏢 Suppliers</h1>
            <a href="add.php" class="add-btn">＋ Add New Supplier</a>
        </div>
        
        <!-- Search Box -->
        <form method="GET" action="" class="search-box">
            <input type="text" name="search" placeholder="Search suppliers by name, email or phone..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">Search</button>
            <?php if(!empty($search)): ?>
                <a href="index.php" style="padding: 12px 15px; color: #666; text-decoration: none;">Clear</a>
            <?php endif; ?>
        </form>
        
        <!-- Suppliers Table -->
        <div class="table-container">
            <?php if(count($suppliers) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Supplier Name</th>
                            <th>Contact Information</th>
                            <th>Medicines Supplied</th>
                            <th>Added On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($suppliers as $supplier): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($supplier['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($supplier['name']); ?></strong>
                                </td>
                                <td>
                                    <?php if(!empty($supplier['email'])): ?>
                                        <div class="contact-info">
                                            📧 <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>">
                                                <?php echo htmlspecialchars($supplier['email']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($supplier['phone'])): ?>
                                        <div class="contact-info">
                                            📞 <?php echo htmlspecialchars($supplier['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($supplier['address'])): ?>
                                        <div class="contact-info">
                                            📍 <?php echo htmlspecialchars(substr($supplier['address'], 0, 50)); ?>
                                            <?php if(strlen($supplier['address']) > 50): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="medicine-count"><?php echo htmlspecialchars($supplier['medicine_count']); ?></span> medicine(s)
                                    <?php if($supplier['medicine_count'] > 0): ?>
                                        <div class="contact-info">
                                            <a href="../medicines/index.php?supplier=<?php echo $supplier['id']; ?>">View medicines</a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($supplier['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit.php?id=<?php echo $supplier['id']; ?>" class="btn-edit">Edit</a>
                                        <a href="delete.php?id=<?php echo $supplier['id']; ?>" 
                                           class="btn-delete"
                                           onclick="return confirm('Are you sure you want to delete this supplier?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No suppliers found</h3>
                    <p>Start by adding your first supplier to the system.</p>
                    <a href="add.php" class="add-btn" style="margin-top: 20px;">＋ Add Your First Supplier</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Stats Summary -->
        <div style="margin-top: 20px; color: #666; font-size: 14px;">
            Total: <?php echo count($suppliers); ?> supplier(s) found
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus search input
            const searchInput = document.querySelector('input[name="search"]');
            if(searchInput && !searchInput.value) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>