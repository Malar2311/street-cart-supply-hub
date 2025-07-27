<!DOCTYPE html>
<html>
<head>
  <title>StreetCart Supply Hub</title>
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
            // Custom color palette for StreetCart branding
            'streetcart-orange': '#FF6B35', // A vibrant orange for accents and primary buttons
            'streetcart-dark': '#2C3E50', // Dark text and secondary button background
            'streetcart-light': '#ECF0F1', // Light background for the page
            'streetcart-gray': '#BDC3C7', // Muted gray for subtle elements
          }
        }
      }
    }
  </script>
  <!-- Favicon link -->
  <link rel="icon" href="images/favicon.ico" type="image/x-icon">
</head>
<!--
  Body with custom font, light background, dark text,
  and a flex column layout to push the hero section to fill available space.
-->
<body class="font-poppins bg-streetcart-light text-streetcart-dark min-h-screen flex flex-col">

  <!--
    Navigation Bar:
    - Sticky to the top, shadow for depth.
    - Uses flexbox for alignment of logo and buttons.
    - Responsive padding and rounded bottom corners.
  -->
  <nav class="bg-white shadow-md p-4 flex items-center justify-between sticky top-0 z-50 rounded-b-lg">
    <!-- Logo Section -->
    <div class="flex items-center space-x-2">
      <!-- Logo image with responsive height and rounded corners -->
      <img src="images/logo.png" alt="StreetCart Logo" class="h-8 md:h-10 rounded-full">
      <!-- Brand name with responsive font size and bold text -->
      <span class="text-xl md:text-2xl font-semibold text-streetcart-dark">StreetCart</span>
    </div>
    <!-- Navigation Buttons -->
    <div class="space-x-4">
      <!-- Login Button:
        - Vibrant orange background, white text.
        - Rounded full, bold font, responsive padding.
        - Smooth transition for hover effects, shadow for depth.
        - Focus ring for accessibility.
      -->
      <a href="login.php" class="inline-block">
        <button class="bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-orange focus:ring-opacity-75">
          Login
        </button>
      </a>
      <!-- Signup Button:
        - Dark background, white text (secondary action).
        - Similar styling to Login button for consistency.
      -->
      <a href="signup.php" class="inline-block">
        <button class="bg-streetcart-dark hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-full transition duration-300 ease-in-out shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-streetcart-dark focus:ring-opacity-75">
          Signup
        </button>
      </a>
    </div>
  </nav>

  <!--
    Hero Section:
    - Takes remaining vertical space (flex-grow).
    - Centers content horizontally and vertically.
    - Text aligned to center, responsive padding.
    - Subtle gradient background for visual interest.
  -->
  <header class="flex-grow flex items-center justify-center text-center p-6 md:p-12 bg-gradient-to-br from-streetcart-light to-blue-100">
    <div class="max-w-4xl">
      <!-- Main Heading:
        - Large, extra bold font, responsive sizing.
        - Leading tight for compact lines, dark text.
        - Margin bottom for spacing.
      -->
      <h1 class="text-4xl md:text-6xl font-extrabold leading-tight mb-4 text-streetcart-dark">
        Connecting street food vendors to trusted suppliers.
      </h1>
      <!-- Sub-paragraph:
        - Responsive font size, slightly muted text color.
        - Margin bottom for spacing.
      -->
      <p class="text-lg md:text-xl text-streetcart-dark opacity-90 mb-8">
        Affordable raw materials • Verified suppliers • Empower local food businesses
      </p>
      <!--
        Optional Call to Action Button (uncomment to include):
        <a href="signup.php" class="inline-block bg-streetcart-orange hover:bg-orange-700 text-white font-bold py-3 px-8 rounded-full text-lg transition duration-300 ease-in-out shadow-xl hover:shadow-2xl">
          Get Started Today
        </a>
      -->
    </div>
  </header>

</body>
</html>
