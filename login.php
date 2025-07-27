<?php
session_start(); // Must be the very first line

// Include database connection (assuming db.php exists and establishes $conn)
include 'db.php';

$message = ''; // Initialize message variable

// Show success message after signup redirection
if (isset($_GET['signup']) && $_GET['signup'] == 'success') {
    $message = "✅ Signup successful! Please login.";
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize email input to prevent SQL injection
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password']; // Password is not escaped as it will be hashed/verified
    $role_input = $_POST['role'] ?? ''; // Assuming you have a hidden or selected 'role' input in your form

    // Query database for user with the provided email
    // Use prepared statements for security
    // IMPORTANT: It seems your original query might be missing 'role = ?' if you intended to filter by role at login.
    // If roles are selected by user, ensure the form sends it correctly.
    // If roles are determined by the user's email/account type, you might not need role in WHERE clause.
    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?"); // Removed AND role = ? for flexibility, add back if needed
    if ($stmt === false) {
        $message = "Database error preparing query: " . $conn->error;
        error_log("Login DB Error: " . $conn->error);
    } else {
        $stmt->bind_param("s", $email); // Only one 's' if role is not in WHERE clause
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc(); // Fetch user data
        $stmt->close();

        if ($user) {
            // Verify password against hashed password in database
            if (password_verify($password, $user['password'])) {

                // --- CRITICAL SESSION MANAGEMENT ---
                // 1. Regenerate session ID for security and to ensure a clean session.
                //    'true' also deletes the old session file on the server.
                session_regenerate_id(true);

                // 2. Set session variables upon successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['name']; // Assuming 'name' is the column for username

                // 3. Explicitly initialize/clear the cart for the *new* session upon login.
                //    This guarantees a clean cart for every new login.
                $_SESSION['cart'] = []; // <--- ADD THIS LINE

                // Redirect based on user role
                if ($user['role'] == 'vendor') {
                    header("Location: vendor_dashboard.php");
                } else { // Assuming 'supplier' or any other role
                    header("Location: supplier_dashboard.php");
                }
                exit(); // Ensure no further code is executed after redirection
            } else {
                $message = "❌ Incorrect password!"; // Message for wrong password
            }
        } else {
            $message = "❌ Email not found!"; // Message for email not found
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - StreetCart Supply Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Tailwind CSS custom configuration for fonts and colors
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        // Define 'poppins' as a custom font family
                        poppins: ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        // Custom color palette for StreetCart branding (consistent with landing page)
                        'streetcart-orange': '#FF6B35', // Primary accent color
                        'streetcart-dark': '#2C3E50',   // Dark text and elements
                        'streetcart-light': '#ECF0F1',   // Light background
                        'streetcart-gray': '#BDC3C7',   // Muted gray
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
            <a href="index.php" class="inline-block">
                <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
                    Back to Home
                </button>
            </a>
        </div>
    </nav>

    <div class="bg-white p-8 md:p-12 rounded-xl shadow-lg w-full flex-grow flex flex-col items-center justify-center">
        <div class="max-w-xl w-full">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-streetcart-dark mb-6 md:mb-8">Login</h2>

            <?php if($message): ?>
                <div class="p-3 mb-4 rounded-lg text-center font-medium
                    <?= strpos($message,'✅')===0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?=htmlspecialchars($message)?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4 md:space-y-6">
                <input name="email" type="email" placeholder="Email" required
                    class="w-full p-3 md:p-4 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 text-lg">

                <div class="relative">
                    <input name="password" type="password" placeholder="Password" id="password" required
                        class="w-full p-3 md:p-4 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 pr-12 text-lg">
                    <button type="button" onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-streetcart-orange hover:text-orange-700 cursor:pointer focus:outline-none">
                        Show
                    </button>
                </div>

                <button type="submit"
                    class="w-full bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 md:py-4 rounded-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75 text-lg">
                    Login
                </button>
            </form>

            <p class="mt-6 text-center text-streetcart-dark text-lg">
                Don’t have an account?
                <a href="signup.php" class="text-streetcart-orange hover:underline font-semibold">Signup</a>
            </p>
            <p class="text-center text-sm mt-2">
                <a href="forgot_password.php" class="text-streetcart-orange hover:underline font-semibold">Forgot password?</a>
            </p>
        </div>
    </div>

    <script>
    // JavaScript function to toggle password visibility
    function togglePassword() {
        var pwd = document.getElementById("password"); // Get the password input element
        var toggleButton = event.target; // Get the button that was clicked

        // Toggle the input type between 'password' and 'text'
        if (pwd.type === "password") {
            pwd.type = "text";
            toggleButton.innerText = "Hide"; // Change button text to 'Hide'
        } else {
            pwd.type = "password";
            toggleButton.innerText = "Show"; // Change button text to 'Show'
        }
    }
    </script>
</body>
</html>