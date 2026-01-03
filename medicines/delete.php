<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Check if medicine exists
$stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ?");
$stmt->execute([$id]);
$medicine = $stmt->fetch();

if(!$medicine) {
    header('Location: index.php');
    exit;
}

// Check if medicine has sales (prevent deletion if it has sales)
$salesStmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE medicine_id = ?");
$salesStmt->execute([$id]);
$salesCount = $salesStmt->fetch()['count'];

// Handle deletion
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if($salesCount > 0) {
        // If medicine has sales, don't delete, just show error
        $error = "Cannot delete medicine because it has associated sales records.";
    } else {
        // Delete the medicine
        $deleteStmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
        $deleteStmt->execute([$id]);
        
        header('Location: index.php?deleted=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Medicine - Pharmacy Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .delete-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .warning-icon {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .delete-container h1 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .delete-container p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .medicine-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: left;
        }
        
        .medicine-details strong {
            color: #333;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-delete, .btn-cancel {
            flex: 1;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .sales-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="delete-container">
        <div class="warning-icon">⚠️</div>
        
        <?php if(isset($error)): ?>
            <div class="alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <h1>Delete Medicine</h1>
        
        <?php if($salesCount > 0): ?>
            <div class="sales-warning">
                <strong>Warning:</strong> This medicine has <?php echo $salesCount; ?> sales record(s).<br>
                Medicines with sales records cannot be deleted to maintain data integrity.
            </div>
        <?php endif; ?>
        
        <div class="medicine-details">
            <p><strong>Medicine ID:</strong> #<?php echo $medicine['id']; ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($medicine['name']); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($medicine['category'] ?? 'N/A'); ?></p>
            <p><strong>Current Stock:</strong> <?php echo htmlspecialchars($medicine['quantity']); ?></p>
            <p><strong>Price:</strong> Br<?php echo htmlspecialchars(number_format($medicine['price'], 2)); ?></p>
        </div>
        
        <?php if($salesCount > 0): ?>
            <p>This action is blocked because the medicine has sales history.</p>
            <a href="index.php" class="btn-cancel" style="display: block; margin-top: 20px;">Back to Medicines</a>
        <?php else: ?>
            <p>Are you sure you want to delete this medicine? This action cannot be undone.</p>
            
            <form method="POST" action="">
                <div class="btn-group">
                    <a href="index.php" class="btn-cancel">Cancel</a>
                    <button type="submit" name="confirm" value="1" class="btn-delete">Delete Permanently</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>