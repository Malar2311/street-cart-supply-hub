<?php
session_start();
include 'db.php'; // Includes your database connection

// Redirect if user is not logged in or not a supplier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'supplier') {
    header("Location: login.php");
    exit();
}

$supplier_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['username'] ?? 'Supplier'; // Get supplier name from session or default
$message = ''; // For displaying status messages

// --- Retrieve and clear session message (for PRG pattern) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it once
}

// Allowed statuses for overall orders (kept for reference, though not directly displayed/updated here now)
$allowed_overall_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

// --- Removed: Function to update overall order status directly from this list (no longer needed) ---
// Since the column is removed, direct update from this page is also removed.


// Fetch orders that contain products from this supplier
// We need to join orders with order_items and users (for vendor name)
$orders_query = $conn->prepare("
    SELECT
        o.id AS order_id,
        o.order_date,
        o.status AS overall_order_status, -- Still fetching, but not displayed in this column
        u.name AS vendor_username,
        o.delivery_address,
        o.delivery_phone,
        SUM(oi.price_at_purchase * oi.quantity) AS total_for_supplier
    FROM
        orders o
    JOIN
        order_items oi ON o.id = oi.order_id
    JOIN
        users u ON o.vendor_id = u.id
    WHERE
        oi.supplier_id = ?
    GROUP BY
        o.id, o.order_date, o.status, u.name, o.delivery_address, o.delivery_phone
    ORDER BY
        o.order_date DESC
");

if ($orders_query === false) {
    error_log("Database error preparing orders query: " . $conn->error);
    $message = "❌ An internal error occurred while fetching orders.";
    $orders_result = false; // Set to false to indicate query failure
} else {
    $orders_query->bind_param("i", $supplier_id);
    $orders_query->execute();
    $orders_result = $orders_query->get_result();
    $orders_query->close();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Supplier Orders - StreetCart Supply Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Tailwind CSS configuration
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

    <nav class="bg-white shadow-md p-4 flex items-center justify-between w-full max-w-full rounded-b-lg mb-8">
        <div class="flex items-center space-x-2">
            <img src="images/logo.png" alt="StreetCart Logo" class="h-8 md:h-10 rounded-full">
            <span class="text-xl md:text-2xl font-semibold text-streetcart-dark">StreetCart</span>
        </div>
        <div>
            <a href="supplier_dashboard.php" class="inline-block mr-4">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    Product Dashboard
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
                📦 Orders for <?= htmlspecialchars($supplier_name) ?>
            </h2>

            <?php if($message): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= strpos($message,'✅')===0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?=htmlspecialchars($message)?>
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto rounded-lg shadow-md mb-8">
                <table class="min-w-full bg-white border-collapse">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tl-lg">Order ID</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Vendor</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Date</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Your Earnings</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tr-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                            <?php while($order = $orders_result->fetch_assoc()): ?>
                                <tr class="border-b border-streetcart-gray last:border-b-0 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-streetcart-dark font-medium">#<?=htmlspecialchars($order['order_id'])?></td>
                                    <td class="py-3 px-4 text-streetcart-dark"><?=htmlspecialchars($order['vendor_username'])?></td>
                                    <td class="py-3 px-4 text-streetcart-dark"><?=date('M d, Y H:i', strtotime($order['order_date']))?></td>
                                    <td class="py-3 px-4 text-streetcart-dark">₹<?=htmlspecialchars(number_format($order['total_for_supplier'], 2))?></td>
                                    <td class="py-3 px-4">
                                        <a href="supplier_order_details.php?order_id=<?= $order['order_id'] ?>"
                                            class="bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-2 px-3 rounded-md transition duration-200 ease-in-out shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75 text-sm">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-6 text-center text-streetcart-gray italic">
                                    No orders found for your products.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>