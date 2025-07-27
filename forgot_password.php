<?php
// Include database connection (assuming db.php exists and establishes $conn)
include 'db.php';

$message = ''; // Initialize message variable

// Handle form submission for password reset request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize email input
    $email = $conn->real_escape_string($_POST['email']);

    // Check if the email exists in the database
    $user_query = $conn->query("SELECT id FROM users WHERE email='$email'");
    $user = $user_query->fetch_assoc();

    if ($user) {
        // In a real application, you would generate a unique token,
        // store it in the database with an expiry, and email a reset link
        // containing this token to the user's email address.
        $message = "✅ Password reset link would be sent to your email (demo).";
    } else {
        $message = "❌ Email not found!"; // Message if email is not registered
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Forgot Password - StreetCart Supply Hub</title>
  <!-- Poppins Font from Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Tailwind CSS CDN -->
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
            'streetcart-light': '#ECF0F1',  // Light background
            'streetcart-gray': '#BDC3C7',   // Muted gray
          }
        }
      }
    }
  </script>
  <!-- Favicon link -->
  <link rel="icon" href="images/favicon.ico" type="image/x-icon">
</head>
<!--
  Body with custom font, light background, dark text.
  Uses flexbox to align content to the top and stretch horizontally.
-->
<body class="font-poppins bg-streetcart-light text-streetcart-dark min-h-screen flex flex-col p-4">

  <!--
    Navigation Bar:
    - Sticky to the top, shadow for depth.
    - Uses flexbox for alignment of logo and buttons.
    - Responsive padding and rounded bottom corners.
    - Includes a "Back to Home" button.
  -->
  <nav class="bg-white shadow-md p-4 flex items-center justify-between w-full max-w-full rounded-b-lg mb-8">
    <!-- Logo Section -->
    <div class="flex items-center space-x-2">
      <img src="images/logo.png" alt="StreetCart Logo" class="h-8 md:h-10 rounded-full">
      <span class="text-xl md:text-2xl font-semibold text-streetcart-dark">StreetCart</span>
    </div>
    <!-- Back Button -->
    <div>
      <a href="index.php" class="inline-block">
        <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
          Back to Home
        </button>
      </a>
    </div>
  </nav>

  <!--
    Form Container (full-screen equivalent):
    - White background, rounded corners (now only top-right/top-left if desired, or remove for full screen).
    - Shadow for a subtle lift.
    - Takes full width (w-full) and expands vertically (flex-grow).
    - Increased padding for better spacing in full-screen mode.
    - Removed max-width to allow it to stretch.
  -->
  <div class="bg-white p-8 md:p-12 rounded-xl shadow-lg w-full flex-grow flex flex-col items-center justify-center">
    <!-- Inner container to constrain content width for readability -->
    <div class="max-w-xl w-full">
      <!-- Heading for the form -->
      <h2 class="text-3xl md:text-4xl font-bold text-center text-streetcart-dark mb-6 md:mb-8">Forgot Password</h2>

      <?php if($message): ?>
        <!-- Message Alert (Success/Error):
          - Conditional styling based on message content.
          - Rounded corners, padding, and margin.
        -->
        <div class="p-3 mb-4 rounded-lg text-center font-medium
          <?= strpos($message,'✅')===0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
          <?=htmlspecialchars($message)?>
        </div>
      <?php endif; ?>

      <!-- Forgot Password Form -->
      <form method="post" class="space-y-4 md:space-y-6">
        <!-- Email Input -->
        <input name="email" type="email" placeholder="Enter your email" required
          class="w-full p-3 md:p-4 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 text-lg">

        <!-- Submit Button -->
        <button type="submit"
          class="w-full bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 md:py-4 rounded-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75 text-lg">
          Send Reset Link
        </button>
      </form>

      <!-- Back to Login Link -->
      <p class="mt-6 text-center text-streetcart-dark text-lg">
        <a href="login.php" class="text-streetcart-orange hover:underline font-semibold">Back to Login</a>
      </p>
    </div>
  </div>

</body>
</html>
