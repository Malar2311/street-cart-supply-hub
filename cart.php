<?php
session_start(); // Must be the very first line

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php'; // Includes your database connection

// Redirect if user is not logged in or not a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor') {
    header("Location: login.php");
    exit();
}

$vendorName = $_SESSION['username'] ?? 'Vendor'; // Changed 'user_name' to 'username' for consistency

// --- Handle AJAX requests for quantity update and removal ---
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // This is an AJAX request
    header('Content-Type: application/json'); // Respond with JSON

    $response = ['success' => false, 'message' => '', 'cart_count' => count($_SESSION['cart'] ?? []), 'total_price' => 0];

    if (isset($_POST['update_quantity_ajax'])) {
        $productId = intval($_POST['product_id']);
        $newQuantity = intval($_POST['quantity']);

        if (isset($_SESSION['cart'][$productId])) {
            $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
            if ($stmt === false) {
                $response['message'] = "Database error preparing statement for stock check: " . $conn->error;
                error_log("Database Error (cart.php, update AJAX): " . $conn->error);
            } else {
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $result = $stmt->get_result();
                $productDB = $result->fetch_assoc();
                $stmt->close();

                if ($productDB) {
                    $availableStock = $productDB['stock'];

                    if ($newQuantity > 0 && $newQuantity <= $availableStock) {
                        $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
                        $response['success'] = true;
                        $response['message'] = "Cart updated successfully!";
                    } elseif ($newQuantity <= 0) {
                        unset($_SESSION['cart'][$productId]);
                        $response['success'] = true;
                        $response['message'] = "Product removed from cart.";
                    } else {
                        $response['message'] = "Cannot update quantity. Only " . $availableStock . " items available for " . htmlspecialchars($_SESSION['cart'][$productId]['name']) . ".";
                    }
                } else {
                    unset($_SESSION['cart'][$productId]);
                    $response['success'] = true; // Still a success, but item removed
                    $response['message'] = "Product no longer available or was removed from inventory.";
                }
            }
        } else {
            $response['message'] = "Product not found in your cart.";
        }
    } elseif (isset($_POST['remove_item_ajax'])) {
        $productId = intval($_POST['product_id']);
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
            $response['success'] = true;
            $response['message'] = "Product removed from cart.";
        } else {
            $response['message'] = "Product was not in your cart.";
        }
    }

    // Re-calculate total price and cart count for the response
    $currentTotalPrice = 0;
    foreach ($_SESSION['cart'] ?? [] as $item) {
        $currentTotalPrice += $item['price'] * $item['quantity'];
    }
    $response['cart_count'] = count($_SESSION['cart'] ?? []);
    $response['total_price'] = number_format($currentTotalPrice, 2); // Format for display

    echo json_encode($response);
    exit(); // IMPORTANT: Exit after sending JSON response for AJAX requests
}

// --- End of AJAX handling ---


// Fetch products in cart for display
$cartItems = $_SESSION['cart'] ?? [];
$totalPrice = 0;

// Re-validate cart items against current stock and calculate total price for initial page load
foreach ($cartItems as $productId => $item) {
    $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $productDB = $result->fetch_assoc();
        $stmt->close();

        if ($productDB) {
            $availableStock = $productDB['stock'];
            // If cart quantity exceeds available stock, adjust it
            if ($item['quantity'] > $availableStock) {
                $_SESSION['cart'][$productId]['quantity'] = $availableStock;
                $item['quantity'] = $availableStock; // Update for current loop
                if ($availableStock === 0) {
                     unset($_SESSION['cart'][$productId]); // Remove if stock becomes 0
                     continue; // Skip calculation for this item
                }
                $_SESSION['message'] = "Quantity for " . htmlspecialchars($item['name']) . " adjusted to available stock (" . $availableStock . ").";
            }
            $totalPrice += $item['price'] * $item['quantity'];
        } else {
            // Product no longer exists in DB, remove from cart
            unset($_SESSION['cart'][$productId]);
            $_SESSION['message'] = "One or more products in your cart are no longer available.";
            continue; // Skip calculation for this item
        }
    } else {
        error_log("Database error preparing statement for cart validation: " . $conn->error);
    }
}
// Update cartItems from session after potential adjustments
$cartItems = $_SESSION['cart'] ?? [];


?>
<!DOCTYPE html>
<html>
<head>
    <title>Your Cart - StreetCart Supply Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Tailwind CSS custom configuration for fonts and colors
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        'streetcart-orange': '#FF6B35',
                        'streetcart-dark': '#2C3E50',
                        'streetcart-light': '#ECF0F1',
                        'streetcart-gray': '#BDC3C7',
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
        <div>
            <a href="vendor_dashboard.php" class="inline-block mr-4">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    Back to Products
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
            <h2 class="text-3xl md:text-4xl font-bold text-center text-streetcart-dark mb-6 md:mb-8">
                Your Shopping Cart 🛒
            </h2>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= (strpos($_SESSION['message'],'successfully')!==false || strpos($_SESSION['message'],'removed')!==false || strpos($_SESSION['message'],'adjusted')!==false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <span class="block sm:inline"><?= htmlspecialchars($_SESSION['message']); ?></span>
                    <?php unset($_SESSION['message']); // Clear message after displaying ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($cartItems)): ?>
                <div class="overflow-x-auto rounded-lg shadow-md mb-8">
                    <table class="min-w-full bg-white border-collapse">
                        <thead>
                            <tr>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tl-lg">Product</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Price</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Quantity</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Subtotal</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tr-lg">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $productId => $item): ?>
                                <?php
                                // Fetch current stock for this product to set max attribute
                                $currentStock = 0;
                                $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
                                if ($stmt) {
                                    $stmt->bind_param("i", $productId);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $productDB = $result->fetch_assoc();
                                    $stmt->close();
                                    if ($productDB) {
                                        $currentStock = $productDB['stock'];
                                    }
                                }
                                ?>
                                <tr class="border-b border-streetcart-gray last:border-b-0 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-streetcart-dark font-medium flex items-center">
                                        <?php if(isset($item['image']) && $item['image']): ?>
                                            <img src="<?=htmlspecialchars($item['image'])?>" alt="Product Image" class="w-12 h-12 object-cover rounded-md mr-3 shadow-sm">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($item['name']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark">₹<?= htmlspecialchars(number_format($item['price'], 2)) ?></td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <input type="number" data-product-id="<?= $productId ?>"
                                                    value="<?= htmlspecialchars($item['quantity']) ?>"
                                                    min="1"
                                                    max="<?= htmlspecialchars($currentStock) ?>"
                                                    class="quantity-input w-20 p-2 border border-streetcart-gray rounded-md focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                                            <button type="button" data-product-id="<?= $productId ?>"
                                                    class="update-quantity-btn bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-3 rounded-md text-sm transition duration-300 ease-in-out">
                                                    Update
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark font-semibold">₹<?= htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)) ?></td>
                                    <td class="py-3 px-4">
                                        <button type="button" data-product-id="<?= $productId ?>"
                                                    class="remove-item-btn bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-3 rounded-md text-sm transition duration-300 ease-in-out">
                                                    Remove
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end items-center mt-6 p-4 bg-streetcart-dark text-white rounded-lg shadow-md">
                    <span class="text-2xl font-bold mr-4">Total:</span>
                    <span class="text-3xl font-extrabold">₹<?= htmlspecialchars(number_format($totalPrice, 2)) ?></span>
                </div>

                <div class="flex justify-end mt-6">
                    <a href="checkout.php" class="inline-block bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 px-8 rounded-full text-lg transition duration-300 ease-in-out shadow-xl hover:shadow-2xl">
                        Proceed to Checkout
                    </a>
                </div>

            <?php else: ?>
                <p class="text-center text-streetcart-gray text-xl py-12">Your cart is empty. Start adding some products!</p>
            <?php endif; ?>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to handle AJAX requests
    function sendCartUpdate(productId, quantity, actionType) {
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        if (actionType === 'update') {
            formData.append('update_quantity_ajax', '1');
        } else if (actionType === 'remove') {
            formData.append('remove_item_ajax', '1');
        }

        fetch('cart.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Identify as AJAX request
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh the whole cart section or just the relevant row
                // For simplicity and to correctly update totals/other rows,
                // we'll reload the page on successful update/remove.
                // A more advanced solution would update specific DOM elements.
                window.location.reload();
            } else {
                alert(data.message);
                // If update failed, reset the quantity input to its original value
                // (before the user changed it). This requires storing original values.
                // For now, reloading handles this by reverting.
                window.location.reload(); // Also reload for errors to show correct state
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            window.location.reload(); // Reload on fetch error too
        });
    }

    // Event listeners for Update Quantity buttons
    document.querySelectorAll('.update-quantity-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const quantityInput = this.closest('td').querySelector('.quantity-input');
            const newQuantity = parseInt(quantityInput.value);
            const maxQuantity = parseInt(quantityInput.max); // Get the max from the input field

            // Basic client-side validation before sending AJAX
            if (isNaN(newQuantity) || newQuantity < 1) {
                alert('Quantity must be at least 1.');
                quantityInput.value = quantityInput.defaultValue; // Reset to original
                return;
            }
            if (newQuantity > maxQuantity) {
                alert(`You can only order up to ${maxQuantity} of this product.`);
                quantityInput.value = maxQuantity; // Reset to max available
                return;
            }

            sendCartUpdate(productId, newQuantity, 'update');
        });
    });

    // Event listeners for Remove Item buttons
    document.querySelectorAll('.remove-item-btn').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                const productId = this.dataset.productId;
                sendCartUpdate(productId, 0, 'remove'); // Quantity 0 for removal
            }
        });
    });

});
</script>

</body>
</html>