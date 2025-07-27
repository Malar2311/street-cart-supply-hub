<?php
session_start();
// Include database connection (assuming db.php exists and establishes $conn)
include 'db.php';

// Redirect if user is not logged in or not a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor') {
    header("Location: login.php");
    exit();
}

// Fetch the vendor's name from the session
$vendorName = $_SESSION['username'] ?? 'Vendor'; // Assuming 'username' is stored in the session, consistent with supplier_dashboard

$search = $_GET['search'] ?? ''; // Get search query, default to empty string

// Get and clear session message
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it
}

// Base SQL query to fetch products from suppliers
// Added users.address as supplier_address to fetch supplier's address
$sql = "SELECT products.*, users.name as supplier, users.id as supplier_user_id, users.address as supplier_address
        FROM products
        JOIN users ON products.supplier_id=users.id
        WHERE users.role='supplier'";

$params = [];
$types = '';

// Add search condition if a search query is provided
if ($search) {
    $sql .= " AND (products.name LIKE ? OR products.description LIKE ? OR users.name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss'; // three string parameters
}

// ORDER PRODUCTS BY PRICE ASCENDING
$sql .= " ORDER BY products.price ASC";

// Prepare and execute the query using prepared statements
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("Failed to prepare statement for vendor dashboard products: " . $conn->error);
    $products_result = null; // Indicate query failure
} else {
    if (!empty($params)) {
        // Dynamically bind parameters using call_user_func_array
        $bind_names = array($types);
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }

    $stmt->execute();
    $products_result = $stmt->get_result(); // Get the result set
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Dashboard - StreetCart Supply Hub</title>
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

    <nav class="bg-white shadow-md p-4 flex items-center justify-between w-full max-w-full rounded-b-lg mb-8 z-10 relative">
        <div class="flex items-center space-x-2">
            <img src="images/logo.png" alt="StreetCart Logo" class="h-8 md:h-10 rounded-full">
            <span class="text-xl md:text-2xl font-semibold text-streetcart-dark">StreetCart</span>
        </div>
        <div class="flex items-center space-x-4">
            <a href="vendor_profile.php" class="block">
                <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75">
                    My Profile
                </button>
            </a>
            <a href="cart.php" class="block">
                <button class="bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75">
                    My Cart (<span id="cart-item-count"><?= count($_SESSION['cart'] ?? []) ?></span>)
                </button>
            </a>
            <a href="vendor_orders.php" class="block">
                <button class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-75">
                    My Orders
                </button>
            </a>
            <a href="logout.php" class="block">
                <button class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-75">
                    Logout
                </button>
            </a>
        </div>
    </nav>

    <div class="bg-white p-8 md:p-12 rounded-xl shadow-lg w-full flex-grow flex flex-col items-center">
        <div class="max-w-4xl w-full">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-streetcart-dark mb-6 md:mb-8">
                🛒 Hello, <?= htmlspecialchars($vendorName) ?>!!!<br>
                <br> The primary goal of a vendor is to make money......
            </h2>

            <?php if (!empty($message)): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= strpos($message,'✅')===0 || strpos($message,'successfully')!==false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <span class="block sm:inline"><?= htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <form method="get" class="flex flex-col md:flex-row items-center gap-4 mb-8 p-6 border border-streetcart-gray rounded-lg shadow-sm">
                <input name="search" placeholder="Search product or supplier" value="<?=htmlspecialchars($search)?>"
                    class="flex-grow p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 min-w-0">
                <button type="submit"
                    class="bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75 flex-shrink-0 flex items-center justify-center space-x-2">
                    <span>🔍</span> <span>Search</span>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="vendor_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-75">Clear</a>
                <?php endif; ?>
            </form>

            <h3 class="text-2xl md:text-3xl font-semibold text-streetcart-dark mb-4">Available Products</h3>
            <div class="overflow-x-auto rounded-lg shadow-md" id="products-table-container">
                <table class="min-w-full bg-white border-collapse">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tl-lg">Product Image</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Product Name & Description</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Price</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Stock</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Supplier</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Address</th> <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tr-lg">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products_result && $products_result->num_rows > 0): ?>
                            <?php while($product = $products_result->fetch_assoc()): ?>
                                <tr class="border-b border-streetcart-gray last:border-b-0 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <?php if(isset($product['image']) && $product['image']): ?>
                                            <img src="<?=htmlspecialchars($product['image'])?>" alt="Product Image" class="w-16 h-16 object-cover rounded-md shadow-sm">
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark font-medium">
                                        <?= htmlspecialchars($product['name']) ?><br>
                                        <span class="text-sm text-gray-500"><?= htmlspecialchars($product['description']) ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark">₹<?= htmlspecialchars(number_format($product['price'], 2)) ?></td>
                                    <td class="py-3 px-4 text-streetcart-dark">
                                        <?php if ($product['stock'] > 0): ?>
                                            <span class="text-green-600 font-semibold"><?= htmlspecialchars($product['stock']) ?> in stock</span>
                                        <?php else: ?>
                                            <span class="text-red-600 font-semibold">Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark"><?= htmlspecialchars($product['supplier']) ?></td>
                                    <td class="py-3 px-4 text-streetcart-dark text-sm">
                                        <?php if (!empty($product['supplier_address'])): ?>
                                            <?= htmlspecialchars($product['supplier_address']) ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                        </td>
                                    <td class="py-3 px-4">
                                        <button class="add-to-cart-btn bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75
                                            <?= $product['stock'] <= 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                            data-product-id="<?= $product['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                            data-product-price="<?= htmlspecialchars($product['price']) ?>"
                                            data-product-image="<?= htmlspecialchars($product['image'] ?? '') ?>"
                                            data-product-stock="<?= htmlspecialchars($product['stock']) ?>"
                                            <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                                            Add to Cart
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-streetcart-gray text-xl">No products found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cartItemCount = document.getElementById('cart-item-count');
            const productsTableContainer = document.getElementById('products-table-container');

            // Function to update cart item count in the UI
            function updateCartCount(count) {
                if (cartItemCount) {
                    cartItemCount.textContent = count;
                }
            }

            // Handle "Add to Cart" button clicks (delegated to parent for dynamically loaded content)
            productsTableContainer.addEventListener('click', function(event) {
                if (event.target.classList.contains('add-to-cart-btn')) {
                    const button = event.target;
                    const productId = button.dataset.productId;
                    const productName = button.dataset.productName;
                    const productPrice = button.dataset.productPrice;
                    const productImage = button.dataset.productImage; // Get image
                    const productStock = parseInt(button.dataset.productStock); // Get stock as integer

                    // Basic client-side stock check
                    if (productStock <= 0) {
                        alert('This product is out of stock.');
                        return;
                    }

                    fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&product_name=${encodeURIComponent(productName)}&product_price=${productPrice}&product_image=${encodeURIComponent(productImage)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            updateCartCount(data.cart_count); // Update cart count
                            fetchProducts(); // Refresh products to show updated stock
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while adding to cart.');
                    });
                }
            });

            // --- Automatic Updates (AJAX Polling) ---

            // Function to fetch and update the products table
            function fetchProducts() {
                const searchTerm = '<?= htmlspecialchars($search) ?>'; // Pass the current search term
                const url = `load_products.php?search=${encodeURIComponent(searchTerm)}`; // New dedicated file for content

                fetch(url)
                    .then(response => response.text()) // Get text/HTML response
                    .then(html => {
                        if (productsTableContainer) {
                            // Create a temporary div to parse the fetched HTML
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = html;

                            // Try to find the tbody within the fetched HTML
                            const newTbody = tempDiv.querySelector('tbody');
                            const currentTbody = productsTableContainer.querySelector('tbody');

                            if (newTbody && currentTbody) {
                                // If a tbody is found in the fetched content, replace the current tbody's innerHTML
                                currentTbody.innerHTML = newTbody.innerHTML;
                                fetchCartCount(); // Refresh cart count
                            } else if (html.trim().startsWith('<tr')) {
                                // Fallback: if no tbody found, but HTML starts with <tr>, assume it's just the rows
                                if (currentTbody) {
                                    currentTbody.innerHTML = html;
                                    fetchCartCount();
                                }
                            } else {
                                // If fetched HTML is not a tbody or trs, log an error or handle as needed
                                console.warn("Fetched content from load_products.php did not contain expected table rows or tbody:", html);
                                // You might want to display a user-friendly error message here
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching products:', error);
                    });
            }

            // Function to fetch just the cart count
            function fetchCartCount() {
                fetch('get_cart_count.php') // You might need to create this simple PHP file
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateCartCount(data.cart_count);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching cart count:', error);
                    });
            }

            fetchCartCount(); // Ensure initial cart count is accurate

            // Set up polling to refresh products every 10 seconds (adjust as needed)
            setInterval(fetchProducts, 10000); // 10000 milliseconds = 10 seconds
        });
    </script>

</body>
</html>