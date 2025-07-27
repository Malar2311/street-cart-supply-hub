<?php
session_start();
include 'db.php';

// Redirect if user is not logged in or not a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor') {
    header("Location: login.php");
    exit();
}

$vendor_id = $_SESSION['user_id'];
$vendorName = $_SESSION['username'] ?? 'Vendor';
$message = '';

// --- Retrieve and clear session message (for PRG pattern) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Allowed statuses for overall orders (no longer directly updated here, but kept for context if needed elsewhere)
$allowed_overall_statuses_for_vendor = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

// --- Removed: Function to update overall order status directly from this list ---
// Since the column and associated form are removed, this functionality is no longer needed here.
// You would still manage order status updates from the 'vendor_order_details.php' page
// or an administrative panel if that's your intended flow.


// --- Removed: Handle overall order status update (no longer applicable on this page) ---


// Fetch orders placed by the current vendor
$sql = "SELECT
            o.id AS order_id,
            o.order_date,
            o.status AS overall_order_status, -- Still fetching this, but not displayed in this column now
            o.delivery_address,
            o.delivery_phone,
            COUNT(oi.id) AS total_items,
            SUM(oi.quantity * oi.price_at_purchase) AS total_amount
        FROM
            orders o
        LEFT JOIN
            order_items oi ON o.id = oi.order_id
        WHERE
            o.vendor_id = ?
        GROUP BY
            o.id, o.order_date, o.status, o.delivery_address, o.delivery_phone
        ORDER BY
            o.order_date DESC";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("DB Error preparing vendor orders query: " . $conn->error);
    $_SESSION['message'] = "❌ Database error fetching your orders.";
    $orders_result = null;
} else {
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders - StreetCart Supply Hub</title>
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

    <nav class="bg-white shadow-md p-4 flex items-center justify-between w-full max-w-full rounded-b-lg mb-8">
        <div class="flex items-center space-x-2">
            <img src="images/logo.png" alt="StreetCart Logo" class="h-8 md:h-10 rounded-full">
            <span class="text-xl md:text-2xl font-semibold text-streetcart-dark">StreetCart</span>
        </div>
        <div class="flex items-center space-x-4">
            <a href="vendor_dashboard.php" class="block">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    ← Back to Dashboard
                </button>
            </a>
            <a href="vendor_profile.php" class="block">
                <button class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75">
                    My Profile
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
                📦 Your Placed Orders, <?= htmlspecialchars($vendorName) ?>
            </h2>

            <?php if (!empty($message)): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= strpos($message,'✅')===0 || strpos($message,'successfully')!==false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <span class="block sm:inline"><?= htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <h3 class="text-2xl md:text-3xl font-semibold text-streetcart-dark mb-4">Your Orders Overview</h3>
            <div class="overflow-x-auto rounded-lg shadow-md">
                <table class="min-w-full bg-white border-collapse">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tl-lg">Order ID</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Order Date</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-right">Total Items</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-right">Total Amount</th>
                            <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tr-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                            <?php while($order = $orders_result->fetch_assoc()): ?>
                                <tr class="border-b border-streetcart-gray last:border-b-0 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-streetcart-dark font-medium">#<?= htmlspecialchars($order['order_id']) ?></td>
                                    <td class="py-3 px-4 text-streetcart-dark"><?= date('M d, Y H:i', strtotime($order['order_date'])) ?></td>
                                    <td class="py-3 px-4 text-streetcart-dark text-right"><?= htmlspecialchars($order['total_items']) ?></td>
                                    <td class="py-3 px-4 text-streetcart-dark text-right">₹<?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                    <td class="py-3 px-4">
                                        <a href="vendor_order_details.php?order_id=<?= htmlspecialchars($order['order_id']) ?>"
                                           class="inline-block bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-2 px-3 rounded-full text-sm transition duration-300 ease-in-out shadow-md hover:shadow-lg">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-streetcart-gray text-xl">
                                    You have not placed any orders yet.
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