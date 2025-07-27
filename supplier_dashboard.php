<?php
session_start(); // Must be the very first line

// Include database connection
include 'db.php';

// Enable error reporting for debugging (REMOVE OR COMMENT OUT IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if user is not logged in or not a supplier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'supplier') {
    header("Location: login.php");
    exit();
}

$message = ''; // Initialize message variable for display
$supplier_id = $_SESSION['user_id']; // Get the logged-in supplier's ID
// Get supplier name from session. Ensure your login script sets $_SESSION['username'].
$supplier_name = $_SESSION['username'] ?? 'Supplier';

// Array of supplier-related quotes
$quotes = [
    "\"Fresh products, happy customers – the core of a thriving business.\"",
    "\"Every fresh ingredient you supply brings a smile to someone's table.\"",
    "\"Your commitment to quality fresh products nourishes communities.\"",
    "\"The journey of fresh produce from your hands to their homes creates joy.\"",
    "\"Delivering freshness daily, delivering happiness always.\""
];
$random_quote = $quotes[array_rand($quotes)]; // Select a random quote

// --- Retrieve and clear session message (for PRG pattern) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it once
}

// --- Handle POST Requests (Add and Update) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle product update submission first
    if (isset($_POST['update_product'])) {
        $product_id = $conn->real_escape_string($_POST['product_id']);
        $new_price = $conn->real_escape_string($_POST['new_price']);
        $new_stock = $conn->real_escape_string($_POST['new_stock']);
        $product_name_for_message = $conn->real_escape_string($_POST['product_name_for_message']); // Get product name for message

        // Sanitize numeric inputs more strictly
        $new_price = floatval($new_price);
        $new_stock = intval($new_stock);

        // Prepare and execute the update query using prepared statements for security
        $stmt = $conn->prepare("UPDATE products SET price = ?, stock = ? WHERE id = ? AND supplier_id = ?");
        if ($stmt === false) {
            $_SESSION['message'] = "❌ Database error preparing update statement: " . $conn->error;
            error_log("DB Error (supplier_dashboard.php update): " . $conn->error);
        } else {
            $stmt->bind_param("diii", $new_price, $new_stock, $product_id, $supplier_id); // d=double, i=integer
            if ($stmt->execute()) {
                $_SESSION['message'] = "✅ " . htmlspecialchars($product_name_for_message) . " updated successfully!";
            } else {
                $_SESSION['message'] = "❌ Error updating product: " . $stmt->error;
                error_log("DB Error (supplier_dashboard.php execute update): " . $stmt->error);
            }
            $stmt->close();
        }
        // Redirect to avoid form re-submission on refresh (PRG pattern)
        header("Location: supplier_dashboard.php");
        exit();
    }
    // Handle product addition submission
    else if (isset($_POST['add_product'])) { // Use else if to ensure only one action is processed per POST
        $product_name = $conn->real_escape_string($_POST['name']);
        $price = $conn->real_escape_string($_POST['price']);
        $stock = $conn->real_escape_string($_POST['stock']);
        $description = $conn->real_escape_string($_POST['description'] ?? ''); // Added description field

        // Sanitize numeric inputs more strictly
        $price = floatval($price);
        $stock = intval($stock);

        $target_file = null; // Initialize to null

        // Handle image upload
        // UPLOAD_ERR_OK means no error occurred during the upload
        if (isset($_FILES["image"]) && $_FILES["image"]["error"] == UPLOAD_ERR_OK) {
            $target_dir = "uploads/"; // Directory where images will be stored
            // Ensure uploads directory exists; create it if it doesn't
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true); // 0777 grants full permissions (adjust as needed for security)
            }

            // Create a unique file name to prevent overwrites and collisions
            $image_name = time() . "_" . uniqid() . "." . strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $target_file = $target_dir . $image_name;
            $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // Validate image file type
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($image_file_type, $allowed_types)) {
                $_SESSION['message'] = "❌ Sorry, only JPG, JPEG, PNG & GIF files are allowed for images.";
                $target_file = null; // Don't try to save invalid file type
            } else {
                // Check file size (e.g., max 5MB)
                if ($_FILES["image"]["size"] > 5 * 1024 * 1024) { // 5MB limit
                    $_SESSION['message'] = "❌ Sorry, your file is too large. Max 5MB allowed.";
                    $target_file = null;
                } elseif (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $_SESSION['message'] = "❌ Sorry, there was an error uploading your image.";
                    error_log("Upload Error (supplier_dashboard.php): Failed to move uploaded file " . $_FILES["image"]["tmp_name"] . " to " . $target_file);
                    $target_file = null;
                }
            }
        } else if (isset($_FILES["image"]) && $_FILES["image"]["error"] != UPLOAD_ERR_NO_FILE) {
             // Handle other specific upload errors
             switch ($_FILES["image"]["error"]) {
                 case UPLOAD_ERR_INI_SIZE:
                 case UPLOAD_ERR_FORM_SIZE:
                     $_SESSION['message'] = "❌ Uploaded file exceeds maximum file size.";
                     break;
                 case UPLOAD_ERR_PARTIAL:
                     $_SESSION['message'] = "❌ The uploaded file was only partially uploaded.";
                     break;
                 case UPLOAD_ERR_NO_TMP_DIR:
                     $_SESSION['message'] = "❌ Missing a temporary folder for uploads.";
                     break;
                 case UPLOAD_ERR_CANT_WRITE:
                     $_SESSION['message'] = "❌ Failed to write file to disk.";
                     break;
                 case UPLOAD_ERR_EXTENSION:
                     $_SESSION['message'] = "❌ A PHP extension stopped the file upload.";
                     break;
                 default:
                     $_SESSION['message'] = "❌ An unknown upload error occurred.";
             }
        } else {
            $_SESSION['message'] = "❌ Please select an image to upload.";
        }

        // Only proceed with DB insert if image upload was successful and no prior error message set
        if ($target_file !== null && empty($_SESSION['message'])) { // Changed !isset($_SESSION['message']) to empty($_SESSION['message'])
            // Insert product details into the database using prepared statements
            $stmt = $conn->prepare("INSERT INTO products (supplier_id, name, description, price, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                $_SESSION['message'] = "❌ Database error preparing insert statement: " . $conn->error;
                error_log("DB Error (supplier_dashboard.php insert): " . $conn->error);
            } else {
                $stmt->bind_param("issdis", $supplier_id, $product_name, $description, $price, $stock, $target_file); // i=integer, s=string, d=double
                if ($stmt->execute()) {
                    $_SESSION['message'] = "✅ Product added successfully!";
                } else {
                    $_SESSION['message'] = "❌ Error adding product: " . $stmt->error;
                    error_log("DB Error (supplier_dashboard.php execute insert): " . $stmt->error);
                }
                $stmt->close();
            }
        }
        // Redirect to avoid form re-submission on refresh (PRG pattern)
        header("Location: supplier_dashboard.php");
        exit();
    }
}

// --- Handle GET Requests (Deletion and Search) ---
$search_term = $_GET['search_term'] ?? ''; // Initialize search_term from GET or empty
$products_query_sql = "SELECT * FROM products WHERE supplier_id=?"; // Base query with placeholder
$bind_types = "i"; // Initial type string for supplier_id
$bind_values = [&$supplier_id]; // Initial array of values (references)

if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id']; // Cast to int for security

    // Prepare and execute the delete query using prepared statements for security
    $stmt = $conn->prepare("DELETE FROM products WHERE id=? AND supplier_id=?");
    if ($stmt === false) {
        $_SESSION['message'] = "❌ Database error preparing delete statement: " . $conn->error;
        error_log("DB Error (supplier_dashboard.php delete): " . $conn->error);
    } else {
        $stmt->bind_param("ii", $delete_id, $supplier_id); // ii = two integers
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) { // Check if any row was actually deleted
                $_SESSION['message'] = "✅ Product deleted successfully!";
            } else {
                $_SESSION['message'] = "❌ Product not found or does not belong to your account.";
            }
        } else {
            $_SESSION['message'] = "❌ Error deleting product: " . $stmt->error;
            error_log("DB Error (supplier_dashboard.php execute delete): " . $stmt->error);
        }
        $stmt->close();
    }
    // Redirect immediately after deletion to remove GET parameter from URL (PRG pattern)
    header("Location: supplier_dashboard.php");
    exit();
}

// Handle search functionality
if (!empty(trim($search_term))) { // Use the initialized $search_term
    $products_query_sql .= " AND (name LIKE ? OR description LIKE ?)"; // Add search filter to name and description
    $bind_types .= "ss"; // Add 'ss' for two string types to the types string
    $search_param_value = "%" . trim($search_term) . "%"; // Create a variable for the wildcarded search term
    $bind_values[] = &$search_param_value; // Add the reference to the new variable (for name)
    $bind_values[] = &$search_param_value; // Add the reference to the new variable (for description)
}

$products_query_sql .= " ORDER BY stock ASC, name ASC"; // Order by stock, then name

// Fetch all products for the logged-in supplier (and apply search filter if any)
$stmt = $conn->prepare($products_query_sql);
if ($stmt === false) {
    // This error is critical and should be logged/handled properly
    error_log("Error preparing product list query: " . $conn->error);
    $products_query = false; // Indicate query failed
    $message = "❌ An internal error occurred while fetching products."; // Display user-friendly error
} else {
    // Dynamically bind parameters using call_user_func_array
    // The first element of $bind_values is the type string, followed by references to variables
    call_user_func_array([$stmt, 'bind_param'], array_merge([$bind_types], $bind_values));

    $stmt->execute();
    $products_query = $stmt->get_result(); // Get the result set
    $stmt->close();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Supplier Dashboard - StreetCart Supply Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Tailwind CSS custom configuration for fonts and colors
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        // Define 'poppins' as a custom font family
                        poppins: ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        // Custom color palette for StreetCart branding
                        'streetcart-orange': '#FF6B35', // Primary accent color
                        'streetcart-dark': '#2C3E50',   // Dark text and elements
                        'streetcart-light': '#ECF0F1',  // Light background
                        'streetcart-gray': '#BDC3C7',   // Muted gray
                    }
                }
            }
        }
    </script>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
</head>
<body class="font-poppins bg-streetcart-light text-streetcart-dark min-h-screen flex flex-col p-4">

    <nav class="bg-white shadow-md p-4 flex items-center justify-between w-full max-w-full rounded-b-lg mb-8">
        <div class="flex items-center space-x-2">
            <img src="images/logo.png" alt="StreetCart Logo" class="h-8 md:h-10 rounded-full">
            <span class="text-xl md:text-2xl font-semibold text-streetcart-dark">StreetCart</span>
        </div>
        <div class="flex items-center space-x-4">
            <a href="supplier_profile.php" class="inline-block">
                <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75">
                    My Profile
                </button>
            </a>
            <a href="logout.php" class="inline-block">
                <button class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-75">
                    Logout
                </button>
            </a>
        </div>
    </nav>

    <div class="bg-white p-8 md:p-12 rounded-xl shadow-lg w-full flex-grow flex flex-col items-center">
        <div class="max-w-4xl w-full">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-streetcart-dark mb-2">Hello, <?= htmlspecialchars($supplier_name) ?>!</h2>
            <p class="text-center text-streetcart-gray text-lg italic mb-6 md:mb-8">
                <?= htmlspecialchars($random_quote) ?>
            </p>

            <?php if($message): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= strpos($message,'✅')===0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?=htmlspecialchars($message)?>
                </div>
            <?php endif; ?>

            <div class="flex justify-center mb-8 space-x-4">
                <a href="supplier_orders.php" class="inline-block">
                    <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-3 px-8 rounded-full text-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                        📦 Orders
                    </button>
                </a>
            </div>

            <h3 class="text-2xl md:text-3xl font-semibold text-streetcart-dark mb-4 mt-8">Add New Product</h3>
            <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 p-6 border border-streetcart-gray rounded-lg shadow-sm">
                <input name="name" placeholder="Product name" required
                    class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                <input name="price" type="number" step="0.01" placeholder="Price (e.g., 10.50)" required
                    class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                <textarea name="description" placeholder="Product description (optional)" rows="3"
                    class="md:col-span-2 w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 resize-y"></textarea>
                <input name="stock" type="number" placeholder="Stock quantity" required
                    class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                <label for="image-upload" class="w-full p-3 border border-streetcart-gray rounded-lg cursor-pointer flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition duration-200 text-streetcart-dark">
                    <span id="file-name" class="truncate">Choose Product Image</span>
                    <input type="file" name="image" id="image-upload" required class="hidden" onchange="document.getElementById('file-name').innerText = this.files[0].name">
                </label>
                <button type="submit" name="add_product"
                    class="md:col-span-2 w-full bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 rounded-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75">
                    ➕ Add Product
                </button>
            </form>

            <h3 class="text-2xl md:text-3xl font-semibold text-streetcart-dark mb-4">Your Products</h3>

            <form method="get" class="flex flex-col md:flex-row items-center gap-4 mb-6 p-4 border border-streetcart-gray rounded-lg shadow-sm">
                <input type="text" name="search_term" placeholder="Search products by name or description..."
                                value="<?= htmlspecialchars($search_term) ?>"
                                class="flex-grow p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 min-w-0">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 flex-shrink-0">
                    Search
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="supplier_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-75 flex-shrink-0">Clear</a>
                <?php endif; ?>
            </form>

            <div class="overflow-x-auto rounded-lg shadow-md">
                <table class="min-w-full bg-white border-collapse">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tl-lg">Image</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Name & Description</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Price</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Stock</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tr-lg">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products_query && $products_query->num_rows > 0): ?>
                            <?php while($product = $products_query->fetch_assoc()): ?>
                                <tr class="border-b border-streetcart-gray last:border-b-0 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <?php if($product['image']): ?>
                                            <img src="<?=htmlspecialchars($product['image'])?>" alt="Product Image" class="w-16 h-16 object-cover rounded-md shadow-sm">
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark font-medium">
                                        <?=htmlspecialchars($product['name'])?>
                                        <?php if (!empty($product['description'])): ?>
                                            <br><span class="text-sm text-gray-500"><?= htmlspecialchars($product['description']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark">₹<?=htmlspecialchars(number_format($product['price'], 2))?></td>
                                    <td class="py-3 px-4 text-streetcart-dark"><?=htmlspecialchars($product['stock'])?></td>
                                    <td class="py-3 px-4 flex items-center space-x-2">
                                        <button type="button" onclick="showUpdateModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>', <?= $product['price'] ?>, <?= $product['stock'] ?>)"
                                            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-3 rounded-md transition duration-200 ease-in-out shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 text-sm flex items-center space-x-1">
                                            <span>✏️</span> <span>Edit</span>
                                        </button>
                                        <button type="button" onclick="showConfirmModal(<?= $product['id'] ?>)"
                                            class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-3 rounded-md transition duration-200 ease-in-out shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-75 text-sm flex items-center space-x-1">
                                            <span>🗑</span> <span>Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-6 text-center text-streetcart-gray italic">
                                    <?php if (!empty($search_term)): ?>
                                        No products found matching "<?= htmlspecialchars($search_term) ?>".
                                    <?php else: ?>
                                        No products added yet.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-8 rounded-lg shadow-xl max-w-sm w-full text-center">
            <h3 class="text-xl font-semibold text-streetcart-dark mb-4">Confirm Deletion</h3>
            <p class="text-streetcart-dark mb-6">Are you sure you want to delete this product?</p>
            <div class="flex justify-center space-x-4">
                <button id="cancelDelete" class="bg-streetcart-gray hover:bg-gray-400 text-streetcart-dark font-bold py-2 px-4 rounded-lg transition duration-200">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200">Delete</a>
            </div>
        </div>
    </div>

    <div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full">
            <h3 class="text-xl font-semibold text-streetcart-dark mb-4">Update Product: <span id="updateProductName" class="font-bold"></span></h3>
            <form method="post" class="space-y-4">
                <input type="hidden" name="product_id" id="updateProductId">
                <input type="hidden" name="product_name_for_message" id="updateProductNameForMessage">
                <div>
                    <label for="newPrice" class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                    <input type="number" step="0.01" name="new_price" id="newPrice" required
                        class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                </div>
                <div>
                    <label for="newStock" class="block text-sm font-medium text-gray-700 mb-1">Stock</label>
                    <input type="number" name="new_stock" id="newStock" required
                        class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" id="cancelUpdate" class="bg-streetcart-gray hover:bg-gray-400 text-streetcart-dark font-bold py-2 px-4 rounded-lg transition duration-200">Cancel</button>
                    <button type="submit" name="update_product" class="bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Function to show the confirmation modal
    function showConfirmModal(productId) {
        document.getElementById('confirmModal').classList.remove('hidden');
        document.getElementById('confirmDeleteLink').href = '?delete_id=' + productId;
    }

    // Function to hide the confirmation modal
    document.getElementById('cancelDelete').addEventListener('click', function() {
        document.getElementById('confirmModal').classList.add('hidden');
    });

    // Hide modal if clicked outside (optional, but good UX)
    document.getElementById('confirmModal').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });

    // Function to show the update product modal
    function showUpdateModal(productId, productName, price, stock) {
        document.getElementById('updateModal').classList.remove('hidden');
        document.getElementById('updateProductId').value = productId;
        document.getElementById('updateProductName').innerText = productName;
        document.getElementById('updateProductNameForMessage').value = productName; // Set hidden input for message
        document.getElementById('newPrice').value = price;
        document.getElementById('newStock').value = stock;
    }

    // Function to hide the update product modal
    document.getElementById('cancelUpdate').addEventListener('click', function() {
        document.getElementById('updateModal').classList.add('hidden');
    });

    // Hide update modal if clicked outside (optional, but good UX)
    document.getElementById('updateModal').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
    </script>
</body>
</html>