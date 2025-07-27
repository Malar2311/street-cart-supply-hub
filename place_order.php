<?php
session_start();
include 'db.php';

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in as vendor or not a POST request
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor' || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.php");
    exit();
}

$vendor_id = $_SESSION['user_id'];
$cartItems = $_SESSION['cart'] ?? [];
// $message is set into $_SESSION['message'] for redirection

// Get delivery details from POST
$delivery_address = $conn->real_escape_string(trim($_POST['delivery_address'] ?? ''));
$delivery_phone = $conn->real_escape_string(trim($_POST['delivery_phone'] ?? ''));

// Basic server-side validation for empty cart and required fields
if (empty($cartItems)) {
    $_SESSION['message'] = "❌ Your cart is empty. No order placed.";
    header("Location: cart.php");
    exit();
}

if (empty($delivery_address) || empty($delivery_phone)) {
    $_SESSION['message'] = "❌ Delivery address and phone number are required.";
    header("Location: checkout.php");
    exit();
}

$conn->begin_transaction(); // Start transaction for atomicity

try {
    $total_amount = 0;
    $order_products_data = []; // To store product details for order_items table

    foreach ($cartItems as $productId => $item) {
        // Double-check stock and get current product details (price, name, supplier_id)
        $stmt_check_stock = $conn->prepare("SELECT stock, price, name, supplier_id FROM products WHERE id = ?");
        if (!$stmt_check_stock) {
            throw new Exception("DB Error preparing stock check: " . $conn->error);
        }
        $stmt_check_stock->bind_param("i", $productId);
        $stmt_check_stock->execute();
        $result_stock = $stmt_check_stock->get_result();
        $productDB = $result_stock->fetch_assoc();
        $stmt_check_stock->close();

        if (!$productDB) {
            throw new Exception("Product " . htmlspecialchars($item['name']) . " not found in inventory. Order not placed.");
        }
        
        if ($productDB['stock'] < $item['quantity']) {
            throw new Exception("Not enough stock for " . htmlspecialchars($item['name']) . ". Available: " . $productDB['stock']);
        }

        // Deduct stock
        $new_stock = $productDB['stock'] - $item['quantity'];
        $stmt_deduct_stock = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        if (!$stmt_deduct_stock) {
            throw new Exception("DB Error preparing stock deduction: " . $conn->error);
        }
        $stmt_deduct_stock->bind_param("ii", $new_stock, $productId);
        if (!$stmt_deduct_stock->execute()) {
            throw new Exception("Error deducting stock for " . htmlspecialchars($item['name']) . ": " . $stmt_deduct_stock->error);
        }
        $stmt_deduct_stock->close();

        // Calculate total amount based on fresh price from DB
        $total_amount += $productDB['price'] * $item['quantity'];
        
        // Store product details for order items, using DB-fetched values
        $order_products_data[] = [
            'product_id' => $productId,
            'supplier_id' => $productDB['supplier_id'],
            'product_name' => $productDB['name'],
            'price_at_purchase' => $productDB['price'], // Use DB price for purchase record
            'quantity' => $item['quantity']
        ];
    }

    // Insert into orders table
    // IMPORTANT: Added 'order_date' and 'status' columns
    $order_status = 'Pending'; // Default status for a new order
    $stmt_order = $conn->prepare("INSERT INTO orders (vendor_id, total_amount, delivery_address, delivery_phone, order_date, status) VALUES (?, ?, ?, ?, NOW(), ?)");
    if (!$stmt_order) {
        throw new Exception("DB Error preparing order insert: " . $conn->error);
    }
    // 'idssd' -> integer, double, string, string, string (for status)
    $stmt_order->bind_param("idsss", $vendor_id, $total_amount, $delivery_address, $delivery_phone, $order_status);
    if (!$stmt_order->execute()) {
        throw new Exception("Error inserting order: " . $stmt_order->error);
    }
    $order_id = $stmt_order->insert_id;
    $stmt_order->close();

    // Insert into order_items table
    // IMPORTANT: Added 'status' column for each item
    $item_status = 'Pending'; // Default status for individual order items
    $stmt_order_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, supplier_id, product_name, price_at_purchase, quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt_order_item) {
        throw new Exception("DB Error preparing order item insert: " . $conn->error);
    }
    foreach ($order_products_data as $item_data) {
        $stmt_order_item->bind_param("iiisdis", // integer, integer, integer, string, double, integer, string
            $order_id,
            $item_data['product_id'],
            $item_data['supplier_id'],
            $item_data['product_name'],
            $item_data['price_at_purchase'],
            $item_data['quantity'],
            $item_status // Set status for each item
        );
        if (!$stmt_order_item->execute()) {
            throw new Exception("Error inserting order item for " . htmlspecialchars($item_data['product_name']) . ": " . $stmt_order_item->error);
        }
    }
    $stmt_order_item->close();

    $conn->commit(); // Commit transaction
    unset($_SESSION['cart']); // Clear the cart after successful order

    $_SESSION['message'] = "✅ Your order has been placed successfully! Order ID: " . $order_id;
    header("Location: vendor_dashboard.php?section=my_orders"); // Redirect to vendor's order history/dashboard
    exit();

} catch (Exception $e) {
    $conn->rollback(); // Rollback transaction on error
    error_log("Order Placement Error for Vendor ID {$vendor_id}: " . $e->getMessage()); // Log error with vendor ID
    $_SESSION['message'] = "❌ Order placement failed: " . htmlspecialchars($e->getMessage());
    header("Location: checkout.php"); // Redirect back to checkout with error
    exit();
}
?>