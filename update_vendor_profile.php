<?php
session_start();
include 'db.php';

// Redirect if not logged in as vendor or not a POST request
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor' || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.php");
    exit();
}

$vendor_id = $_SESSION['user_id'];
$address = $_POST['address'] ?? '';
$phone_number = $_POST['phone_number'] ?? '';

if (empty($address) || empty($phone_number)) {
    $_SESSION['message'] = "❌ Address and Phone Number cannot be empty.";
    header("Location: vendor_profile.php");
    exit();
}

// Update the user's address and phone number
$stmt = $conn->prepare("UPDATE users SET address = ?, phone_number = ? WHERE id = ?");
if ($stmt === false) {
    $_SESSION['message'] = "❌ Database error preparing update: " . $conn->error;
    error_log("DB Error (update_vendor_profile.php prepare): " . $conn->error);
    header("Location: vendor_profile.php");
    exit();
}

$stmt->bind_param("ssi", $address, $phone_number, $vendor_id);

if ($stmt->execute()) {
    $_SESSION['message'] = "✅ Profile updated successfully!";
} else {
    $_SESSION['message'] = "❌ Error updating profile: " . $stmt->error;
    error_log("DB Error (update_vendor_profile.php execute): " . $stmt->error);
}

$stmt->close();
header("Location: vendor_profile.php");
exit();
?>