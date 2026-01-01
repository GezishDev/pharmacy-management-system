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

// Check if supplier exists
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if(!$supplier) {
    header('Location: index.php');
    exit;
}

// Check if supplier has medicines (prevent deletion if it has medicines)
$medStmt = $pdo->prepare("SELECT COUNT(*) as count FROM medicines WHERE supplier_id = ?");
$medStmt->execute([$id]);
$medicineCount = $medStmt->fetch()['count'];

// Handle deletion
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['cancel'])) {
        header('Location: index.php');
        exit;
    }
    
    if(isset($_POST['confirm'])) {
        if($medicineCount > 0) {
            // Option 1: Set medicines to NULL supplier
            if(isset($_POST['handle_medicines']) && $_POST['handle_medicines'] === 'nullify') {
                $updateStmt = $pdo->prepare("UPDATE medicines SET supplier_id = NULL WHERE supplier_id = ?");
                $updateStmt->execute([$id]);
                
                // Now delete the supplier
                $deleteStmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                $deleteStmt->execute([$id]);
                
                header('Location: index.php?deleted=1&handled=nullified');
                exit;
            }
            // Option 2: Cancel deletion
            else {
                $error = "Cannot delete supplier because it has associated medicines.";
            }
        } else {
            // Delete the supplier
            $deleteStmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            header('Location: index.php?deleted=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Supplier - Pharmacy Management</title>
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
        }
        
        .supplier-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .supplier-details strong {
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
        
        .option-group {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .option-group label {
            display: block;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .option-group input[type="radio"] {
            margin-right: 10px;
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
        
        <h1>Delete Supplier</h1>
        
        <div class="supplier-details">
            <p><strong>Supplier ID:</strong> #<?php echo $supplier['id']; ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($supplier['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($supplier['email'] ?? 'N/A'); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></p>
        </div>
        
        <?php if($medicineCount > 0): ?>
            <div class="warning-box">
                <strong>⚠️ Warning:</strong> This supplier has <?php echo $medicineCount; ?> medicine(s) associated with it.
            </div>
            
            <p>What would you like to do with the associated medicines?</p>
            
            <form method="POST" action="">
                <div class="option-group">
                    <label>
                        <input type="radio" name="handle_medicines" value="nullify" required>
                        Remove supplier association (set supplier to "None" for these medicines)
                    </label>
                    <label>
                        <input type="radio" name="handle_medicines" value="cancel" required>
                        Cancel deletion (keep supplier and medicines as is)
                    </label>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="cancel" value="1" class="btn-cancel">Cancel</button>
                    <button type="submit" name="confirm" value="1" class="btn-delete">Proceed with Deletion</button>
                </div>
            </form>
        <?php else: ?>
            <p>Are you sure you want to delete this supplier? This action cannot be undone.</p>
            
            <form method="POST" action="">
                <div class="btn-group">
                    <button type="submit" name="cancel" value="1" class="btn-cancel">Cancel</button>
                    <button type="submit" name="confirm" value="1" class="btn-delete">Delete Permanently</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>