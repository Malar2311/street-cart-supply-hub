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

// Retrieve vendor's current profile data
$stmt = $conn->prepare("SELECT name, email, address, phone_number FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendor_data = $result->fetch_assoc();
    $stmt->close();

    if (!$vendor_data) {
        $_SESSION['message'] = "Error: Vendor profile not found.";
        header("Location: vendor_dashboard.php");
        exit();
    }
} else {
    $_SESSION['message'] = "Database error fetching profile: " . $conn->error;
    error_log("DB Error (vendor_profile.php fetch): " . $conn->error);
    header("Location: vendor_dashboard.php");
    exit();
}

// Check for messages from update_vendor_profile.php
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
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

    <nav class="bg-white shadow-md p-4 flex items-center justify-between w-full max-w-full rounded-b-lg mb-8 z-10 relative">
        <div class="flex items-center space-x-2">
            <img src="images/logo.png" alt="StreetCart Logo" class="h-8 md:h-10 rounded-full">
            <span class="text-xl md:text-2xl font-semibold text-streetcart-dark">StreetCart</span>
        </div>
        <div>
            <a href="vendor_dashboard.php" class="inline-block mr-4">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    Back to Dashboard
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
                My Profile 👤
            </h2>

            <?php if (!empty($message)): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= strpos($message,'✅')===0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <span class="block sm:inline"><?= htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <form action="update_vendor_profile.php" method="post" class="space-y-6 p-6 border border-streetcart-gray rounded-lg shadow-sm">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" id="name" required
                        class="w-full p-3 border border-streetcart-gray rounded-lg bg-gray-100 cursor-not-allowed"
                        value="<?= htmlspecialchars($vendor_data['name'] ?? '') ?>" readonly>
                    <p class="text-xs text-streetcart-gray mt-1">Your name cannot be changed here.</p>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="email" required
                        class="w-full p-3 border border-streetcart-gray rounded-lg bg-gray-100 cursor-not-allowed"
                        value="<?= htmlspecialchars($vendor_data['email'] ?? '') ?>" readonly>
                    <p class="text-xs text-streetcart-gray mt-1">Your email cannot be changed here.</p>
                </div>
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Delivery Address</label>
                    <textarea name="address" id="address" rows="4" required
                        class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200"
                        placeholder="E.g., 123 Main St, Anytown, Tamil Nadu, 641001"><?= htmlspecialchars($vendor_data['address'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input type="tel" name="phone_number" id="phone_number" required
                        class="w-full p-3 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200"
                        placeholder="E.g., +91 98765 43210" value="<?= htmlspecialchars($vendor_data['phone_number'] ?? '') ?>">
                </div>
                <button type="submit" class="w-full bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 rounded-lg text-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75">
                    Update Profile
                </button>
            </form>
        </div>
    </div>
</body>
</html>