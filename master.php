<?php
// Database connection
$host = 'sql109.infinityfree.com';
$dbname = 'if0_38595302_ecommerce_app';
$username = 'if0_38595302';
$password = '6uK4j3ta2qQr';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Define available categories
$categories = [
 
    // Fashion
    "Men's Clothing",
    "Women's Clothing",
    "Kids' Clothing",
    'Shoes',
    'Bags & Wallets',
    'Jewelry',
    'Watches',
    'Sunglasses',
    
    // Home & Kitchen
    'Furniture',
    'Home Appliances',
    'Kitchen Appliances',
    'Bedding',
    'Home Decor',
    'Lighting',
    'Cookware',
    'Tableware',
    
    // Health & Beauty
    'Skincare',
    'Hair Care',
    'Makeup',
    'Fragrances',
    'Personal Care',
    'Health Care',
    'Vitamins',
    
    // Sports & Outdoors
    'Exercise Equipment',
    'Sports Apparel',
    'Outdoor Gear',
    'Cycling',
    'Team Sports',
    'Fitness Accessories',
    
    // Agricultural Produce
    'Fresh Vegetables',
    'Fresh Fruits',
    'Grains & Cereals',
    'Legumes & Pulses',
    'Tubers & Roots',
    'Dairy Products',
    'Poultry & Eggs',
    'Livestock',
    'Seeds & Seedlings',
    'Fertilizers',
    'Farm Tools',
    'Organic Produce',
    'Processed Farm Products',
    'Herbs & Spices',
    'Flowers & Plants',
    
    // Other Categories
    'Books',
    'Toys & Games',
    'Baby Products',
    'Pet Supplies',
    'Automotive',
    'Tools & Home Improvement',
    'Office Supplies',
    'Other'
];

// Handle CRUD operations
$message = '';
$item = null;

// Create or Update Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $seller = trim($_POST['seller']);
    $category = $_POST['category'] ?? 'Other';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    // Validate inputs
    if (empty($title) || empty($description) || $price <= 0 || empty($category)) {
        $message = '<div class="alert alert-error">Please fill all required fields correctly</div>';
    } else {
        try {
            // Handle image upload
            $imagePath = $_POST['existing_image'] ?? '';
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                // Validate image
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($fileInfo, $_FILES['image']['tmp_name']);
                
                if (in_array($mimeType, $allowedTypes)) {
                    $filename = uniqid() . '_' . basename($_FILES['image']['name']);
                    $targetPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        // Delete old image if exists
                        if (!empty($imagePath) && file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                        $imagePath = $targetPath;
                    }
                }
            }

            // Save to database
            if ($id) {
                // Update existing item
                $stmt = $pdo->prepare("UPDATE items SET title = ?, description = ?, price = ?, seller = ?, image_path = ?, category = ? WHERE id = ?");
                $stmt->execute([$title, $description, $price, $seller, $imagePath, $category, $id]);
                $message = '<div class="alert alert-success">Item updated successfully</div>';
            } else {
                // Create new item
                $stmt = $pdo->prepare("INSERT INTO items (title, description, price, seller, image_path, category, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$title, $description, $price, $seller, $imagePath, $category]);
                $message = '<div class="alert alert-success">Item added successfully</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Delete Item
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Get image path first
        $stmt = $pdo->prepare("SELECT image_path FROM items WHERE id = ?");
        $stmt->execute([$id]);
        $itemToDelete = $stmt->fetch();
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
        
        // Delete image file if exists
        if ($itemToDelete && !empty($itemToDelete['image_path']) && file_exists($itemToDelete['image_path'])) {
            unlink($itemToDelete['image_path']);
        }
        
        $message = '<div class="alert alert-success">Item deleted successfully</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Error deleting item: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Edit Item - Load data
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
}

// Search functionality
$searchQuery = $_GET['search'] ?? '';
$whereClause = '';
$params = [];

if (!empty($searchQuery)) {
    $whereClause = "WHERE title LIKE :search OR description LIKE :search OR seller LIKE :search OR category LIKE :search";
    $params[':search'] = '%' . $searchQuery . '%';
}

// Fetch items with search filter
$sql = "SELECT * FROM items $whereClause ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}

$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MarketPlace</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6200ee;
            --primary-dark: #3700b3;
            --error-color: #b00020;
            --success-color: #00c853;
            --surface-color: #ffffff;
            --background-color: #f5f5f5;
            --on-primary: #ffffff;
            --on-surface: #000000;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
        }
        
        body {
            background-color: var(--background-color);
            color: var(--on-surface);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-header {
            background-color: var(--primary-color);
            color: var(--on-primary);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card {
            background-color: var(--surface-color);
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 16px;
            border-bottom: 1px solid #eee;
        }
        
        .card-body {
            padding: 16px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1.25px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            outline: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--on-primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn-secondary {
            background-color: #666;
            color: white;
        }
        
        .btn-error {
            background-color: var(--error-color);
            color: white;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            font-weight: 500;
            color: #666;
        }
        
        .table tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .category-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 12px;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .alert {
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: var(--success-color);
        }
        
        .alert-error {
            background-color: #ffebee;
            color: var(--error-color);
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            display: block;
            margin-top: 10px;
            border-radius: 4px;
        }
        
        /* Search styles */
        .search-container {
            display: flex;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .search-input {
            flex-grow: 1;
            padding: 10px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .search-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .search-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .clear-search {
            margin-left: 10px;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .search-results-info {
            margin-bottom: 16px;
            color: #666;
            display: flex;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .table {
                display: block;
                overflow-x: auto;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .search-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }


        /* Category badge colors */
.category-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    margin-top: 5px;
    color: white;
}

/* Electronics */
[data-category="Smartphones"],
[data-category="Laptops"],
[data-category="Tablets"],
[data-category="Cameras"],
[data-category="Televisions"],
[data-category="Headphones"],
[data-category="Speakers"],
[data-category="Smart Watches"],
[data-category="Gaming Consoles"],
[data-category="Computer Accessories"] {
    background-color: #3f51b5;
}

/* Fashion */
[data-category*="Clothing"],
[data-category="Shoes"],
[data-category="Bags & Wallets"],
[data-category="Jewelry"],
[data-category="Watches"],
[data-category="Sunglasses"] {
    background-color: #e91e63;
}

/* Home & Kitchen */
[data-category="Furniture"],
[data-category="Home Appliances"],
[data-category="Kitchen Appliances"],
[data-category="Bedding"],
[data-category="Home Decor"],
[data-category="Lighting"],
[data-category="Cookware"],
[data-category="Tableware"] {
    background-color: #ff5722;
}

/* Health & Beauty */
[data-category="Skincare"],
[data-category="Hair Care"],
[data-category="Makeup"],
[data-category="Fragrances"],
[data-category="Personal Care"],
[data-category="Health Care"],
[data-category="Vitamins"] {
    background-color: #9c27b0;
}

/* Sports & Outdoors */
[data-category="Exercise Equipment"],
[data-category="Sports Apparel"],
[data-category="Outdoor Gear"],
[data-category="Cycling"],
[data-category="Team Sports"],
[data-category="Fitness Accessories"] {
    background-color: #009688;
}

/* Agricultural Produce */
[data-category="Fresh Vegetables"],
[data-category="Fresh Fruits"],
[data-category="Grains & Cereals"],
[data-category="Legumes & Pulses"],
[data-category="Tubers & Roots"],
[data-category="Dairy Products"],
[data-category="Poultry & Eggs"],
[data-category="Livestock"],
[data-category="Seeds & Seedlings"],
[data-category="Fertilizers"],
[data-category="Farm Tools"],
[data-category="Organic Produce"],
[data-category="Processed Farm Products"],
[data-category="Herbs & Spices"],
[data-category="Flowers & Plants"] {
    background-color: #4caf50;
}

/* Other Categories */
[data-category="Books"],
[data-category="Toys & Games"],
[data-category="Baby Products"],
[data-category="Pet Supplies"],
[data-category="Automotive"],
[data-category="Tools & Home Improvement"],
[data-category="Office Supplies"],
[data-category="Other"] {
    background-color: #607d8b;
}
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <h1>MarketPlace Admin Dashboard</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="material-icons">store</i>
                <span>View Store</span>
            </a>
        </div>
        
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header">
                <h2><?php echo isset($item) ? 'Edit Item' : 'Add New Item'; ?></h2>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php if (isset($item)): ?>
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($item['image_path'] ?? ''); ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?php 
                            echo htmlspecialchars($item['description'] ?? ''); 
                        ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="price" class="form-label">Price (UGX) *</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" 
                               value="<?php echo htmlspecialchars($item['price'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="seller" class="form-label">Seller</label>
                        <input type="text" class="form-control" id="seller" name="seller" 
                               value="<?php echo htmlspecialchars($item['seller'] ?? 'Anonymous'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category" class="form-label">Category *</label>
                        <select class="form-control" id="category" name="category" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php echo (isset($item) && $item['category'] === $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="image" class="form-label">Item Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        
                        <?php if (isset($item) && !empty($item['image_path'])): ?>
                            <div class="mt-2">
                                <p>Current Image:</p>
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" class="preview-image">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group" style="display: flex; gap: 10px;">
                        <button type="submit" name="save_item" class="btn btn-primary">
                            <i class="material-icons">save</i>
                            <?php echo isset($item) ? 'Update Item' : 'Add Item'; ?>
                        </button>
                        
                        <?php if (isset($item)): ?>
                            <a href="master.php" class="btn btn-secondary">
                                <i class="material-icons">cancel</i>
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>All Items</h2>
                    <form method="GET" class="search-container">
                        <input type="text" name="search" class="search-input" 
                               placeholder="Search items..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button type="submit" class="search-btn">
                            <i class="material-icons">search</i>
                            <span>Search</span>
                        </button>
                        <?php if (!empty($searchQuery)): ?>
                            <a href="master.php" class="clear-search">
                                <i class="material-icons">clear</i>
                                <span>Clear</span>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($searchQuery)): ?>
                    <div class="search-results-info">
                        <i class="material-icons">search</i>
                        <span>Showing results for: "<?php echo htmlspecialchars($searchQuery); ?>" (<?php echo count($items); ?> items found)</span>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($items)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="material-icons" style="font-size: 48px; color: #999;">inventory</i>
                        <h3>No items found</h3>
                        <p><?php echo empty($searchQuery) ? 'No items have been added yet' : 'No items match your search'; ?></p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Price</th>
                                <th>Category</th>
                                <th>Seller</th>
                                <th>Views</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" class="item-image">
                                        <?php else: ?>
                                            <i class="material-icons">image</i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['title']); ?>
                                        <div class="category-badge">
                                            <?php echo htmlspecialchars($item['category'] ?? 'Other'); ?>
                                        </div>
                                    </td>
                                    <td>UGX <?php echo number_format($item['price']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category'] ?? 'Other'); ?></td>
                                    <td><?php echo htmlspecialchars($item['seller']); ?></td>
                                    <td><?php echo $item['views']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($item['created_at'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="?edit=<?php echo $item['id']; ?>" class="btn btn-primary" style="padding: 8px;">
                                                <i class="material-icons">edit</i>
                                            </a>
                                            <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-error" style="padding: 8px;"
                                               onclick="return confirm('Are you sure you want to delete this item?')">
                                                <i class="material-icons">delete</i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Image preview functionality
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    let preview = document.querySelector('.preview-image');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.className = 'preview-image';
                        document.querySelector('.form-group:last-child').appendChild(preview);
                    }
                    preview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>