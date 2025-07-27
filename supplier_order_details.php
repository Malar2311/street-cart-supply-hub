<?php
session_start();
include 'db.php'; // Assuming 'db.php' handles your database connection ($conn)

// Redirect if user is not logged in or not a supplier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'supplier') {
    header("Location: login.php");
    exit();
}

$supplier_id = $_SESSION['user_id'];
$supplier_name = $_SESSION['username'] ?? 'Supplier';
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
    header("Location: supplier_orders.php");
    exit();
}

// Function to update the overall order status based on item statuses
function updateOverallOrderStatus($conn, $order_id) {
    // Get statuses of all items for this order
    $stmt_check_items = $conn->prepare("SELECT status FROM order_items WHERE order_id = ?");
    if ($stmt_check_items === false) {
        error_log("DB Error preparing item status check for overall status: " . $conn->error);
        return false;
    }
    $stmt_check_items->bind_param("i", $order_id);
    $stmt_check_items->execute();
    $result_check_items = $stmt_check_items->get_result();

    $all_item_statuses = [];
    while ($row = $result_check_items->fetch_assoc()) {
        $all_item_statuses[] = $row['status'];
    }
    $stmt_check_items->close();

    $new_overall_status = 'Pending'; // Default to Pending

    // Define the hierarchy of statuses (lowest to highest priority for overall order status)
    $status_priority = [
        'Cancelled' => 0, // A cancelled item might mean partial fulfillment or full cancellation
        'Pending' => 1,
        'Processing' => 2,
        'Ready for Dispatch' => 3,
        'Shipped' => 4,
        'Delivered' => 5
    ];

    // Initialize flags
    $has_pending = false;
    $has_processing = false;
    $has_ready_for_dispatch = false;
    $has_shipped = false;
    $has_delivered = false;
    $has_cancelled = false;

    foreach ($all_item_statuses as $status) {
        if ($status == 'Pending') $has_pending = true;
        if ($status == 'Processing') $has_processing = true;
        if ($status == 'Ready for Dispatch') $has_ready_for_dispatch = true;
        if ($status == 'Shipped') $has_shipped = true;
        if ($status == 'Delivered') $has_delivered = true;
        if ($status == 'Cancelled') $has_cancelled = true;
    }

    // Determine the overall status based on the highest "incomplete" status present,
    // or 'Delivered' if all are delivered, or 'Cancelled' if all are cancelled.

    // If all items are 'Delivered'
    if ($has_delivered && !$has_pending && !$has_processing && !$has_ready_for_dispatch && !$has_shipped && !$has_cancelled) {
        $new_overall_status = 'Delivered';
    }
    // If all items are 'Cancelled'
    else if ($has_cancelled && !$has_pending && !$has_processing && !$has_ready_for_dispatch && !$has_shipped && !$has_delivered) {
        $new_overall_status = 'Cancelled';
    }
    // If any item is Pending, the order is Pending
    else if ($has_pending) {
        $new_overall_status = 'Pending';
    }
    // If any item is Processing, and no Pending
    else if ($has_processing) {
        $new_overall_status = 'Processing';
    }
    // If any item is Ready for Dispatch, and no Pending/Processing
    else if ($has_ready_for_dispatch) {
        $new_overall_status = 'Ready for Dispatch';
    }
    // If any item is Shipped, and no Pending/Processing/Ready for Dispatch (some might be delivered)
    else if ($has_shipped) {
        $new_overall_status = 'Shipped';
    }
    // Handle cases where there might be a mix of Shipped/Delivered/Cancelled (partial fulfillment)
    // You might want a 'Partially Fulfilled' or 'Mixed Status' here if your business logic requires it.
    // For now, we fall through to the default 'Pending' if none of the above specific conditions are met.
    // This part of the logic can be complex and depends heavily on desired business rules.
    // The previous logic for `Shipped` when some are delivered also needs refinement based on this.
    // For a simple hierarchy:
    // If all items are Delivered -> Delivered
    // Else if all items are Shipped (or a mix of Shipped/Delivered) -> Shipped
    // Else if all items are Ready for Dispatch (or a mix of RFD/Shipped/Delivered) -> Ready for Dispatch
    // ...and so on.

    // A more robust sequential check (from highest "incomplete" status to lowest):
    if ($has_pending) {
        $new_overall_status = 'Pending';
    } elseif ($has_processing) {
        $new_overall_status = 'Processing';
    } elseif ($has_ready_for_dispatch) {
        $new_overall_status = 'Ready for Dispatch';
    } elseif ($has_shipped) {
        $new_overall_status = 'Shipped';
    } elseif ($has_delivered && !$has_cancelled) { // All items are delivered (or a mix including delivered, but no pending/processing/shipped/RFD)
         $all_delivered = true;
         foreach ($all_item_statuses as $status) {
             if ($status !== 'Delivered') {
                 $all_delivered = false;
                 break;
             }
         }
         if ($all_delivered) {
             $new_overall_status = 'Delivered';
         }
    }
    // If all items are cancelled
    if (count(array_unique($all_item_statuses)) === 1 && $all_item_statuses[0] === 'Cancelled') {
        $new_overall_status = 'Cancelled';
    }
    // If there's a mix including cancelled and other active statuses, it's typically "Partially Fulfilled"
    // but without that status in `orders` table, it will fall to the next highest active status.
    // This is where custom logic for "partially cancelled" or "partially delivered" would go.


    // Fetch current overall status to avoid unnecessary updates
    $stmt_get_current_overall_status = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    if ($stmt_get_current_overall_status === false) {
        error_log("DB Error preparing fetch current overall status: " . $conn->error);
        return false;
    }
    $stmt_get_current_overall_status->bind_param("i", $order_id);
    $stmt_get_current_overall_status->execute();
    $current_overall_status_result = $stmt_get_current_overall_status->get_result()->fetch_assoc();
    $current_overall_status = $current_overall_status_result['status'] ?? 'Unknown'; // Default if not found
    $stmt_get_current_overall_status->close();


    if ($current_overall_status !== $new_overall_status) {
        $stmt_update_overall = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt_update_overall === false) {
            error_log("DB Error preparing overall order status update: " . $conn->error);
            return false;
        }
        $stmt_update_overall->bind_param("si", $new_overall_status, $order_id);
        if (!$stmt_update_overall->execute()) {
            error_log("DB Error executing overall order status update: " . $stmt_update_overall->error);
            return false;
        }
        $stmt_update_overall->close();
        return true; // Status was updated
    }
    return false; // No change needed
}


// Handle status update for individual order item
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_item_status'])) {
    $item_id = $conn->real_escape_string($_POST['item_id']);
    $new_item_status = $conn->real_escape_string($_POST['new_item_status']);

    // Validate new_item_status against allowed_item_statuses
    $allowed_item_statuses = ['Pending', 'Processing', 'Ready for Dispatch', 'Shipped', 'Delivered', 'Cancelled'];
    if (!in_array($new_item_status, $allowed_item_statuses)) {
        $_SESSION['message'] = "❌ Invalid status value provided.";
        header("Location: supplier_order_details.php?order_id=" . $order_id);
        exit();
    }

    // Ensure the item belongs to this supplier and the specific order
    $stmt_update = $conn->prepare("UPDATE order_items SET status = ? WHERE id = ? AND order_id = ? AND supplier_id = ?");
    if ($stmt_update === false) {
        error_log("DB Error preparing item status update: " . $conn->error);
        $_SESSION['message'] = "❌ Database error updating item status.";
    } else {
        $stmt_update->bind_param("siii", $new_item_status, $item_id, $order_id, $supplier_id);
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                $_SESSION['message'] = "✅ Item status updated to " . htmlspecialchars($new_item_status) . "!";
                // ******************************************************************
                // CALL THE FUNCTION TO UPDATE OVERALL ORDER STATUS HERE
                if (updateOverallOrderStatus($conn, $order_id)) {
                    // Refresh $order_details to reflect the new overall status for the current page load
                    // (though a redirect happens, this is good practice if you weren't redirecting)
                    // The redirect will cause a fresh fetch anyway.
                    // Removed the message addition as it's already covered by the redirect.
                }
                // ******************************************************************
            } else {
                $_SESSION['message'] = "⚠️ Item not found or status already updated.";
            }
        } else {
            error_log("DB Error executing item status update: " . $stmt_update->error);
            $_SESSION['message'] = "❌ Error updating item status: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
    header("Location: supplier_order_details.php?order_id=" . $order_id); // Redirect back to same page
    exit();
}

// Fetch general order details (THIS FETCH MUST HAPPEN AFTER ANY POTENTIAL UPDATES/REDIRECTS)
$order_details = [];
$stmt_order_details = $conn->prepare("
    SELECT
        o.id AS order_id,
        o.order_date,
        o.status AS overall_order_status,
        u.name AS vendor_username,
        o.delivery_address,
        o.delivery_phone
    FROM
        orders o
    JOIN
        users u ON o.vendor_id = u.id
    WHERE
        o.id = ?
");

if ($stmt_order_details === false) {
    error_log("DB Error preparing main order details query: " . $conn->error);
    $_SESSION['message'] = "❌ Database error fetching order details.";
    header("Location: supplier_orders.php");
    exit();
}

$stmt_order_details->bind_param("i", $order_id);
$stmt_order_details->execute();
$result_order_details = $stmt_order_details->get_result();
$order_details = $result_order_details->fetch_assoc();
$stmt_order_details->close();

if (!$order_details) {
    $_SESSION['message'] = "❌ Order not found.";
    header("Location: supplier_orders.php");
    exit();
}

// Fetch specific order items for THIS supplier within this order
$order_items = [];
$stmt_items = $conn->prepare("
    SELECT
        oi.id AS item_id,
        oi.product_name,
        oi.quantity,
        oi.price_at_purchase,
        oi.status AS item_status,
        p.image
    FROM
        order_items oi
    JOIN
        products p ON oi.product_id = p.id
    WHERE
        oi.order_id = ? AND oi.supplier_id = ?
");

if ($stmt_items === false) {
    error_log("DB Error preparing order items query: " . $conn->error);
    $_SESSION['message'] = "❌ Database error fetching order items.";
    header("Location: supplier_orders.php");
    exit();
}

$stmt_items->bind_param("ii", $order_id, $supplier_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($row = $result_items->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt_items->close();

$total_supplier_earnings_for_this_order = 0;
foreach ($order_items as $item) {
    $total_supplier_earnings_for_this_order += $item['quantity'] * $item['price_at_purchase'];
}

// Array of allowed statuses for order items
$allowed_item_statuses = ['Pending', 'Processing', 'Ready for Dispatch', 'Shipped', 'Delivered', 'Cancelled'];

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
            <a href="supplier_orders.php" class="inline-block mr-4">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    ← Back to Orders
                </button>
            </a>
            <a href="logout.php" class="inline-block">
                <button class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-75">
                    Logout
                </button>
            </a
        </div>
    </nav>

    <div class="bg-white p-8 md:p-12 rounded-xl shadow-lg w-full flex-grow flex flex-col items-center">
        <div class="max-w-4xl w-full">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-streetcart-dark mb-6 md:mb-8">
                Order #<?= htmlspecialchars($order_details['order_id']) ?> Details
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
                    <p><strong>Vendor:</strong> <?= htmlspecialchars($order_details['vendor_username']) ?></p>
                    <p><strong>Order Date:</strong> <?= date('M d, Y H:i', strtotime($order_details['order_date'])) ?></p>
                    <p><strong>Your Total for this Order:</strong> ₹<?= htmlspecialchars(number_format($total_supplier_earnings_for_this_order, 2)) ?></p>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-streetcart-dark mb-2">Delivery Information</h3>
                    <p><strong>Address:</strong> <?= htmlspecialchars($order_details['delivery_address']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($order_details['delivery_phone']) ?></p>
                </div>
            </div>

            <h3 class="text-2xl md:text-3xl font-semibold text-streetcart-dark mb-4">Your Items in This Order</h3>

            <?php if (!empty($order_items)): ?>
                <div class="overflow-x-auto rounded-lg shadow-md">
                    <table class="min-w-full bg-white border-collapse">
                        <thead>
                            <tr>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tl-lg">Image</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Product</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-right">Quantity</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-right">Price/Item</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-right">Subtotal</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left">Item Status</th>
                                <th class="py-3 px-4 bg-streetcart-dark text-white text-left rounded-tr-lg">Update Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr class="border-b border-streetcart-gray last:border-b-0 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <?php if($item['image']): ?>
                                            <img src="<?=htmlspecialchars($item['image'])?>" alt="Product Image" class="w-16 h-16 object-cover rounded-md shadow-sm">
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-streetcart-dark font-medium"><?=htmlspecialchars($item['product_name'])?></td>
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
                                    <td class="py-3 px-4">
                                        <form method="post" class="flex flex-col space-y-2">
                                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                            <select name="new_item_status"
                                                class="w-full p-2 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                                                <?php foreach ($allowed_item_statuses as $status): ?>
                                                    <option value="<?= htmlspecialchars($status) ?>"
                                                        <?= ($item['item_status'] == $status) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($status) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="update_item_status"
                                                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-3 rounded-md transition duration-200 ease-in-out shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 text-sm">
                                                Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="py-6 text-center text-streetcart-gray italic">No items from your inventory found in this order.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>