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

$order_id = $_GET['order_id'] ?? null;

// Validate order_id
if (!$order_id || !is_numeric($order_id)) {
    $_SESSION['message'] = "❌ Invalid order ID provided.";
    header("Location: vendor_orders.php"); // Redirect back to vendor orders
    exit();
}

// Fetch general order details for THIS vendor
$order_details = [];
$stmt_order_details = $conn->prepare("
    SELECT
        o.id AS order_id,
        o.order_date,
        o.status AS overall_order_status,
        o.delivery_address,
        o.delivery_phone
    FROM
        orders o
    WHERE
        o.id = ? AND o.vendor_id = ?
");

if ($stmt_order_details === false) {
    error_log("DB Error preparing main vendor order details query: " . $conn->error);
    $_SESSION['message'] = "❌ Database error fetching order details.";
    header("Location: vendor_orders.php");
    exit();
}

$stmt_order_details->bind_param("ii", $order_id, $vendor_id);
$stmt_order_details->execute();
$result_order_details = $stmt_order_details->get_result();
$order_details = $result_order_details->fetch_assoc();
$stmt_order_details->close();

if (!$order_details) {
    $_SESSION['message'] = "❌ Order not found or you don't have permission to view it.";
    header("Location: vendor_orders.php");
    exit();
}

// Fetch specific order items for this order (all items, not just from a specific supplier)
$order_items = [];
$stmt_items = $conn->prepare("
    SELECT
        oi.id AS item_id,
        oi.product_name,
        oi.quantity,
        oi.price_at_purchase,
        oi.status AS item_status,
        p.image,
        u.name AS supplier_name -- To show which supplier provides the item
    FROM
        order_items oi
    JOIN
        products p ON oi.product_id = p.id
    JOIN
        users u ON oi.supplier_id = u.id -- Join with users to get supplier name
    WHERE
        oi.order_id = ?
");

if ($stmt_items === false) {
    error_log("DB Error preparing order items query: " . $conn->error);
    $_SESSION['message'] = "❌ Database error fetching order items.";
    header("Location: vendor_orders.php");
    exit();
}

$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($row = $result_items->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt_items->close();

$total_order_amount = 0;
foreach ($order_items as $item) {
    $total_order_amount += $item['quantity'] * $item['price_at_purchase'];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Details - StreetCart Supply Hub</title>
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
        <div>
            <a href="vendor_orders.php" class="inline-block mr-4">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    ← Back to My Orders
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
                Details for Order #<?= htmlspecialchars($order_details['order_id']) ?>
            </h2>

            <?php if($message): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= strpos($message,'✅')===0 ? 'bg-green-100 text-green-700' : (strpos($message,'❌')===0 ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') ?>">
                    <?=htmlspecialchars($message)?>
                </div>
            <?php endif; ?>

            <div class="mb-8 p-6 border border-streetcart-gray rounded-lg shadow-sm grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-streetcart-dark mb-2">Order Information</h3>
                    <p><strong>Order Date:</strong> <?= date('M d, Y H:i', strtotime($order_details['order_date'])) ?></p>
                    <p><strong>Overall Order Status:</strong>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                            <?php
                                switch ($order_details['overall_order_status']) {
                                    case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'Processing': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'Shipped': echo 'bg-purple-100 text-purple-800'; break;
                                    case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                                    case 'Cancelled': echo 'bg-red-100 text-red-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800'; break;
                                }
                            ?>">
                            <?=htmlspecialchars($order_details['overall_order_status'])?>
                        </span>
                    </p>
                    <p><strong>Total Order Amount:</strong> ₹<?= htmlspecialchars(number_format($total_order_amount, 2)) ?></p>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-streetcart-dark mb-2">Delivery Information</h3>
                    <p><strong>Address:</strong> <?= htmlspecialchars($order_details['delivery_address']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($order_details['delivery_phone']) ?></p>
                </div>
            </div>

            <h3 class="text-2xl md:text-3xl font-semibold text-streetcart-dark mb-4">Items in This Order</h3>

            <?php if (!empty($order_items)): ?>
                <div class="overflow-x-auto rounded-lg shadow-md">
                    <table class="min-w-full bg-white border-collapse">
                        <thead>
                            <tr>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tl-lg">Image</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Product</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Supplier</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-right">Quantity</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-right">Price/Item</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-right">Subtotal</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tr-lg">Item Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr class="border-b border-streetcart-gray last:border-b-0 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <?php if(isset($item['image']) && $item['image']): ?>
                                            <img src="<?=htmlspecialchars($item['image'])?>" alt="Product Image" class="w-16 h-16 object-cover rounded-md shadow-sm">
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark font-medium"><?=htmlspecialchars($item['product_name'])?></td>
                                    <td class="py-3 px-4 text-streetcart-dark"><?=htmlspecialchars($item['supplier_name'])?></td>
                                    <td class="py-3 px-4 text-streetcart-dark text-right"><?=htmlspecialchars($item['quantity'])?></td>
                                    <td class="py-3 px-4 text-streetcart-dark text-right">₹<?=htmlspecialchars(number_format($item['price_at_purchase'], 2))?></td>
                                    <td class="py-3 px-4 text-streetcart-dark text-right">₹<?=htmlspecialchars(number_format($item['quantity'] * $item['price_at_purchase'], 2))?></td>
                                    <td class="py-3 px-4">
                                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                                            <?php
                                                switch ($item['item_status']) {
                                                    case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'Processing': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'Ready for Dispatch': echo 'bg-indigo-100 text-indigo-800'; break;
                                                    case 'Shipped': echo 'bg-purple-100 text-purple-800'; break;
                                                    case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                                                    case 'Cancelled': echo 'bg-red-100 text-red-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800'; break;
                                                }
                                            ?>">
                                            <?=htmlspecialchars($item['item_status'])?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="py-6 text-center text-streetcart-gray italic">No items found for this order.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>