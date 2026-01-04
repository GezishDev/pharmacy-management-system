<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Only admin can delete sales
if($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Fetch sale details
$stmt = $pdo->prepare("SELECT s.*, m.name as medicine_name FROM sales s 
                      LEFT JOIN medicines m ON s.medicine_id = m.id 
                      WHERE s.id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if(!$sale) {
    header('Location: index.php');
    exit;
}

// Handle deletion
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['cancel'])) {
        header('Location: index.php');
        exit;
    }
    
    if(isset($_POST['confirm'])) {
        try {
            $pdo->beginTransaction();
            
            // Restore stock
            if(!empty($sale['medicine_id'])) {
                $restoreStmt = $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?");
                $restoreStmt->execute([$sale['quantity_sold'], $sale['medicine_id']]);
            }
            
            // Delete the sale
            $deleteStmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            $pdo->commit();
            
            header('Location: index.php?deleted=1');
            exit;
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = "Deletion failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Sale - Pharmacy Management</title>
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
            max-width: 600px;
            width: 100%;
        }
        
        .warning-icon {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .delete-container h1 {
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .delete-container p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
            text-align: center;
        }
        
        .sale-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .sale-details strong {
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
        
        .warning-box {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
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
            border: none;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .restore-info {
            color: #28a745;
            font-weight: 600;
            margin-top: 10px;
        }
        /* ===== MOBILE RESPONSIVE FIX (DELETE PAGE) ===== */
@media (max-width: 768px) {

    body {
        padding: 10px;
    }

    .delete-container {
        padding: 25px 20px;
        border-radius: 12px;
    }

    .warning-icon {
        font-size: 48px;
    }

    .sale-details {
        padding: 15px;
        font-size: 14px;
    }

    .sale-details p {
        margin-bottom: 8px;
    }

    /* Stack buttons vertically on mobile */
    .btn-group {
        flex-direction: column;
    }

    .btn-delete,
    .btn-cancel {
        width: 100%;
        padding: 16px;
        font-size: 16px;
        border-radius: 12px;
    }

    .btn-delete {
        background: linear-gradient(135deg, #ff3b3b, #c70000);
    }

    .btn-cancel {
        background: linear-gradient(135deg, #6c757d, #495057);
    }

    .btn-delete:active,
    .btn-cancel:active {
        transform: scale(0.97);
    }
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
        
        <h1>Delete Sale Record</h1>
        
        <div class="warning-box">
            <strong>⚠️ Important:</strong> Deleting this sale will restore <?php echo $sale['quantity_sold']; ?> unit(s) of stock.
        </div>
        
        <div class="sale-details">
            <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($sale['invoice_number']); ?></p>
            <p><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($sale['sale_date'])); ?></p>
            <?php if(!empty($sale['customer_name'])): ?>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?></p>
            <?php endif; ?>
            <?php if(!empty($sale['medicine_name'])): ?>
                <p><strong>Medicine:</strong> <?php echo htmlspecialchars($sale['medicine_name']); ?></p>
            <?php endif; ?>
            <p><strong>Quantity:</strong> <?php echo htmlspecialchars($sale['quantity_sold']); ?></p>
            <p><strong>Total Amount:</strong> Br<?php echo number_format($sale['total_price'], 2); ?></p>
            
            <div class="restore-info">
                ⚠️ <?php echo $sale['quantity_sold']; ?> unit(s) will be restored to stock.
            </div>
        </div>
        
        <p>Are you sure you want to delete this sale record? This action cannot be undone.</p>
        
        <form method="POST" action="">
            <div class="btn-group">
                <button type="submit" name="cancel" value="1" class="btn-cancel">Cancel</button>
                <button type="submit" name="confirm" value="1" class="btn-delete">Delete Permanently</button>
            </div>
        </form>
    </div>
</body>
</html>