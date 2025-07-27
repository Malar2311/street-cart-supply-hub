<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'supplier') {
    header("Location: login.php");
    exit();
}

$supplier_id = $_SESSION['user_id'];
$message = '';

// Retrieve and clear session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Fetch supplier details
$supplier_details = [];
$stmt = $conn->prepare("SELECT name, email, phone_number, address FROM users WHERE id = ? AND role = 'supplier'");
if ($stmt === false) {
    error_log("DB Error preparing supplier profile query: " . $conn->error);
    $_SESSION['message'] = "❌ Database error fetching profile details.";
    header("Location: supplier_dashboard.php");
    exit();
}
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$supplier_details = $result->fetch_assoc();
$stmt->close();

if (!$supplier_details) {
    $_SESSION['message'] = "❌ Your profile could not be found.";
    header("Location: supplier_dashboard.php");
    exit();
}

// Handle profile update (POST request)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $newPhone = trim($_POST['phone']); // This $_POST key 'phone' is correct as per your HTML name attribute
    $newAddress = trim($_POST['address']);

    // Basic validation
    if (empty($newName) || empty($newEmail) || empty($newPhone) || empty($newAddress)) {
        $_SESSION['message'] = "❌ All fields are required.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "❌ Invalid email format.";
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, address = ? WHERE id = ? AND role = 'supplier'");
        if ($update_stmt === false) {
            error_log("DB Error preparing supplier profile update: " . $conn->error);
            $_SESSION['message'] = "❌ Database error updating profile.";
        } else {
            $update_stmt->bind_param("ssssi", $newName, $newEmail, $newPhone, $newAddress, $supplier_id);
            if ($update_stmt->execute()) {
                $_SESSION['message'] = "✅ Profile updated successfully!";
                // Update session username if name changed
                $_SESSION['username'] = $newName;
                // Refresh details after update with the correct key
                $supplier_details['name'] = $newName;
                $supplier_details['email'] = $newEmail;
                $supplier_details['phone_number'] = $newPhone; // <<< CHANGE THIS LINE
                $supplier_details['address'] = $newAddress;
            } else {
                $_SESSION['message'] = "❌ Error updating profile: " . $update_stmt->error;
                error_log("DB Error executing supplier profile update: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
    }
    // Redirect to prevent form re-submission
    header("Location: supplier_profile.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile - StreetCart Supply Hub</title>
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
            <a href="supplier_dashboard.php" class="inline-block">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    ← Back to Dashboard
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
        <div class="max-w-xl w-full">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-streetcart-dark mb-6 md:mb-8">
                👤 My Profile - <?= htmlspecialchars($supplier_details['name'] ?? 'Supplier') ?>
            </h2>

            <?php if (!empty($message)): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= strpos($message,'✅')===0 || strpos($message,'successfully')!==false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <span class="block sm:inline"><?= htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" id="name" value="<?= htmlspecialchars($supplier_details['name'] ?? '') ?>" required
                            class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($supplier_details['email'] ?? '') ?>" required
                            class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($supplier_details['phone_number'] ?? '') ?>" required
                            class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200">
                </div>
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" id="address" rows="3" required
                                  class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 resize-y"><?= htmlspecialchars($supplier_details['address'] ?? '') ?></textarea>
                </div>
                <div class="flex justify-end mt-6">
                    <button type="submit" name="update_profile"
                                class="bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75">
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>