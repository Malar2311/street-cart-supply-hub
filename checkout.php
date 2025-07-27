<?php
session_start();
include 'db.php'; // Includes your database connection

// Redirect if user is not logged in or not a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor') {
    header("Location: login.php");
    exit();
}

$vendor_id = $_SESSION['user_id'];
$vendorName = $_SESSION['username'] ?? 'Vendor';
$cartItems = $_SESSION['cart'] ?? [];
$totalPrice = 0;
$message = ''; // For displaying status messages

// Fetch vendor's saved address and phone number
$stmt = $conn->prepare("SELECT address, phone_number FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor_details = $result->fetch_assoc();
    $stmt->close();
} else {
    error_log("Database error preparing vendor details query: " . $conn->error);
    $vendor_details = ['address' => '', 'phone_number' => ''];
}

// Re-validate cart items against current stock and calculate total price
// This is critical to ensure stock is still available at checkout time.
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
            if ($item['quantity'] > $availableStock) {
                $_SESSION['cart'][$productId]['quantity'] = $availableStock; // Adjust quantity in cart
                $item['quantity'] = $availableStock; // Update for current loop
                $message = "Quantity for " . htmlspecialchars($item['name']) . " adjusted to available stock (" . $availableStock . ").";
                if ($availableStock === 0) {
                    unset($_SESSION['cart'][$productId]); // Remove if stock becomes 0
                    $message = "Product " . htmlspecialchars($item['name']) . " is now out of stock and removed from your cart.";
                    continue; // Skip calculation for this item
                }
            }
            $totalPrice += $item['price'] * $item['quantity'];
        } else {
            // Product no longer exists in DB, remove from cart
            unset($_SESSION['cart'][$productId]);
            $message = "One or more products in your cart are no longer available and have been removed.";
            continue; // Skip calculation for this item
        }
    } else {
        error_log("Database error preparing statement for cart validation (checkout.php): " . $conn->error);
    }
}
// Update cartItems from session after potential adjustments
$cartItems = $_SESSION['cart'] ?? [];

// If cart is empty after validation, redirect back to cart.php with a message
if (empty($cartItems)) {
    $_SESSION['message'] = "Your cart is empty or all items were removed due to stock changes.";
    header("Location: cart.php");
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout - StreetCart Supply Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
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
            <a href="cart.php" class="inline-block mr-4">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    Back to Cart
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
                Confirm Your Order 📝
            </h2>

            <?php if (!empty($message)): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= (strpos($message,'adjusted')!==false || strpos($message,'removed')!==false) ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?>">
                    <span class="block sm:inline"><?= htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div>
                    <h3 class="text-2xl font-semibold text-streetcart-dark mb-4">Order Summary</h3>
                    <div class="overflow-x-auto rounded-lg shadow-md mb-4">
                        <table class="min-w-full bg-white border-collapse">
                            <thead>
                                <tr>
                                    <th class="py-2 px-3 bg-streetcart-dark text-white text-left rounded-tl-lg">Product</th>
                                    <th class="py-2 px-3 bg-streetcart-dark text-white text-right">Qty</th>
                                    <th class="py-2 px-3 bg-streetcart-dark text-white text-right rounded-tr-lg">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                    <tr class="border-b border-streetcart-gray last:border-b-0">
                                        <td class="py-2 px-3 text-streetcart-dark"><?= htmlspecialchars($item['name']) ?></td>
                                        <td class="py-2 px-3 text-streetcart-dark text-right"><?= htmlspecialchars($item['quantity']) ?></td>
                                        <td class="py-2 px-3 text-streetcart-dark text-right">₹<?= htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end items-center p-3 bg-streetcart-dark text-white rounded-lg shadow-md">
                        <span class="text-xl font-bold mr-4">Total:</span>
                        <span class="text-2xl font-extrabold">₹<?= htmlspecialchars(number_format($totalPrice, 2)) ?></span>
                    </div>
                </div>

                <div>
                    <h3 class="text-2xl font-semibold text-streetcart-dark mb-4">Delivery Information</h3>
                    <form action="place_order.php" method="post" class="space-y-4 p-6 border border-streetcart-gray rounded-lg shadow-sm">
                        <div>
                            <label for="delivery_address" class="block text-sm font-medium text-gray-700 mb-1">Delivery Address</label>
                            <textarea name="delivery_address" id="delivery_address" rows="4" required
                                class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200"
                                placeholder="E.g., 123 Main St, Anytown, Tamil Nadu, 641001"><?= htmlspecialchars($vendor_details['address'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label for="delivery_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" name="delivery_phone" id="delivery_phone" required
                                class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200"
                                placeholder="E.g., +91 98765 43210" value="<?= htmlspecialchars($vendor_details['phone_number'] ?? '') ?>">
                        </div>
                        <button type="submit" class="w-full bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 rounded-lg text-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75">
                            Place Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>