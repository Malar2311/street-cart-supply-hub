<?php
session_start();
include 'db.php'; // Include your database connection

// IMPORTANT: Basic security check for AJAX calls
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor') {
    // Return an empty table body or an error message if not authorized
    echo '<tbody><tr><td colspan="7" class="py-8 text-center text-red-600 text-xl">Authorization Error. Please log in again.</td></tr></tbody>';
    exit();
}

$search = $_GET['search'] ?? '';

// Base SQL query to fetch products from suppliers
// This query MUST match the one in vendor_dashboard.php for consistency
$sql = "SELECT products.*, users.name as supplier, users.id as supplier_user_id, users.address as supplier_address
        FROM products
        JOIN users ON products.supplier_id=users.id
        WHERE users.role='supplier'";

$params = [];
$types = '';

if ($search) {
    $sql .= " AND (products.name LIKE ? OR products.description LIKE ? OR users.name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$sql .= " ORDER BY products.price ASC";

$stmt = $conn->prepare($sql);

// Start outputting the tbody content
echo '<tbody>';

if ($stmt === false) {
    error_log("Failed to prepare statement for load_products.php: " . $conn->error);
    // Output an error row
    echo '<tr><td colspan="7" class="py-8 text-center text-red-600 text-xl">Error fetching products. Please try again.</td></tr>';
} else {
    if (!empty($params)) {
        $bind_names = array($types);
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }

    $stmt->execute();
    $products_result = $stmt->get_result();

    if ($products_result && $products_result->num_rows > 0) {
        while($product = $products_result->fetch_assoc()) {
            // Output each table row (<tr>) with <td> elements
            ?>
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
            <?php
        }
    } else {
        // Output a row indicating no products found
        ?>
        <tr>
            <td colspan="7" class="py-8 text-center text-streetcart-gray text-xl">No products found.</td>
        </tr>
        <?php
    }
}
echo '</tbody>'; // End outputting the tbody content
$stmt->close();
$conn->close(); // Close connection
?>