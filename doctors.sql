-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 18, 2025 at 08:20 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `doctors`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_datetime` datetime NOT NULL,
  `status` enum('scheduled','completed','canceled') NOT NULL DEFAULT 'scheduled',
  `visit_duration_minutes` int(11) DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `slot_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_datetime`, `status`, `visit_duration_minutes`, `follow_up_required`, `reason`, `created_at`, `slot_id`) VALUES
(59, 29, 57, '2025-08-17 17:30:00', 'scheduled', 30, 0, '', '2025-08-17 14:14:18', 416);

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE `chats` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','accepted','closed') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chats`
--

INSERT INTO `chats` (`id`, `patient_id`, `doctor_id`, `created_at`, `status`) VALUES
(15, 29, 45, '2025-08-17 16:57:33', 'closed'),
(21, 29, 57, '2025-08-17 17:20:42', 'accepted');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('new','replied') NOT NULL DEFAULT 'new',
  `reply` text DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `created_at`, `status`, `reply`, `replied_at`) VALUES
(13, 'Wasim Shalah', 'waseemshalah@gmail.com', '15415', 'iugibolnkl;', '2025-08-08 21:49:07', 'new', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `doctor_applications`
--

CREATE TABLE `doctor_applications` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL DEFAULT '',
  `location` varchar(100) NOT NULL DEFAULT '',
  `gender` enum('male','female','other') NOT NULL DEFAULT 'other',
  `date_of_birth` date DEFAULT NULL,
  `license_number` varchar(50) NOT NULL,
  `specialization_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `cv_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_applications`
--

INSERT INTO `doctor_applications` (`id`, `name`, `email`, `phone`, `location`, `gender`, `date_of_birth`, `license_number`, `specialization_id`, `message`, `cv_path`, `status`, `created_at`, `user_id`) VALUES
(44, 'aybk abo alheja', 'aybk.1999@gmail.com', '0511111111', 'tamra', 'male', '1999-05-09', '13-555555', 13, 'ascavavasv', 'uploads/cvs/1755439434_68a1e14ade16d8.67789614.pdf', 'completed', '2025-08-17 14:03:54', 208135020);

-- --------------------------------------------------------

--
-- Stand-in structure for view `doctor_ratings_summary`
-- (See below for the actual view)
--
CREATE TABLE `doctor_ratings_summary` (
`doctor_id` int(11)
,`avg_rating` decimal(4,2)
,`ratings_count` bigint(21)
,`avg_wait_time` decimal(6,2)
,`avg_service` decimal(6,2)
,`avg_communication` decimal(6,2)
,`avg_facilities` decimal(6,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `medical_reports`
--

CREATE TABLE `medical_reports` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `patient_id` int(11) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_reports`
--

INSERT INTO `medical_reports` (`id`, `doctor_id`, `appointment_id`, `diagnosis`, `created_at`, `patient_id`, `description`) VALUES
(61, 57, 59, 'asdjio1', '2025-08-17 17:15:52', 29, 'jgwekfsg');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `generic_name` varchar(120) DEFAULT NULL,
  `dosage_form` varchar(120) DEFAULT NULL,
  `strength` varchar(120) DEFAULT NULL,
  `is_otc` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `is_prescription_required` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `name`, `generic_name`, `dosage_form`, `strength`, `is_otc`, `created_at`, `updated_at`, `is_prescription_required`) VALUES
(3, 'Amoxicillin', 'Amoxicillin', 'Capsule', '500 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(4, 'Paracetamol', 'Acetaminophen', 'Tablet', '500 mg', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(5, 'Ibuprofen', 'Ibuprofen', 'Tablet', '400 mg', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(6, 'Azithromycin', 'Azithromycin', 'Tablet', '250 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(7, 'Metformin', 'Metformin Hydrochloride', 'Tablet', '850 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(8, 'Atorvastatin', 'Atorvastatin Calcium', 'Tablet', '20 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(9, 'Omeprazole', 'Omeprazole', 'Capsule', '20 mg', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(10, 'Lisinopril', 'Lisinopril', 'Tablet', '10 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(11, 'Levothyroxine', 'Levothyroxine Sodium', 'Tablet', '100 mcg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(12, 'Cetirizine', 'Cetirizine Hydrochloride', 'Tablet', '10 mg', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(13, 'Amlodipine', 'Amlodipine Besylate', 'Tablet', '5 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(14, 'Ciprofloxacin', 'Ciprofloxacin', 'Tablet', '500 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(15, 'Furosemide', 'Furosemide', 'Tablet', '40 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(16, 'Clopidogrel', 'Clopidogrel Bisulfate', 'Tablet', '75 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(17, 'Salbutamol Inhaler', 'Salbutamol Sulfate', 'Inhaler', '100 mcg/dose', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(18, 'Prednisone', 'Prednisone', 'Tablet', '20 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(19, 'Warfarin', 'Warfarin Sodium', 'Tablet', '5 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(20, 'Losartan', 'Losartan Potassium', 'Tablet', '50 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(21, 'Metoprolol', 'Metoprolol Tartrate', 'Tablet', '50 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(22, 'Hydrochlorothiazide', 'Hydrochlorothiazide', 'Tablet', '25 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(23, 'Insulin Glargine', 'Insulin Glargine', 'Injection', '100 IU/ml', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(24, 'Alprazolam', 'Alprazolam', 'Tablet', '0.5 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(25, 'Diazepam', 'Diazepam', 'Tablet', '5 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(26, 'Morphine', 'Morphine Sulfate', 'Injection', '10 mg/ml', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(27, 'Tramadol', 'Tramadol Hydrochloride', 'Capsule', '50 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(28, 'Erythromycin', 'Erythromycin', 'Tablet', '250 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(29, 'Doxycycline', 'Doxycycline Hyclate', 'Capsule', '100 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(30, 'Hydrocortisone Cream', 'Hydrocortisone', 'Cream', '1%', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(31, 'Fluoxetine', 'Fluoxetine Hydrochloride', 'Capsule', '20 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(32, 'Sertraline', 'Sertraline Hydrochloride', 'Tablet', '50 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(33, 'Ranitidine', 'Ranitidine Hydrochloride', 'Tablet', '150 mg', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(34, 'Montelukast', 'Montelukast Sodium', 'Tablet', '10 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(35, 'Ketoconazole', 'Ketoconazole', 'Cream', '2%', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(36, 'Naproxen', 'Naproxen', 'Tablet', '500 mg', 1, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 0),
(37, 'Allopurinol', 'Allopurinol', 'Tablet', '300 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(38, 'Digoxin', 'Digoxin', 'Tablet', '0.25 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(39, 'Propranolol', 'Propranolol Hydrochloride', 'Tablet', '40 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(40, 'Topiramate', 'Topiramate', 'Tablet', '50 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(41, 'Lamotrigine', 'Lamotrigine', 'Tablet', '100 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(42, 'Levetiracetam', 'Levetiracetam', 'Tablet', '500 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(43, 'Valproic Acid', 'Sodium Valproate', 'Tablet', '500 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(44, 'Clonazepam', 'Clonazepam', 'Tablet', '1 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(45, 'Olanzapine', 'Olanzapine', 'Tablet', '10 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(46, 'Quetiapine', 'Quetiapine Fumarate', 'Tablet', '200 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(47, 'Risperidone', 'Risperidone', 'Tablet', '2 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(48, 'Haloperidol', 'Haloperidol', 'Tablet', '5 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(49, 'Lithium', 'Lithium Carbonate', 'Tablet', '300 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(50, 'Mirtazapine', 'Mirtazapine', 'Tablet', '30 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(51, 'Buspirone', 'Buspirone Hydrochloride', 'Tablet', '10 mg', 0, '2025-08-09 01:32:42', '2025-08-09 01:32:42', 1),
(52, 'Amoxicillin', 'Amoxicillin', 'Capsule', '500 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(53, 'Paracetamol', 'Acetaminophen', 'Tablet', '500 mg', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(54, 'Ibuprofen', 'Ibuprofen', 'Tablet', '400 mg', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(55, 'Azithromycin', 'Azithromycin', 'Tablet', '250 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(56, 'Metformin', 'Metformin Hydrochloride', 'Tablet', '850 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(57, 'Atorvastatin', 'Atorvastatin Calcium', 'Tablet', '20 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(58, 'Omeprazole', 'Omeprazole', 'Capsule', '20 mg', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(59, 'Lisinopril', 'Lisinopril', 'Tablet', '10 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(60, 'Levothyroxine', 'Levothyroxine Sodium', 'Tablet', '100 mcg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(61, 'Cetirizine', 'Cetirizine Hydrochloride', 'Tablet', '10 mg', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(62, 'Amlodipine', 'Amlodipine Besylate', 'Tablet', '5 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(63, 'Ciprofloxacin', 'Ciprofloxacin', 'Tablet', '500 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(64, 'Furosemide', 'Furosemide', 'Tablet', '40 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(65, 'Clopidogrel', 'Clopidogrel Bisulfate', 'Tablet', '75 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(66, 'Salbutamol Inhaler', 'Salbutamol Sulfate', 'Inhaler', '100 mcg/dose', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(67, 'Prednisone', 'Prednisone', 'Tablet', '20 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(68, 'Warfarin', 'Warfarin Sodium', 'Tablet', '5 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(69, 'Losartan', 'Losartan Potassium', 'Tablet', '50 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(70, 'Metoprolol', 'Metoprolol Tartrate', 'Tablet', '50 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(71, 'Hydrochlorothiazide', 'Hydrochlorothiazide', 'Tablet', '25 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(72, 'Insulin Glargine', 'Insulin Glargine', 'Injection', '100 IU/ml', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(73, 'Alprazolam', 'Alprazolam', 'Tablet', '0.5 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(74, 'Diazepam', 'Diazepam', 'Tablet', '5 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(75, 'Morphine', 'Morphine Sulfate', 'Injection', '10 mg/ml', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(76, 'Tramadol', 'Tramadol Hydrochloride', 'Capsule', '50 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(77, 'Erythromycin', 'Erythromycin', 'Tablet', '250 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(78, 'Doxycycline', 'Doxycycline Hyclate', 'Capsule', '100 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(79, 'Hydrocortisone Cream', 'Hydrocortisone', 'Cream', '1%', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(80, 'Fluoxetine', 'Fluoxetine Hydrochloride', 'Capsule', '20 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(81, 'Sertraline', 'Sertraline Hydrochloride', 'Tablet', '50 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(82, 'Ranitidine', 'Ranitidine Hydrochloride', 'Tablet', '150 mg', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(83, 'Montelukast', 'Montelukast Sodium', 'Tablet', '10 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(84, 'Ketoconazole', 'Ketoconazole', 'Cream', '2%', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(85, 'Naproxen', 'Naproxen', 'Tablet', '500 mg', 1, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 0),
(86, 'Allopurinol', 'Allopurinol', 'Tablet', '300 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(87, 'Digoxin', 'Digoxin', 'Tablet', '0.25 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(88, 'Propranolol', 'Propranolol Hydrochloride', 'Tablet', '40 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(89, 'Topiramate', 'Topiramate', 'Tablet', '50 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(90, 'Lamotrigine', 'Lamotrigine', 'Tablet', '100 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(91, 'Levetiracetam', 'Levetiracetam', 'Tablet', '500 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(92, 'Valproic Acid', 'Sodium Valproate', 'Tablet', '500 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(93, 'Clonazepam', 'Clonazepam', 'Tablet', '1 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(94, 'Olanzapine', 'Olanzapine', 'capsule', '10 mg', 0, '2025-08-09 01:34:48', '2025-08-15 12:29:23', 1),
(95, 'Quetiapine', 'Quetiapine Fumarate', 'Tablet', '200 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(96, 'Risperidone', 'Risperidone', 'Tablet', '2 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(97, 'Haloperidol', 'Haloperidol', 'Tablet', '5 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(98, 'Lithium', 'Lithium Carbonate', 'Tablet', '300 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(99, 'Mirtazapine', 'Mirtazapine', 'Tablet', '30 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(100, 'Buspirone', 'Buspirone Hydrochloride', 'Tablet', '10 mg', 0, '2025-08-09 01:34:48', '2025-08-09 01:34:48', 1),
(102, 'Olanzapine', 'Olanzapine', 'Cream', '500 mg', 1, '2025-08-15 15:45:31', '2025-08-15 15:45:31', 0),
(103, 'Paracetamol', 'Acetaminophen', 'Tablet', '325 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(104, 'Paracetamol', 'Acetaminophen', 'Tablet', '500 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(105, 'Paracetamol', 'Acetaminophen', 'Extended Release Tablet', '650 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(106, 'Paracetamol', 'Acetaminophen', 'Oral Suspension', '120 mg/5 mL', 1, '2025-08-15 23:27:18', NULL, 0),
(107, 'Paracetamol', 'Acetaminophen', 'Suppository', '325 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(108, 'Ibuprofen', 'Ibuprofen', 'Tablet', '200 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(109, 'Ibuprofen', 'Ibuprofen', 'Tablet', '400 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(110, 'Ibuprofen', 'Ibuprofen', 'Oral Suspension', '100 mg/5 mL', 1, '2025-08-15 23:27:18', NULL, 0),
(111, 'Aspirin', 'Acetylsalicylic Acid', 'Tablet', '81 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(112, 'Aspirin', 'Acetylsalicylic Acid', 'Tablet', '325 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(113, 'Amoxicillin', 'Amoxicillin', 'Oral Suspension', '125 mg/5 mL', 0, '2025-08-15 23:27:18', NULL, 1),
(114, 'Amoxicillin', 'Amoxicillin', 'Oral Suspension', '250 mg/5 mL', 0, '2025-08-15 23:27:18', NULL, 1),
(115, 'Paracetamol', 'Acetaminophen', 'Oral Suspension', '160 mg/5 mL', 1, '2025-08-15 23:27:18', NULL, 0),
(116, 'Ibuprofen', 'Ibuprofen', 'Oral Suspension', '100 mg/5 mL', 1, '2025-08-15 23:27:18', NULL, 0),
(117, 'ORS', 'Oral Rehydration Salts', 'Powder for Solution', '20.5 g sachet', 1, '2025-08-15 23:27:18', NULL, 0),
(118, 'Amlodipine', 'Amlodipine Besylate', 'Tablet', '5 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(119, 'Amlodipine', 'Amlodipine Besylate', 'Tablet', '10 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(120, 'Enalapril', 'Enalapril Maleate', 'Tablet', '5 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(121, 'Enalapril', 'Enalapril Maleate', 'Tablet', '10 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(122, 'Atenolol', 'Atenolol', 'Tablet', '25 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(123, 'Atenolol', 'Atenolol', 'Tablet', '50 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(124, 'Atorvastatin', 'Atorvastatin Calcium', 'Tablet', '10 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(125, 'Atorvastatin', 'Atorvastatin Calcium', 'Tablet', '40 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(126, 'Hydrocortisone', 'Hydrocortisone', 'Cream', '1%', 1, '2025-08-15 23:27:18', NULL, 0),
(127, 'Hydrocortisone', 'Hydrocortisone', 'Cream', '2.5%', 0, '2025-08-15 23:27:18', NULL, 1),
(128, 'Clotrimazole', 'Clotrimazole', 'Cream', '1%', 1, '2025-08-15 23:27:18', NULL, 0),
(129, 'Clotrimazole', 'Clotrimazole', 'Topical Solution', '1%', 1, '2025-08-15 23:27:18', NULL, 0),
(130, 'Mupirocin', 'Mupirocin', 'Ointment', '2%', 0, '2025-08-15 23:27:18', NULL, 1),
(131, 'Benzoyl Peroxide', 'Benzoyl Peroxide', 'Gel', '5%', 1, '2025-08-15 23:27:18', NULL, 0),
(132, 'Carbamazepine', 'Carbamazepine', 'Tablet', '200 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(133, 'Carbamazepine', 'Carbamazepine', 'Extended Release Tablet', '400 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(134, 'Valproic Acid', 'Sodium Valproate', 'Tablet', '200 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(135, 'Valproic Acid', 'Sodium Valproate', 'Oral Solution', '200 mg/5 mL', 0, '2025-08-15 23:27:18', NULL, 1),
(136, 'Gabapentin', 'Gabapentin', 'Capsule', '300 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(137, 'Levetiracetam', 'Levetiracetam', 'Tablet', '500 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(138, 'Diclofenac', 'Diclofenac Sodium', 'Tablet', '50 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(139, 'Diclofenac', 'Diclofenac Sodium', 'Topical Gel', '1%', 1, '2025-08-15 23:27:18', NULL, 0),
(140, 'Calcium Carbonate', 'Calcium Carbonate', 'Tablet', '500 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(141, 'Glucosamine', 'Glucosamine Sulfate', 'Capsule', '500 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(142, 'Sertraline', 'Sertraline Hydrochloride', 'Tablet', '50 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(143, 'Sertraline', 'Sertraline Hydrochloride', 'Tablet', '100 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(144, 'Fluoxetine', 'Fluoxetine Hydrochloride', 'Capsule', '20 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(145, 'Diazepam', 'Diazepam', 'Tablet', '5 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(146, 'Diazepam', 'Diazepam', 'Tablet', '10 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(147, 'Ethinylestradiol + Levonorgestrel', 'Ethinylestradiol/Levonorgestrel', 'Tablet', '30 mcg/150 mcg', 0, '2025-08-15 23:27:18', NULL, 1),
(148, 'Ferrous Sulfate', 'Ferrous Sulfate', 'Tablet', '325 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(149, 'Folic Acid', 'Folic Acid', 'Tablet', '5 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(150, 'Clomiphene', 'Clomiphene Citrate', 'Tablet', '50 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(151, 'Timolol', 'Timolol Maleate', 'Ophthalmic Solution', '0.25%', 0, '2025-08-15 23:27:18', NULL, 1),
(152, 'Timolol', 'Timolol Maleate', 'Ophthalmic Solution', '0.5%', 0, '2025-08-15 23:27:18', NULL, 1),
(153, 'Latanoprost', 'Latanoprost', 'Ophthalmic Solution', '0.005%', 0, '2025-08-15 23:27:18', NULL, 1),
(154, 'Ciprofloxacin Eye Drops', 'Ciprofloxacin', 'Ophthalmic Solution', '0.3%', 0, '2025-08-15 23:27:18', NULL, 1),
(155, 'Fluticasone Nasal Spray', 'Fluticasone Propionate', 'Nasal Spray', '50 mcg/dose', 1, '2025-08-15 23:27:18', NULL, 0),
(156, 'Oxymetazoline', 'Oxymetazoline Hydrochloride', 'Nasal Spray', '0.05%', 1, '2025-08-15 23:27:18', NULL, 0),
(157, 'Amoxicillin + Clavulanic Acid', 'Amoxicillin/Clavulanate', 'Tablet', '500 mg/125 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(158, 'Chlorhexidine Mouthwash', 'Chlorhexidine Gluconate', 'Mouthwash', '0.12%', 1, '2025-08-15 23:27:18', NULL, 0),
(159, 'Amoxicillin', 'Amoxicillin', 'Capsule', '500 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(160, 'Ibuprofen', 'Ibuprofen', 'Tablet', '400 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(161, 'Omeprazole', 'Omeprazole', 'Capsule', '20 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(162, 'Omeprazole', 'Omeprazole', 'Capsule', '40 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(163, 'Esomeprazole', 'Esomeprazole Magnesium', 'Tablet', '40 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(164, 'Loperamide', 'Loperamide Hydrochloride', 'Capsule', '2 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(165, 'Tamsulosin', 'Tamsulosin Hydrochloride', 'Capsule', '0.4 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(166, 'Finasteride', 'Finasteride', 'Tablet', '5 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(167, 'Nitrofurantoin', 'Nitrofurantoin', 'Capsule', '100 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(168, 'Methotrexate', 'Methotrexate', 'Tablet', '2.5 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(169, 'Methotrexate', 'Methotrexate', 'Injection', '50 mg/2 mL', 0, '2025-08-15 23:27:18', NULL, 1),
(170, 'Cyclophosphamide', 'Cyclophosphamide', 'Injection', '500 mg vial', 0, '2025-08-15 23:27:18', NULL, 1),
(171, 'Salbutamol', 'Albuterol Sulfate', 'Inhaler', '100 mcg/dose', 0, '2025-08-15 23:27:18', NULL, 1),
(172, 'Salbutamol', 'Albuterol Sulfate', 'Nebulizer Solution', '2.5 mg/2.5 mL', 0, '2025-08-15 23:27:18', NULL, 1),
(173, 'Budesonide', 'Budesonide', 'Inhaler', '200 mcg/dose', 0, '2025-08-15 23:27:18', NULL, 1),
(174, 'Furosemide', 'Furosemide', 'Tablet', '40 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(175, 'Furosemide', 'Furosemide', 'Injection', '20 mg/2 mL', 0, '2025-08-15 23:27:18', NULL, 1),
(176, 'Erythropoietin', 'Epoetin Alfa', 'Injection', '4000 IU/mL', 0, '2025-08-15 23:27:18', NULL, 1),
(177, 'Insulin Regular', 'Insulin', 'Injection', '100 IU/mL', 0, '2025-08-15 23:27:18', NULL, 1),
(178, 'Insulin NPH', 'Insulin Isophane', 'Injection', '100 IU/mL', 0, '2025-08-15 23:27:18', NULL, 1),
(179, 'Levothyroxine', 'Levothyroxine Sodium', 'Tablet', '50 mcg', 0, '2025-08-15 23:27:18', NULL, 1),
(180, 'Levothyroxine', 'Levothyroxine Sodium', 'Tablet', '100 mcg', 0, '2025-08-15 23:27:18', NULL, 1),
(181, 'Methotrexate', 'Methotrexate', 'Tablet', '2.5 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(182, 'Sulfasalazine', 'Sulfasalazine', 'Tablet', '500 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(183, 'Hydroxychloroquine', 'Hydroxychloroquine Sulfate', 'Tablet', '200 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(184, 'Loratadine', 'Loratadine', 'Tablet', '10 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(185, 'Cetirizine', 'Cetirizine Hydrochloride', 'Tablet', '10 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(186, 'Montelukast', 'Montelukast Sodium', 'Tablet', '10 mg', 0, '2025-08-15 23:27:18', NULL, 1),
(187, 'Ferrous Sulfate', 'Ferrous Sulfate', 'Tablet', '325 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(188, 'Folic Acid', 'Folic Acid', 'Tablet', '5 mg', 1, '2025-08-15 23:27:18', NULL, 0),
(189, 'Warfarin', 'Warfarin Sodium', 'Tablet', '5 mg', 0, '2025-08-15 23:27:18', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `medicine_specializations`
--

CREATE TABLE `medicine_specializations` (
  `medicine_id` int(11) NOT NULL,
  `specialization_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_specializations`
--

INSERT INTO `medicine_specializations` (`medicine_id`, `specialization_id`) VALUES
(3, 1),
(3, 10),
(3, 11),
(4, 1),
(4, 5),
(5, 1),
(5, 6),
(5, 11),
(6, 1),
(6, 10),
(7, 1),
(7, 17),
(8, 3),
(9, 1),
(9, 12),
(10, 3),
(10, 16),
(11, 17),
(12, 1),
(12, 2),
(12, 19),
(13, 3),
(14, 1),
(14, 10),
(14, 13),
(15, 3),
(15, 16),
(16, 3),
(16, 20),
(17, 15),
(17, 19),
(18, 18),
(18, 19),
(19, 3),
(19, 20),
(20, 3),
(20, 16),
(21, 3),
(22, 3),
(22, 16),
(23, 17),
(24, 7),
(25, 5),
(25, 7),
(26, 1),
(26, 14),
(27, 1),
(27, 6),
(28, 1),
(28, 11),
(29, 1),
(29, 13),
(29, 18),
(30, 4),
(31, 7),
(32, 7),
(33, 1),
(33, 12),
(34, 15),
(34, 19),
(35, 4),
(36, 1),
(36, 6),
(36, 18),
(37, 18),
(38, 3),
(39, 3),
(39, 5),
(40, 5),
(41, 5),
(42, 5),
(43, 5),
(43, 7),
(44, 5),
(44, 7),
(45, 7),
(46, 7),
(47, 7),
(48, 7),
(49, 7),
(50, 7),
(51, 7),
(52, 1),
(52, 10),
(52, 11),
(53, 1),
(53, 5),
(54, 1),
(54, 6),
(54, 11),
(55, 1),
(55, 10),
(56, 1),
(56, 17),
(57, 3),
(58, 1),
(58, 12),
(59, 3),
(59, 16),
(60, 17),
(61, 1),
(61, 2),
(61, 19),
(62, 3),
(63, 1),
(63, 10),
(63, 13),
(64, 3),
(64, 16),
(65, 3),
(65, 20),
(66, 15),
(66, 19),
(67, 18),
(67, 19),
(68, 3),
(68, 20),
(69, 3),
(69, 16),
(70, 3),
(71, 3),
(71, 16),
(72, 17),
(73, 7),
(74, 5),
(74, 7),
(75, 1),
(75, 14),
(76, 1),
(76, 6),
(77, 1),
(77, 11),
(78, 1),
(78, 4),
(79, 4),
(80, 7),
(81, 7),
(82, 1),
(82, 12),
(83, 15),
(83, 19),
(84, 4),
(85, 1),
(85, 6),
(85, 18),
(86, 18),
(87, 3),
(88, 3),
(88, 5),
(89, 5),
(90, 5),
(91, 5),
(92, 5),
(92, 7),
(93, 5),
(93, 7),
(94, 7),
(95, 7),
(96, 7),
(97, 7),
(98, 7),
(99, 7),
(100, 7),
(102, 7),
(103, 1),
(104, 1),
(105, 1),
(106, 1),
(106, 2),
(107, 1),
(108, 1),
(109, 1),
(110, 1),
(111, 1),
(112, 1),
(113, 2),
(114, 2),
(115, 1),
(115, 2),
(116, 2),
(117, 1),
(117, 2),
(117, 12),
(118, 3),
(119, 3),
(120, 3),
(121, 3),
(122, 3),
(123, 3),
(124, 3),
(125, 3),
(126, 4),
(127, 4),
(128, 4),
(129, 4),
(130, 4),
(131, 4),
(132, 5),
(133, 5),
(134, 5),
(135, 5),
(136, 5),
(137, 5),
(138, 6),
(139, 6),
(140, 6),
(141, 6),
(142, 7),
(143, 7),
(144, 7),
(145, 5),
(146, 5),
(147, 8),
(148, 8),
(148, 20),
(149, 8),
(149, 20),
(150, 8),
(151, 9),
(152, 9),
(153, 9),
(154, 9),
(155, 10),
(155, 15),
(155, 19),
(156, 10),
(156, 19),
(157, 10),
(158, 11),
(159, 10),
(159, 11),
(160, 1),
(160, 6),
(160, 11),
(161, 12),
(162, 12),
(163, 12),
(164, 1),
(164, 12),
(165, 13),
(166, 13),
(167, 13),
(168, 14),
(168, 18),
(169, 14),
(170, 14),
(171, 15),
(172, 15),
(173, 15),
(174, 3),
(174, 16),
(175, 16),
(176, 16),
(176, 20),
(177, 17),
(178, 17),
(179, 17),
(180, 17),
(181, 18),
(182, 18),
(183, 18),
(184, 19),
(185, 1),
(185, 2),
(185, 19),
(186, 15),
(186, 19),
(187, 8),
(187, 20),
(188, 8),
(188, 20),
(189, 3),
(189, 20);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `seen` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescribed_medicines`
--

CREATE TABLE `prescribed_medicines` (
  `id` int(11) NOT NULL,
  `report_id` int(11) DEFAULT NULL,
  `medicine_id` int(11) DEFAULT NULL,
  `dosage_form` varchar(120) DEFAULT NULL,
  `strength` varchar(120) DEFAULT NULL,
  `pills_per_day` int(11) DEFAULT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `used_status` enum('ISSUED','USED') NOT NULL DEFAULT 'ISSUED',
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescribed_medicines`
--

INSERT INTO `prescribed_medicines` (`id`, `report_id`, `medicine_id`, `dosage_form`, `strength`, `pills_per_day`, `duration_days`, `doctor_id`, `patient_id`, `used_status`, `used_at`) VALUES
(76, 61, 166, NULL, NULL, 2, 7, 57, 29, 'USED', '2025-08-18 00:44:11');

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `rating` decimal(3,2) NOT NULL,
  `comment` text DEFAULT NULL,
  `rated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `wait_time` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `service` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `communication` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `facilities` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `appointment_id`, `doctor_id`, `patient_id`, `rating`, `comment`, `rated_at`, `wait_time`, `service`, `communication`, `facilities`) VALUES
(36, NULL, 45, 29, 3.50, '55', '2025-08-15 11:20:54', 4, 3, 4, 3);

-- --------------------------------------------------------

--
-- Table structure for table `slots`
--

CREATE TABLE `slots` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `slots`
--

INSERT INTO `slots` (`id`, `doctor_id`, `date`, `time`) VALUES
(186, 45, '2025-08-14', '19:45:00'),
(193, 45, '2025-08-27', '10:27:00'),
(194, 45, '2025-08-27', '10:57:00'),
(195, 45, '2025-08-27', '11:27:00'),
(196, 45, '2025-08-27', '11:57:00'),
(197, 45, '2025-08-27', '12:27:00'),
(198, 45, '2025-08-27', '12:57:00'),
(199, 45, '2025-08-27', '13:27:00'),
(200, 45, '2025-08-27', '13:57:00'),
(201, 45, '2025-08-27', '14:27:00'),
(202, 45, '2025-08-27', '14:57:00'),
(203, 45, '2025-08-27', '15:27:00'),
(204, 45, '2025-08-27', '15:57:00'),
(205, 45, '2025-08-27', '16:27:00'),
(206, 45, '2025-08-27', '16:57:00'),
(207, 45, '2025-08-27', '17:27:00'),
(208, 45, '2025-08-27', '17:57:00'),
(209, 45, '2025-08-27', '18:27:00'),
(210, 45, '2025-08-27', '18:57:00'),
(211, 45, '2025-08-27', '19:27:00'),
(212, 45, '2025-08-27', '19:57:00'),
(213, 45, '2025-08-27', '20:27:00'),
(214, 45, '2025-08-27', '20:57:00'),
(215, 45, '2025-08-27', '21:27:00'),
(216, 45, '2025-08-27', '21:57:00'),
(217, 45, '2025-08-29', '10:00:00'),
(218, 45, '2025-08-29', '10:30:00'),
(219, 45, '2025-08-29', '11:00:00'),
(220, 45, '2025-08-29', '11:30:00'),
(221, 45, '2025-08-29', '12:00:00'),
(222, 45, '2025-08-29', '12:30:00'),
(223, 45, '2025-08-29', '13:00:00'),
(224, 45, '2025-08-29', '13:30:00'),
(225, 45, '2025-08-29', '14:00:00'),
(226, 45, '2025-08-29', '14:30:00'),
(227, 45, '2025-08-29', '15:00:00'),
(228, 45, '2025-08-29', '15:30:00'),
(229, 45, '2025-08-29', '16:00:00'),
(230, 45, '2025-08-29', '16:30:00'),
(231, 45, '2025-08-29', '17:00:00'),
(232, 45, '2025-08-29', '17:30:00'),
(233, 45, '2025-08-29', '18:00:00'),
(234, 45, '2025-08-29', '18:30:00'),
(235, 45, '2025-08-29', '19:00:00'),
(236, 45, '2025-08-29', '19:30:00'),
(237, 45, '2025-08-29', '20:00:00'),
(238, 45, '2025-08-29', '20:30:00'),
(239, 45, '2025-08-29', '21:00:00'),
(240, 45, '2025-08-29', '21:30:00'),
(241, 45, '2025-08-26', '10:05:00'),
(251, 45, '2025-08-26', '15:05:00'),
(252, 45, '2025-08-26', '15:35:00'),
(253, 45, '2025-08-26', '16:05:00'),
(254, 45, '2025-08-26', '16:35:00'),
(255, 45, '2025-08-26', '17:05:00'),
(256, 45, '2025-08-26', '17:35:00'),
(257, 45, '2025-08-26', '18:05:00'),
(258, 45, '2025-08-26', '18:35:00'),
(259, 45, '2025-08-26', '19:05:00'),
(260, 45, '2025-08-26', '19:35:00'),
(261, 45, '2025-08-26', '20:05:00'),
(262, 45, '2025-08-26', '20:35:00'),
(263, 45, '2025-08-26', '21:05:00'),
(324, 45, '2025-09-01', '10:00:00'),
(325, 45, '2025-09-01', '10:30:00'),
(326, 45, '2025-09-01', '11:00:00'),
(327, 45, '2025-09-01', '11:30:00'),
(328, 45, '2025-09-01', '12:00:00'),
(329, 45, '2025-09-01', '12:30:00'),
(330, 45, '2025-09-01', '13:00:00'),
(331, 45, '2025-09-01', '13:30:00'),
(332, 45, '2025-09-01', '14:00:00'),
(333, 45, '2025-09-01', '14:30:00'),
(334, 45, '2025-09-01', '15:00:00'),
(335, 45, '2025-09-01', '15:30:00'),
(336, 45, '2025-09-01', '16:00:00'),
(337, 45, '2025-09-01', '16:30:00'),
(338, 45, '2025-09-01', '17:00:00'),
(339, 45, '2025-09-01', '17:30:00'),
(340, 45, '2025-09-01', '18:00:00'),
(341, 45, '2025-09-01', '18:30:00'),
(342, 45, '2025-09-01', '19:00:00'),
(343, 45, '2025-09-01', '19:30:00'),
(344, 45, '2025-09-01', '20:00:00'),
(345, 45, '2025-09-01', '20:30:00'),
(416, 57, '2025-08-17', '17:30:00'),
(417, 57, '2025-08-17', '18:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `specializations`
--

CREATE TABLE `specializations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `specializations`
--

INSERT INTO `specializations` (`id`, `name`) VALUES
(19, 'Allergy and Immunology'),
(3, 'Cardiology'),
(11, 'Dentistry'),
(4, 'Dermatology'),
(17, 'Endocrinology'),
(10, 'ENT (Ear, Nose, Throat)'),
(12, 'Gastroenterology'),
(1, 'General Medicine'),
(8, 'Gynecology'),
(20, 'Hematology'),
(16, 'Nephrology'),
(5, 'Neurology'),
(14, 'Oncology'),
(9, 'Ophthalmology'),
(6, 'Orthopedics'),
(2, 'Pediatrics'),
(7, 'Psychiatry'),
(15, 'Pulmonology'),
(18, 'Rheumatology'),
(13, 'Urology');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verify_token` varchar(255) DEFAULT NULL,
  `verify_expires` datetime DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `temp_password` tinyint(1) DEFAULT 0,
  `role` enum('patient','doctor','admin') DEFAULT 'patient',
  `gender` enum('male','female','other') DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `specialization_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `user_id` int(10) NOT NULL,
  `block_reason` text DEFAULT NULL,
  `blocked_at` datetime DEFAULT NULL,
  `is_blocked` tinyint(1) DEFAULT 0,
  `user_deleted` tinyint(1) DEFAULT 0,
  `delete_reason` text DEFAULT NULL,
  `blocked_by` int(11) DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_reason` varchar(255) DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `is_activated` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `email_verified`, `verify_token`, `verify_expires`, `profile_image`, `phone`, `location`, `password`, `temp_password`, `role`, `gender`, `height_cm`, `weight_kg`, `bmi`, `date_of_birth`, `license_number`, `specialization_id`, `is_active`, `created_at`, `reset_token`, `token_expiry`, `user_id`, `block_reason`, `blocked_at`, `is_blocked`, `user_deleted`, `delete_reason`, `blocked_by`, `deleted_by`, `deleted_reason`, `verification_code`, `is_activated`) VALUES
(7, 'Admin', 'admin', 'admin@mediverse.com', 1, NULL, NULL, NULL, NULL, NULL, '$2y$10$6Jqix8rV38tp1eHFc8Rw1empNTv6lkX8V621L7tTNbnTJcQ9fFeMO', 0, 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-08-06 17:06:04', NULL, NULL, 123123123, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(10, 'sadmin', 'sadmin', 'sadmin@gmail.com', 1, NULL, NULL, NULL, '0526115151', 'qgr', '$2y$10$4tbVoCpp20g.quqKCzsDZuKbKtG.1i.yxFyHjoGuAdtzBTwKRK6BG', 0, 'admin', 'male', 123.00, 265.00, 175.16, '2001-11-24', NULL, NULL, 1, '2025-08-06 21:01:28', NULL, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(29, 'patient5', 'patient5', 'carcraze420@gmail.com', 1, NULL, NULL, 'uploads/avatars/u29_1755453414.png', '0526122697', 'Haifa', '$2y$10$LP3gKYhnkPAiCrNZHR9uL.Ci1aKf37BQQOAvSVoixl8a7YuU/jpli', 0, 'patient', 'male', 180.00, 90.00, 27.78, '2001-08-25', NULL, NULL, 1, '2025-08-10 18:39:00', NULL, NULL, 147258369, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(45, 'Wasim Shalah', 'waseem', 'waseemshalah@gmail.com', 0, NULL, NULL, NULL, '0509861813', 'שפרעם', '$2y$10$omAz8VfuUUHXcjuOi0yr3.Pa7kf7HsMVp1vLBbStSr5b3eq20ZQr.', 0, 'doctor', 'male', NULL, NULL, NULL, '2000-01-08', '7-123321', 7, 1, '2025-08-14 15:33:18', '58331e8090d55460dbf6e4272fc2f4ae', '2025-08-17 21:59:34', 123321123, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(57, 'aybk abo alheja', 'aybk', 'aybk.1999@gmail.com', 0, NULL, NULL, NULL, '0511111111', 'tamra', '$2y$10$M5Py.UtT56ETQAH9rAqHdO19Wtg5BghOIZmd4klnfmVyKpdM6Au0W', 0, 'doctor', 'male', NULL, NULL, NULL, '1999-05-09', '13-555555', 13, 1, '2025-08-17 14:06:09', NULL, NULL, 208135020, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 1),
(62, 'fares karam', 'patient1', 'waseemshalah1@gmail.com', 1, NULL, NULL, NULL, '0565489498', 'Tel Aviv, Israel', '$2y$10$5ecguT9i.aKS2KF/GIHTduG4Zyb1qg88G1i9xt5Y7bQMnmBujKzV6', 0, 'patient', 'male', 180.00, 85.00, 26.23, '2010-08-18', NULL, NULL, 1, '2025-08-18 18:15:16', NULL, NULL, 654984984, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `work_hours`
--

CREATE TABLE `work_hours` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `work_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `doctor_ratings_summary`
--
DROP TABLE IF EXISTS `doctor_ratings_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `doctor_ratings_summary`  AS SELECT `r`.`doctor_id` AS `doctor_id`, round(avg(`r`.`rating`),2) AS `avg_rating`, count(0) AS `ratings_count`, round(avg(`r`.`wait_time`),2) AS `avg_wait_time`, round(avg(`r`.`service`),2) AS `avg_service`, round(avg(`r`.`communication`),2) AS `avg_communication`, round(avg(`r`.`facilities`),2) AS `avg_facilities` FROM `ratings` AS `r` GROUP BY `r`.`doctor_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_patient` (`patient_id`),
  ADD KEY `fk_doctor` (`doctor_id`),
  ADD KEY `fk_slot` (`slot_id`),
  ADD KEY `idx_appt_doc_dt` (`doctor_id`,`appointment_datetime`);

--
-- Indexes for table `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `doctor_applications`
--
ALTER TABLE `doctor_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `specialization_id` (`specialization_id`);

--
-- Indexes for table `medical_reports`
--
ALTER TABLE `medical_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `fk_medical_reports_appointment` (`appointment_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medicine_specializations`
--
ALTER TABLE `medicine_specializations`
  ADD PRIMARY KEY (`medicine_id`,`specialization_id`),
  ADD KEY `specialization_id` (`specialization_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_id` (`chat_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_msg_chat_time` (`chat_id`,`sent_at`);

--
-- Indexes for table `prescribed_medicines`
--
ALTER TABLE `prescribed_medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `fk_prescribed_doctor` (`doctor_id`),
  ADD KEY `fk_prescribed_patient` (`patient_id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_id` (`appointment_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `idx_ratings_doctor` (`doctor_id`),
  ADD KEY `idx_ratings_appt` (`appointment_id`),
  ADD KEY `idx_rate_doc` (`doctor_id`);

--
-- Indexes for table `slots`
--
ALTER TABLE `slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `specializations`
--
ALTER TABLE `specializations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `specialization_id` (`specialization_id`),
  ADD KEY `id` (`id`);

--
-- Indexes for table `work_hours`
--
ALTER TABLE `work_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_workhours_user` (`doctor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `chats`
--
ALTER TABLE `chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `doctor_applications`
--
ALTER TABLE `doctor_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `medical_reports`
--
ALTER TABLE `medical_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `prescribed_medicines`
--
ALTER TABLE `prescribed_medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `slots`
--
ALTER TABLE `slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=429;

--
-- AUTO_INCREMENT for table `specializations`
--
ALTER TABLE `specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `work_hours`
--
ALTER TABLE `work_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_slot` FOREIGN KEY (`slot_id`) REFERENCES `slots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chats_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctor_applications`
--
ALTER TABLE `doctor_applications`
  ADD CONSTRAINT `doctor_applications_ibfk_1` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`);

--
-- Constraints for table `medical_reports`
--
ALTER TABLE `medical_reports`
  ADD CONSTRAINT `fk_medical_reports_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_medical_reports_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_medical_reports_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rep_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rep_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rep_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `medical_reports_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`);

--
-- Constraints for table `medicine_specializations`
--
ALTER TABLE `medicine_specializations`
  ADD CONSTRAINT `medicine_specializations_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_specializations_ibfk_2` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescribed_medicines`
--
ALTER TABLE `prescribed_medicines`
  ADD CONSTRAINT `fk_pm_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pm_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pm_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pm_report` FOREIGN KEY (`report_id`) REFERENCES `medical_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prescribed_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prescribed_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescribed_medicines_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `medical_reports` (`id`),
  ADD CONSTRAINT `prescribed_medicines_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`);

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `fk_rate_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rate_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rate_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ratings_doctor_fk` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `ratings_patient_fk` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `slots`
--
ALTER TABLE `slots`
  ADD CONSTRAINT `slots_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`);

--
-- Constraints for table `work_hours`
--
ALTER TABLE `work_hours`
  ADD CONSTRAINT `fk_workhours_user` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
