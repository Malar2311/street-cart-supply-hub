-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 27, 2025 at 02:38 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `streetcart`
--

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_address` varchar(255) NOT NULL,
  `delivery_phone` varchar(20) NOT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `vendor_id`, `total_amount`, `order_date`, `delivery_address`, `delivery_phone`, `status`) VALUES
(1, 1, 200.00, '2025-07-27 07:02:19', '123,Main Street', '1234567890', 'Pending'),
(2, 1, 100.00, '2025-07-27 11:02:24', '123,main street', '1234567890', 'Pending'),
(3, 6, 100.00, '2025-07-27 12:27:41', 'new street', '1234567878', 'Pending'),
(4, 6, 300.00, '2025-07-27 12:30:13', 'new street', '1234567878', 'Shipped');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `supplier_id`, `product_name`, `price_at_purchase`, `quantity`, `status`) VALUES
(1, 1, 3, 3, 'Pani poori', 20.00, 6, 'Pending'),
(2, 1, 4, 3, 'Pani poori', 20.00, 4, 'Pending'),
(3, 2, 6, 3, 'Pani poori', 20.00, 4, 'Pending'),
(4, 2, 8, 2, 'Pani poori', 20.00, 1, 'Shipped'),
(5, 3, 8, 2, 'Pani poori', 20.00, 5, 'Pending'),
(6, 4, 12, 7, 'Rice', 300.00, 1, 'Shipped');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `supplier_id`, `name`, `description`, `price`, `stock`, `image`) VALUES
(2, 2, 'Dosa', NULL, 25.00, 5, 'uploads/1753521495_WhatsApp Image 2025-07-24 at 11.30.12_a2618435.jpg'),
(3, 3, 'Pani poori', NULL, 20.00, 10, 'uploads/1753521884_WhatsApp Image 2025-07-24 at 11.30.12_a2618435.jpg'),
(4, 3, 'Pani poori', NULL, 20.00, 2, 'uploads/1753522330_WhatsApp Image 2025-07-24 at 11.30.12_a2618435.jpg'),
(5, 3, 'Pani poori', NULL, 20.00, 6, 'uploads/1753522336_WhatsApp Image 2025-07-24 at 11.30.12_a2618435.jpg'),
(6, 3, 'Pani poori', NULL, 20.00, 2, 'uploads/1753522342_WhatsApp Image 2025-07-24 at 11.30.12_a2618435.jpg'),
(8, 2, 'Pani poori', NULL, 20.00, 3, 'uploads/1753525546_WhatsApp Image 2025-07-24 at 11.36.36_cf10817d.jpg'),
(9, 2, 'Dosa', NULL, 24.00, 5, 'uploads/1753595778_WhatsApp Image 2025-07-24 at 11.30.12_a2618435.jpg'),
(10, 2, 'Dosa', NULL, 25.09, 4, 'uploads/1753595897_WhatsApp Image 2025-07-24 at 11.30.12_a2618435.jpg'),
(11, 2, 'apple', NULL, 44.01, 10, 'uploads/1753596032_WhatsApp Image 2025-07-24 at 11.36.36_cf10817d.jpg'),
(12, 7, 'Rice', '4 kg', 300.00, 4, 'uploads/1753619346_68861b9270b1a.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('vendor','supplier') DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `address`, `phone_number`, `contact_number`, `description`) VALUES
(1, 'vendor', 'vendor@gmail.com', '$2y$10$C0TD.N/DKwlnOd1fjwk4.u5v/J2gyLwadcysW8uNqjYyd6Cx.VcuS', 'vendor', '123,main street', '1234567890', NULL, NULL),
(2, 'supplier', 'supplier@gmail.com', '$2y$10$.cZkGYZjaA1vj0i8.PhcmOg45H6HCVp1/bcktzz8FoU0r2GzrHiCi', 'supplier', '123,Coimbatore', '8122009311', NULL, NULL),
(3, 'Mathu', 'mathu@gmail.com', '$2y$10$TZRBlcaewRyhNfHl5rD5Cew5mJXiMsyXsIpbuR5W7I0DpXAqaSc7u', 'supplier', NULL, NULL, NULL, NULL),
(4, 'Malar', 'malar@gmail.com', '$2y$10$ZxmlYRJw0RquqSwjdUD5z.xiCp36ca7sBeB7b0nAeR6oieZ4kqEIm', 'vendor', NULL, NULL, NULL, NULL),
(5, 'Malar S', 'malars@gmail.com', '$2y$10$.1xIYBYtDqkrlBUNrtbn/eGdruqoYzS5hX3HWjgK21WoRcWDmcvl2', 'vendor', NULL, NULL, NULL, NULL),
(6, 'Newuser', 'new@gmail.com', '$2y$10$r/OyMOf6T6/CZP6.Y3OtvueaCGv8CXt.WducbiF3Kr3PmbTgFd7wu', 'vendor', 'new street ', '1234567878', NULL, NULL),
(7, 'new supplier', 'newsupplier@gmail.com', '$2y$10$WcAjbLYOqqtgUQ35awevc.yWOGJMo6ZahWxNDm1dJd2Wade4kFTJy', 'supplier', 'new street , 1', '1111111111', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
