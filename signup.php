<?php
// Include database connection (assuming db.php exists and establishes $conn)
include 'db.php';

$message = ''; // Initialize message variable

// Handle signup form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs to prevent SQL injection and hash password
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
    $role = $conn->real_escape_string($_POST['role']); // Sanitize role input

    // Check if email already exists in the database
    $exists_query = $conn->query("SELECT id FROM users WHERE email='$email'");
    $exists = $exists_query->fetch_assoc();

    if ($exists) {
        $message = "❌ Email already registered!"; // Message if email exists
    } else {
        // Insert new user into the database
        $insert_query = $conn->query("INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')");
        if ($insert_query) {
            // Redirect to login page with success message
            header("Location: login.php?signup=success");
            exit(); // Ensure no further code is executed after redirection
        } else {
            $message = "❌ Signup failed. Please try again."; // Message for database insertion error
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Signup - StreetCart Supply Hub</title>
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
    - White background, rounded corners.
    - Shadow for a subtle lift.
    - Takes full width (w-full) and expands vertically (flex-grow).
    - Increased padding for better spacing in full-screen mode.
  -->
  <div class="bg-white p-8 md:p-12 rounded-xl shadow-lg w-full flex-grow flex flex-col items-center justify-center">
    <!-- Inner container to constrain content width for readability -->
    <div class="max-w-xl w-full">
      <!-- Heading for the form -->
      <h2 class="text-3xl md:text-4xl font-bold text-center text-streetcart-dark mb-6 md:mb-8">Signup</h2>

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

      <!-- Signup Form -->
      <form method="post" class="space-y-4 md:space-y-6">
        <!-- Full Name Input -->
        <input name="name" placeholder="Full Name" required
          class="w-full p-3 md:p-4 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 text-lg">

        <!-- Email Input -->
        <input name="email" type="email" placeholder="Email" required
          class="w-full p-3 md:p-4 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 text-lg">

        <!-- Password Input with Show/Hide Toggle -->
        <div class="relative">
          <input name="password" type="password" placeholder="Password" id="password" required
            class="w-full p-3 md:p-4 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 pr-12 text-lg">
          <button type="button" onclick="togglePassword()"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-streetcart-orange hover:text-orange-700 cursor-pointer focus:outline-none">
            Show
          </button>
        </div>

        <!-- Role Selection Dropdown -->
        <select name="role" required
          class="w-full p-3 md:p-4 border border-streetcart-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange transition duration-200 bg-white text-lg">
          <option value="">-- Select Role --</option>
          <option value="vendor">Vendor</option>
          <option value="supplier">Supplier</option>
        </select>

        <!-- Submit Button -->
        <button type="submit"
          class="w-full bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 md:py-4 rounded-lg transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75 text-lg">
          Signup
        </button>
      </form>

      <!-- Login Link -->
      <p class="mt-6 text-center text-streetcart-dark text-lg">
        Already have an account?
        <a href="login.php" class="text-streetcart-orange hover:underline font-semibold">Login</a>
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
