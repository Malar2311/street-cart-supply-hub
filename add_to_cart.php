<?php
session_start();
include 'db.php'; // Include your database connection

header('Content-Type: application/json'); // Respond with JSON

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id'])) {
    $productId = intval($_POST['product_id']);
    $productName = htmlspecialchars($_POST['product_name']);
    $productPrice = floatval($_POST['product_price']);

    // Fetch actual product details from the database for verification and to get stock
    $stmt = $conn->prepare("SELECT id, name, price, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit();
    }

    $availableStock = $product['stock'];

    // Initialize cart if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Check if product is already in cart
    if (isset($_SESSION['cart'][$productId])) {
        // If product is in cart, increment quantity
        $currentQuantity = $_SESSION['cart'][$productId]['quantity'];
        if ($currentQuantity + 1 <= $availableStock) {
            $_SESSION['cart'][$productId]['quantity']++;
            echo json_encode([
                'success' => true,
                'message' => 'Product quantity updated in cart!',
                'cart_count' => count($_SESSION['cart'])
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot add more, maximum stock reached.'
            ]);
        }
    } else {
        // Add new product to cart
        if ($availableStock > 0) {
            $_SESSION['cart'][$productId] = [
                'id' => $productId,
                'name' => $productName,
                'price' => $productPrice,
                'quantity' => 1,
                'image' => $product['image'] ?? null // Assuming image path is available in products table
            ];
            echo json_encode([
                'success' => true,
                'message' => 'Product added to cart!',
                'cart_count' => count($_SESSION['cart'])
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Product is out of stock.'
            ]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>