<?php
$conn = new mysqli("localhost", "root", "", "streetcart");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
