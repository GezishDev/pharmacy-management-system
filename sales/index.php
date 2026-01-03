<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Handle date filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build SQL query
$sql = "SELECT s.*, m.name as medicine_name, u.username as sold_by_name 
        FROM sales s 
        LEFT JOIN medicines m ON s.medicine_id = m.id 
        LEFT JOIN users u ON s.sold_by = u.id 
        WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if(!empty($search)) {
    $sql .= " AND (s.invoice_number LIKE ? OR s.customer_name LIKE ? OR m.name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY s.sale_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Calculate totals
$total_sales = 0;
$total_quantity = 0;
foreach($sales as $sale) {
    $total_sales += $sale['total_price'];
    $total_quantity += $sale['quantity_sold'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Pharmacy Management</title>
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
        
        .container {
            max-width: 1400px;
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
        
        .filters {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .filter-group input {
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .filter-group input:focus {
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
            height: 46px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .summary-card h3 {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .summary-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
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
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-view, .btn-delete {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
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
        
        .btn-delete {
            background: #fdeaea;
            color: #dc3545;
            border: 1px solid #f5c6cb;
        }
        
        .btn-delete:hover {
            background: #dc3545;
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
        
        .customer-info {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .export-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
         .model{
           color:red;
           font-weight:600px;
        }
        
        @media print {
            .navbar, .filters, .header-actions, .summary-cards, .action-buttons {
                display: none !important;
            }
            
            .container {
                margin: 0;
                padding: 0;
            }
            
            .table-container {
                box-shadow: none;
            }
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
            <a href="index.php" style="background: #f0f2ff; color: #667eea;">Sales</a>
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
            <h1 class="page-title">📊 Sales Report</h1>
            <div style="display: flex; gap: 15px;">
                <a href="create.php" class="add-btn">＋ New Sale</a>
                <button onclick="window.print()" class="export-btn">🖨️ Print Report</button>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="GET" action="" class="filters">
            <div class="filter-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            
            <div class="filter-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            
            <div class="filter-group">
                <label for="search">Search Invoice/Customer</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Invoice, Customer, or Medicine...">
            </div>
            
            <div>
                <button type="submit" class="search-btn">Apply Filters</button>
                <?php if(!empty($search) || $start_date != date('Y-m-01') || $end_date != date('Y-m-d')): ?>
                    <a href="index.php" style="padding: 12px 15px; color: #666; text-decoration: none;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <h3>Total Sales</h3>
                <div class="summary-value">Br<?php echo number_format($total_sales, 2); ?></div>
            </div>
            
            <div class="summary-card">
                <h3>Total Transactions</h3>
                <div class="summary-value"><?php echo count($sales); ?></div>
            </div>
            
            <div class="summary-card">
                <h3>Items Sold</h3>
                <div class="summary-value"><?php echo $total_quantity; ?></div>
            </div>
            
            <div class="summary-card">
                <h3>Period</h3>
                <div class="summary-value">
                    <?php echo date('d M Y', strtotime($start_date)); ?> - 
                    <?php echo date('d M Y', strtotime($end_date)); ?>
                </div>
            </div>
        </div>
        
        <!-- Sales Table -->
        <div class="table-container">
            <?php if(count($sales) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Medicine</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total Amount</th>
                            <th>Sold By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sales as $sale): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($sale['sale_date'])); ?><br>
                                    <small style="color: #666;"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></small>
                                </td>
                                <td>
                                    <?php if(!empty($sale['customer_name'])): ?>
                                        <?php echo htmlspecialchars($sale['customer_name']); ?>
                                        <?php if(!empty($sale['customer_phone'])): ?>
                                            <div class="customer-info">📞 <?php echo htmlspecialchars($sale['customer_phone']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Walk-in</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($sale['medicine_name'])): ?>
                                        <?php echo htmlspecialchars($sale['medicine_name']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($sale['quantity_sold']); ?></td>
                                <td>Br<?php echo number_format($sale['total_price'] / $sale['quantity_sold'], 2); ?></td>
                                <td>
                                    <strong>Br<?php echo number_format($sale['total_price'], 2); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($sale['sold_by_name']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?php echo $sale['id']; ?>" class="btn-view">View</a>
                                        <?php if($_SESSION['role'] == 'admin'): ?>
                                            <a href="delete.php?id=<?php echo $sale['id']; ?>" 
                                               class="btn-delete"
                                               onclick="return confirm('Are you sure you want to delete this sale? This will restore stock.')">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No sales found for the selected period</h3>
                    <p>Try adjusting your filters or make your first sale.</p>
                    <a href="create.php" class="add-btn" style="margin-top: 20px;">＋ Create Your First Sale</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Report Summary -->
        <?php if(count($sales) > 0): ?>
            <div style="margin-top: 20px; color: #666; font-size: 14px; text-align: right;">
                Report generated on: <?php echo date('d M Y, h:i A'); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set max end date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('end_date').max = today;
            document.getElementById('start_date').max = today;
            
            // Set default end date to today if not set
            if(!document.getElementById('end_date').value) {
                document.getElementById('end_date').value = today;
            }
            
            // Set default start date to first of current month if not set
            if(!document.getElementById('start_date').value) {
                const firstDay = new Date();
                firstDay.setDate(1);
                document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>