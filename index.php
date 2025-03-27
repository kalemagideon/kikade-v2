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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $seller = $_POST['seller'] ?? 'Anonymous';
    
    // Validate inputs
    $errors = [];
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (!is_numeric($price) || $price <= 0) $errors[] = "Valid price is required";
    
    // Handle image upload
    $imagePath = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate image
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $_FILES['image']['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = "Only JPG, PNG, and GIF files are allowed";
        } elseif ($_FILES['image']['size'] > $maxSize) {
            $errors[] = "File size must be less than 2MB";
        } else {
            $filename = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $imagePath = $targetPath;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO items (title, description, price, seller, image_path, views, created_at) 
                                  VALUES (:title, :description, :price, :seller, :image_path, 0, NOW())");
            $stmt->execute([
                ':title' => htmlspecialchars($title),
                ':description' => htmlspecialchars($description),
                ':price' => (float)$price,
                ':seller' => htmlspecialchars($seller),
                ':image_path' => $imagePath
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
            background-color: var(--primary-color);
            color: var(--on-primary);
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
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
        
        .detail-modal-footer {
            padding: 16px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

/* Add these to your existing CSS */
.search-container {
    max-width: 500px;
}

.search-input {
    background-color: white;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}

.search-input input {
    border: none;
    outline: none;
    padding: 10px 16px;
    width: 100%;
}

.search-results-count {
    margin: 10px 0;
    color: #666;
    font-size: 14px;
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

            <!-- Search bar -->
<div class="search-container" style="flex-grow: 1; margin: 0 20px;">
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" class="search-form" style="display: flex;">
        <div class="search-input" style="position: relative; flex-grow: 1;">
            <input type="text" name="search_query" placeholder="Search items..." 
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
    </header>
    
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
                        <!-- your item cards -->
                    <?php endforeach; ?>
                </div>

        <div class="grid">
            <?php foreach ($items as $item): ?>
                <div class="card" data-id="<?php echo $item['id']; ?>">
                    <?php if ($item['image_path']): ?>
                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="card-image">
                    <?php else: ?>
                        <div style="height: 200px; background-color: #eee; display: flex; align-items: center; justify-content: center;">
                            <i class="material-icons" style="font-size: 48px; color: #999;">photo</i>
                        </div>
                    <?php endif; ?>
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p class="card-subtitle">Sold by: <?php echo htmlspecialchars($item['seller']); ?></p>
                        <p class="card-text"><?php echo htmlspecialchars($item['description']); ?></p>
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
                <label for="seller" class="form-label">Your Name</label>
                <input type="text" id="seller" name="seller" class="form-control" placeholder="Anonymous">
            </div>
            <div class="form-group">
                <label for="image" class="form-label">Item Image</label>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <small class="form-text">Max size: 2MB (JPEG, PNG, GIF)</small>
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
            <div class="detail-modal-footer">
                <button class="btn btn-flat close-detail-modal">Close</button>
                <button class="btn btn-primary">
                    <i class="material-icons">message</i> Contact Seller
                </button>
            </div>
        </div>
    </div>


              
<script>
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
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('.search-form');
    const searchInput = document.querySelector('input[name="search_query"]');
    
    // Focus on search input when page loads if there's a search query
    if (searchInput.value) {
        searchInput.focus();
    }
    
    // Optional: Add live search with debounce
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                if (searchInput.value.length > 2 || searchInput.value.length === 0) {
                    searchForm.submit();
                }
            }, 500);
        });
    }
});
</script>