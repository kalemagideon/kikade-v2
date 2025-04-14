<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


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
// Function to compress and resize image
/**
 * Compresses and resizes an image
 */
function compressImage($source, $destination, $quality) {
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
    } elseif ($info['mime'] == 'image/gif') {
        $image = imagecreatefromgif($source);
    } else {
        return false;
    }
    
    // Calculate new dimensions (max width: 800px)
    $maxWidth = 800;
    $width = imagesx($image);
    $height = imagesy($image);
    
    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = $height * $ratio;
        
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $image = $newImage;
    }
    
    // Save the compressed image
    if ($info['mime'] == 'image/jpeg') {
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        imagepng($image, $destination, round(9 * $quality / 100)); // PNG quality is 0-9
    } elseif ($info['mime'] == 'image/gif') {
        imagegif($image, $destination);
    }
    
    imagedestroy($image);
    return true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $seller = $_POST['seller'] ?? 'Anonymous';
    $category = $_POST['category'] ?? 'Other'; // Get the category
    
    // Validate inputs
    $errors = [];
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (!is_numeric($price) || $price <= 0) $errors[] = "Valid price is required";
    if (empty($category)) $errors[] = "Category is required";
    
    // Handle image upload
// Handle image upload
$imagePath = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate image type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['image']['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = "Only JPG, PNG, and GIF files are allowed";
    } else {
        $filename = uniqid() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $filename;
        
        // Compress and resize the image
        if (compressImage($_FILES['image']['tmp_name'], $targetPath, 50)) {
            $imagePath = $targetPath;
        } else {
            $errors[] = "Failed to process image";
        }
    }
}
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO items (title, description, price, seller, image_path, views, category, created_at) 
                          VALUES (:title, :description, :price, :seller, :image_path, 0, :category, NOW())");
    $stmt->execute([
        ':title' => htmlspecialchars($title),
        ':description' => htmlspecialchars($description),
        ':price' => (float)$price,
        ':seller' => htmlspecialchars($seller),
        ':image_path' => $imagePath,
        ':category' => htmlspecialchars($category)
            ]);
            
            // Redirect to prevent form resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // Display errors if any
    if (!empty($errors)) {
        echo '<div class="error-container" style="background: #ffebee; color: #b71c1c; padding: 15px; margin: 20px; border-radius: 4px;">';
        echo '<h4 style="margin-top: 0;">Errors:</h4>';
        echo '<ul style="margin-bottom: 0;">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}

// Handle view count increment
$itemDetails = null;
if (isset($_GET['view_item'])) {
    $itemId = (int)$_GET['view_item'];
    
    // Increment view count
    $stmt = $pdo->prepare("UPDATE items SET views = views + 1 WHERE id = :id");
    $stmt->execute([':id' => $itemId]);
    
    // Fetch item details
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $itemId]);
    $itemDetails = $stmt->fetch(PDO::FETCH_ASSOC);
}


// Fetch all items
// Replace your existing items fetch code with this:
$searchQuery = $_GET['search_query'] ?? '';
$whereClause = '';
$params = [];

if (!empty($searchQuery)) {
    $whereClause = "WHERE title LIKE :search OR description LIKE :search OR seller LIKE :search";
    $params[':search'] = '%' . $searchQuery . '%';
}

$sql = "SELECT * FROM items $whereClause ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}

$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarketPlace</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6200ee;
            --primary-dark: #3700b3;
            --secondary-color: #03dac6;
            --error-color: #b00020;
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
        
        header {
    height: 68px; /* Add this line */
    background-color: var(--primary-color);
    color: var(--on-primary);
    padding: 0 20px; /* Changed from padding: 20px */
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    position: sticky;
    top: 0;
    z-index: 100;
    display: flex;
    align-items: center;
}

.header-content {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Mobile header height */
@media (max-width: 600px) {
    header {
        height: 56px;
        padding: 0 12px;
    }
}
        
        .logo {
            font-size: 24px;
            font-weight: 500;
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
        
        .btn-flat {
            background-color: transparent;
            color: var(--primary-color);
        }
        
        .btn-flat:hover {
            background-color: rgba(98, 0, 238, 0.08);
        }
        
        .card {
            background-color: var(--surface-color);
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            transition: all 0.3s cubic-bezier(.25,.8,.25,1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card:hover {
            box-shadow: 0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22);
        }
        
        .card-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .card-content {
            padding: 16px;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .card-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .card-text {
            margin-bottom: 16px;
        }
        
        .card-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 16px;
            border-top: 1px solid #eee;
        }
        
        .price {
            font-size: 18px;
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: var(--surface-color);
            border-radius: 4px;
            width: 90%;
            max-width: 500px;
            padding: 20px;
            box-shadow: 0 11px 15px -7px rgba(0,0,0,0.2), 
                         0 24px 38px 3px rgba(0,0,0,0.14), 
                         0 9px 46px 8px rgba(0,0,0,0.12);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 500;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
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
        
        .view-count {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
        }
        
        .view-count i {
            margin-right: 4px;
            font-size: 18px;
        }
        
        @media (max-width: 600px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .modal {
    display: none; /* This should already be there */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    overflow-y: auto; /* Allow scrolling if content is too tall */
}


        .detail-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            z-index: 1001;
            overflow-y: auto;
            padding: 20px;
        }
        
        .detail-modal-content {
            background-color: white;
            border-radius: 8px;
            max-width: 800px;
            margin: 20px auto;
            box-shadow: 0 11px 15px -7px rgba(0,0,0,0.2), 
                         0 24px 38px 3px rgba(0,0,0,0.14), 
                         0 9px 46px 8px rgba(0,0,0,0.12);
        }
        
        .detail-modal-header {
            padding: 16px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .detail-modal-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
        }
        
        .detail-modal-image {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .detail-modal-title {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .detail-modal-price {
            font-size: 28px;
            color: var(--primary-color);
            margin: 10px 0;
            font-weight: 500;
        }
        
        .detail-modal-seller {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: #666;
        }
        
        .detail-modal-description {
            line-height: 1.6;
            margin-bottom: 20px;
            white-space: pre-line;
        }
        
        
        
        @media (min-width: 768px) {
            .detail-modal-body {
                flex-direction: row;
            }
            
            .detail-modal-image-container {
                flex: 1;
                padding-right: 20px;
            }
            
            .detail-modal-info {
                flex: 1;
            }
        }

        .price {
    font-size: 18px;
    font-weight: 500;
    color: var(--primary-color);
    white-space: nowrap; /* Prevent wrapping */
}

.detail-modal-price {
    font-size: 28px;
    color: var(--primary-color);
    margin: 10px 0;
    font-weight: 500;
    white-space: nowrap; /* Prevent wrapping */
}

.search-section {
    background-color: white;
    padding: 15px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    position: sticky;
    top: 68px; /* Height of your header */
    z-index: 90;
}

.search-container {
    max-width: 100%;
    margin: 0 auto;
}

.search-form {
    display: flex;
    align-items: center;
}

.search-input {
    position: relative;
    flex-grow: 1;
    background-color: #f5f5f5;
    border-radius: 4px;
}

/* For mobile responsiveness */
@media (max-width: 600px) {
    .search-section {
        top: 56px; /* Smaller header height on mobile */
        padding: 10px 0;
    }
    
    .search-form {
        flex-direction: row;
    }
    
    .search-input input {
        padding: 8px 16px;
        padding-right: 36px;
    }
}


.search-results-count {
    margin: 10px 0;
    color: #666;
    font-size: 14px;
}


.item-category {
    position: absolute;
    top: 10px;
    left: 10px;
    background: var(--primary-color);
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    z-index: 2;
}


/* Loading spinner styles */
.loading-spinner {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2000;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 600px) {
    .spinner {
        width: 40px;
        height: 40px;
        border-width: 4px;
    }
}

/* Category dropdown styling */
select.form-control {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 1em;
    padding-right: 2.5em;
}

optgroup {
    font-weight: bold;
    font-style: normal;
    color: var(--primary-color);
}

option {
    padding: 8px 12px;
    font-weight: normal;
}

select.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(98, 0, 238, 0.2);
}
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">MarketPlace</div>
            <button id="openModalBtn" class="btn btn-primary">
                <i class="material-icons">add</i>
                <span>Post Item</span>
            </button>

            

        </div>
    </header>
    
    <!-- Search Section -->
    <div class="search-section">
    <div class="container">
        <div class="search-container">
            <form id="searchForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" class="search-form">
                <div class="search-input">
                    <input type="text" name="search_query" placeholder="Tap here to Search items..." 
                           value="<?php echo htmlspecialchars($_GET['search_query'] ?? ''); ?>"
                           style="width: 100%; padding: 10px 16px; padding-right: 40px; border: none; border-radius: 4px;">
                    <button type="submit" style="position: absolute; right: 0; top: 0; height: 100%; background: none; border: none; cursor: pointer;">
                        <i class="material-icons" style="color: #666;">search</i>
                    </button>
                </div>
                <?php if(isset($_GET['search_query']) && !empty($_GET['search_query'])): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-flat" style="margin-left: 8px;">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

    <div class="container">

                <!-- Add this right before the grid starts -->
                <?php if (!empty($searchQuery)): ?>
                    <div class="search-results-count" style="margin: 20px 0; padding: 10px; background: #f5f5f5; border-radius: 4px;">
                        <i class="material-icons" style="vertical-align: middle;">search</i>
                        Found <?php echo count($items); ?> items matching "<?php echo htmlspecialchars($searchQuery); ?>"
                    </div>
                <?php endif; ?>

                <!-- Your existing grid starts here -->
                <div class="grid">
                    <?php foreach ($items as $item): ?>
                        <div class="card" data-id="<?php echo $item['id']; ?>">
    <div style="position: relative;"> <!-- Add this wrapper div -->
        <?php if ($item['image_path']): ?>
            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="card-image">
        <?php else: ?>
            <div style="height: 200px; background-color: #eee; display: flex; align-items: center; justify-content: center;">
                <i class="material-icons" style="font-size: 48px; color: #999;">photo</i>
            </div>
        <?php endif; ?>
        <!-- Category badge - moved inside the wrapper div -->
        <div class="item-category">
            <?php echo htmlspecialchars($item['category'] ?? 'Other'); ?>
        </div>
    </div>
    <div class="card-content">
        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
        <p class="card-subtitle">Sold by: <?php echo htmlspecialchars($item['seller']); ?></p>
    </div>
    <div class="card-actions">
        <span class="price">Ugshs <?php echo number_format($item['price']); ?></span>
        <div class="view-count">
            <i class="material-icons">visibility</i>
            <span><?php echo $item['views']; ?></span>
        </div>
    </div>
</div>

                    <?php endforeach; ?>
                </div>

        
            
            <?php if (empty($items)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <i class="material-icons" style="font-size: 48px; color: #999;">inventory</i>
                    <h3>No items for sale yet</h3>
                    <p>Be the first to post an item!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal for posting new item -->
<!-- Replace your existing modal code with this -->
<div id="postModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Post an Item for Sale</h3>
            <button class="close-btn">&times;</button>
        </div>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title" class="form-label">Title*</label>
                <input type="text" id="title" name="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="description" class="form-label">Description*</label>
                <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="price" class="form-label">Price (Ugshs)*</label>
                <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
            </div>
            <div class="form-group">
    <label for="category" class="form-label">Category*</label>
    <select id="category" name="category" class="form-control" required>
        <option value="">Select a category</option>
        
        <!-- Electronics -->
        <optgroup label="Electronics">
            <option value="Smartphones">Smartphones</option>
            <option value="Laptops">Laptops</option>
            <option value="Tablets">Tablets</option>
            <option value="Cameras">Cameras</option>
            <option value="Televisions">Televisions</option>
            <option value="Headphones">Headphones</option>
            <option value="Speakers">Speakers</option>
            <option value="Smart Watches">Smart Watches</option>
            <option value="Gaming Consoles">Gaming Consoles</option>
            <option value="Computer Accessories">Computer Accessories</option>
        </optgroup>
        
        <!-- Fashion -->
        <optgroup label="Fashion">
            <option value="Men's Clothing">Mens Clothing</option>
            <option value="Women's Clothing">Womens Clothing</option>
            <option value="Kids' Clothing">Kids Clothing</option>
            <option value="Shoes">Shoes</option>
            <option value="Bags & Wallets">Bags & Wallets</option>
            <option value="Jewelry">Jewelry</option>
            <option value="Watches">Watches</option>
            <option value="Sunglasses">Sunglasses</option>
        </optgroup>
        
        <!-- Home & Kitchen -->
        <optgroup label="Home & Kitchen">
            <option value="Furniture">Furniture</option>
            <option value="Home Appliances">Home Appliances</option>
            <option value="Kitchen Appliances">Kitchen Appliances</option>
            <option value="Bedding">Bedding</option>
            <option value="Home Decor">Home Decor</option>
            <option value="Lighting">Lighting</option>
            <option value="Cookware">Cookware</option>
            <option value="Tableware">Tableware</option>
        </optgroup>
        

        <!-- Agricultural Produce -->
            <optgroup label="Agricultural Produce">
                <option value="Fresh Vegetables">Fresh Vegetables</option>
                <option value="Fresh Fruits">Fresh Fruits</option>
                <option value="Grains & Cereals">Grains & Cereals</option>
                <option value="Legumes & Pulses">Legumes & Pulses</option>
                <option value="Tubers & Roots">Tubers & Roots</option>
                <option value="Dairy Products">Dairy Products</option>
                <option value="Poultry & Eggs">Poultry & Eggs</option>
                <option value="Livestock">Livestock</option>
                <option value="Seeds & Seedlings">Seeds & Seedlings</option>
                <option value="Fertilizers">Fertilizers</option>
                <option value="Farm Tools">Farm Tools</option>
                <option value="Organic Produce">Organic Produce</option>
                <option value="Processed Farm Products">Processed Farm Products</option>
                <option value="Herbs & Spices">Herbs & Spices</option>
                <option value="Flowers & Plants">Flowers & Plants</option>
            </optgroup>

            
        <!-- Health & Beauty -->
        <optgroup label="Health & Beauty">
            <option value="Skincare">Skincare</option>
            <option value="Hair Care">Hair Care</option>
            <option value="Makeup">Makeup</option>
            <option value="Fragrances">Fragrances</option>
            <option value="Personal Care">Personal Care</option>
            <option value="Health Care">Health Care</option>
            <option value="Vitamins">Vitamins</option>
        </optgroup>
        
        <!-- Sports & Outdoors -->
        <optgroup label="Sports & Outdoors">
            <option value="Exercise Equipment">Exercise Equipment</option>
            <option value="Sports Apparel">Sports Apparel</option>
            <option value="Outdoor Gear">Outdoor Gear</option>
            <option value="Cycling">Cycling</option>
            <option value="Team Sports">Team Sports</option>
            <option value="Fitness Accessories">Fitness Accessories</option>
        </optgroup>
        
        <!-- Other Categories -->
        <optgroup label="Other Categories">
            <option value="Books">Books</option>
            <option value="Toys & Games">Toys & Games</option>
            <option value="Baby Products">Baby Products</option>
            <option value="Pet Supplies">Pet Supplies</option>
            <option value="Automotive">Automotive</option>
            <option value="Tools & Home Improvement">Tools & Home Improvement</option>
            <option value="Office Supplies">Office Supplies</option>
            <option value="Other">Other</option>
        </optgroup>
    </select>
</div>
            <div class="form-group">
                <label for="seller" class="form-label">Your Name</label>
                <input type="text" id="seller" name="seller" class="form-control" placeholder="Anonymous">
            </div>
            <div class="form-group">
                <label for="image" class="form-label">Item Image</label>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <small class="form-text">Images will be automatically resized (max width: 800px)</small>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-flat close-btn">Cancel</button>
                <button type="submit" name="submit" class="btn btn-primary" style="margin-left: 8px;">
                    <i class="material-icons">check</i> Post Item
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Item details modal -->
<div id="itemDetailModal" class="detail-modal">
        <div class="detail-modal-content">
            <div class="detail-modal-header">
                <h2 class="detail-modal-title"><?php echo htmlspecialchars($itemDetails['title'] ?? ''); ?></h2>
                <button class="close-detail-modal">&times;</button>
            </div>
            <div class="detail-modal-body">
                <?php if (!empty($itemDetails['image_path'])): ?>
                <div class="detail-modal-image-container">
                    <img src="<?php echo htmlspecialchars($itemDetails['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($itemDetails['title']); ?>" 
                         class="detail-modal-image">
                </div>
                <?php endif; ?>
                <div class="detail-modal-info">
                <div class="detail-modal-price">Ugshs <?php echo number_format($itemDetails['price'] ?? 0); ?></div>
                    <div class="detail-modal-seller">
                        <i class="material-icons">person</i>
                        <span>Posted by: <?php echo htmlspecialchars($itemDetails['seller'] ?? 'Anonymous'); ?></span>
                    </div>
                    <div class="detail-modal-views">
                        <i class="material-icons">visibility</i>
                        <span><?php echo $itemDetails['views'] ?? 0; ?> views</span>
                    </div>
                    <div class="detail-modal-date">
                        <i class="material-icons">schedule</i>
                        <span>Posted on: <?php echo date('M j, Y', strtotime($itemDetails['created_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="detail-modal-description">
                        <?php echo nl2br(htmlspecialchars($itemDetails['description'] ?? '')); ?>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <div id="loadingSpinner" class="loading-spinner" style="display: none;">
    <div class="spinner"></div>
    </div>
              
<script>
document.addEventListener('DOMContentLoaded', addCategoryIcons);

    // Modal functionality - fixed version
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('postModal');
        const openModalBtn = document.getElementById('openModalBtn');
        const closeModalBtns = document.querySelectorAll('.close-btn');
        
        // Open modal
        if (openModalBtn) {
            openModalBtn.addEventListener('click', (e) => {
                e.preventDefault();
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            });
        }
        
        // Close modal
        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                modal.style.display = 'none';
                document.body.style.overflow = ''; // Re-enable scrolling
            });
        });
        
        // Close when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = ''; // Re-enable scrolling
            }
        });
        
        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                const title = document.getElementById('title').value.trim();
                const description = document.getElementById('description').value.trim();
                const price = document.getElementById('price').value;
                
                if (!title || !description || !price) {
                    e.preventDefault();
                    alert('Please fill in all required fields');
                }
            });
        }
    });



    // Item Detail Modal functionality
    document.addEventListener('DOMContentLoaded', function() {
            // Open detail modal if URL has view_item parameter
            <?php if ($itemDetails): ?>
                document.getElementById('itemDetailModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            <?php endif; ?>
            
            // Close detail modal
            document.querySelectorAll('.close-detail-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('itemDetailModal').style.display = 'none';
                    document.body.style.overflow = '';
                    // Remove the view_item parameter from URL
                    history.replaceState(null, null, window.location.pathname);
                });
            });
            
            // Close when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === document.getElementById('itemDetailModal')) {
                    document.getElementById('itemDetailModal').style.display = 'none';
                    document.body.style.overflow = '';
                    history.replaceState(null, null, window.location.pathname);
                }
            });
            
            // Make card clicks open the detail modal
            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on a button or link inside the card
                    if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') return;
                    
                    const itemId = this.dataset.id;
                    if (itemId) {
                        window.location.href = '?view_item=' + itemId;
                    }
                });
            });
        });


        // Add search functionality enhancements
// Add search functionality enhancements
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.querySelector('input[name="search_query"]');
    const loadingSpinner = document.getElementById('loadingSpinner');
    
    // Focus on search input when page loads if there's a search query
    if (searchInput.value) {
        searchInput.focus();
    }
    
    // Show loading spinner when form submits
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            loadingSpinner.style.display = 'flex';
        });
    }
    
    // Optional: Add live search with debounce
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            if (searchInput.value.length > 2 || searchInput.value.length === 0) {
                loadingSpinner.style.display = 'flex';
                searchTimer = setTimeout(function() {
                    searchForm.submit();
                }, 500);
            }
        });
    }
    
    // Hide spinner when page finishes loading (in case of back navigation)
    window.addEventListener('load', function() {
        loadingSpinner.style.display = 'none';
    });
});
</script>