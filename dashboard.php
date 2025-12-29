<?php
require_once 'config/session.php';
require_once 'config/database.php';
requireLogin();

// Get statistics for dashboard
$stats = [];

// Total medicines
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines");
$stats['total_medicines'] = $stmt->fetch()['total'];

// Low stock medicines (less than 20)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE quantity < 20");
$stats['low_stock'] = $stmt->fetch()['total'];

// Total suppliers
$stmt = $pdo->query("SELECT COUNT(*) as total FROM suppliers");
$stats['total_suppliers'] = $stmt->fetch()['total'];

// Recent sales (last 7 days)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sales WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent_sales'] = $stmt->fetch()['total'];

// Get low stock medicines list
$stmt = $pdo->query("SELECT name, quantity FROM medicines WHERE quantity < 20 ORDER BY quantity ASC LIMIT 10");
$low_stock_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pharmacy Management</title>
    <style>
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
        
        .logout-btn:hover {
            background: #eee;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .welcome-section h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2ff;
        }
        
        .table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
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
        
        .stock-low {
            color: #dc3545;
            font-weight: 600;
        }
        
        .stock-ok {
            color: #28a745;
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="dashboard.php" class="logo">Pharmacy Management</a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="medicines/">Medicines</a>
            <a href="sales/">Sales</a>
            <a href="suppliers/">Suppliers</a>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> 
                (<?php echo htmlspecialchars($_SESSION['role']); ?>)
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p>Manage your pharmacy inventory, sales, and suppliers efficiently</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_medicines']; ?></div>
                <div class="stat-label">Total Medicines</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_suppliers']; ?></div>
                <div class="stat-label">Suppliers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['recent_sales']; ?></div>
                <div class="stat-label">Sales (7 Days)</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="medicines/add.php" class="action-btn">+ Add New Medicine</a>
            <a href="sales/create.php" class="action-btn">+ New Sale</a>
            <a href="suppliers/add.php" class="action-btn">+ Add Supplier</a>
        </div>
        
        <!-- Low Stock Alert -->
        <div style="margin-top: 40px;">
            <h2 class="section-title">⚠️ Low Stock Alert</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Medicine Name</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($low_stock_list) > 0): ?>
                        <?php foreach($low_stock_list as $medicine): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['quantity']); ?></td>
                                <td class="stock-low">LOW STOCK</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: #28a745;">
                                All medicines have sufficient stock! ✅
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>