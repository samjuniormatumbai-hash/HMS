


-- Disable foreign key checks for clean recreation
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. USERS & STAFF ACCOUNTS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `role` ENUM('admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech', 'accountant', 'patient') NOT NULL,
  `department` VARCHAR(80) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. PATIENTS MASTER INDEX TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
  `patient_id` INT AUTO_INCREMENT PRIMARY KEY,
  `opd_number` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(120) NOT NULL,
  `dob` DATE NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') DEFAULT 'O+',
  `allergies` TEXT DEFAULT NULL,
  `next_of_kin_name` VARCHAR(120) NOT NULL,
  `next_of_kin_phone` VARCHAR(25) NOT NULL,
  `next_of_kin_relation` VARCHAR(50) NOT NULL,
  `insurance_provider` VARCHAR(100) DEFAULT 'Cash / Self Pay',
  `insurance_policy_no` VARCHAR(60) DEFAULT NULL,
  `current_status` ENUM('Registered', 'Triaged', 'In_Consultation', 'Lab_Pending', 'Pharmacy_Pending', 'Billing_Pending', 'Discharged') DEFAULT 'Registered',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_patient_opd` (`opd_number`),
  INDEX `idx_patient_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. TRIAGE & BIO-SIGNALS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `triage_records`;
CREATE TABLE `triage_records` (
  `triage_id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `recorded_by_user_id` INT NOT NULL,
  `bp_systolic` INT NOT NULL,
  `bp_diastolic` INT NOT NULL,
  `heart_rate` INT NOT NULL,
  `temperature` DECIMAL(4, 1) NOT NULL,
  `respiratory_rate` INT NOT NULL,
  `spo2` INT NOT NULL,
  `weight_kg` DECIMAL(5, 2) NOT NULL,
  `height_cm` DECIMAL(5, 2) NOT NULL,
  `bmi` DECIMAL(4, 1) NOT NULL,
  `bmi_category` VARCHAR(40) NOT NULL,
  `blood_glucose` INT DEFAULT NULL,
  `pain_score` INT DEFAULT NULL,
  `consciousness_level` ENUM('Alert', 'Voice', 'Pain', 'Unresponsive') DEFAULT 'Alert',
  `priority` ENUM('Red', 'Yellow', 'Green') NOT NULL,
  `chief_complaint` TEXT NOT NULL,
  `triage_notes` TEXT DEFAULT NULL,
  `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. CLINICAL CONSULTATIONS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `consultations`;
CREATE TABLE `consultations` (
  `consultation_id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `triage_id` INT DEFAULT NULL,
  `doctor_user_id` INT NOT NULL,
  `symptoms` TEXT NOT NULL,
  `examination_notes` TEXT NOT NULL,
  `diagnosis` TEXT NOT NULL,
  `icd10_code` VARCHAR(30) DEFAULT NULL,
  `treatment_plan` TEXT NOT NULL,
  `clinical_notes` TEXT DEFAULT NULL,
  `consultation_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
  FOREIGN KEY (`triage_id`) REFERENCES `triage_records`(`triage_id`) ON DELETE SET NULL,
  FOREIGN KEY (`doctor_user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. LABORATORY ORDERS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `lab_orders`;
CREATE TABLE `lab_orders` (
  `order_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(40) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `consultation_id` INT DEFAULT NULL,
  `doctor_user_id` INT NOT NULL,
  `technician_user_id` INT DEFAULT NULL,
  `clinical_indication` TEXT NOT NULL,
  `urgency` ENUM('Routine', 'Urgent', 'STAT') DEFAULT 'Routine',
  `fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Pending', 'Sample Collected', 'Analyzing', 'Completed') DEFAULT 'Pending',
  `technician_notes` TEXT DEFAULT NULL,
  `ordered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
  FOREIGN KEY (`consultation_id`) REFERENCES `consultations`(`consultation_id`) ON DELETE SET NULL,
  FOREIGN KEY (`doctor_user_id`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`technician_user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. LABORATORY TEST RESULTS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `lab_results`;
CREATE TABLE `lab_results` (
  `result_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `test_name` VARCHAR(100) NOT NULL,
  `parameter_name` VARCHAR(100) NOT NULL,
  `measured_value` VARCHAR(80) NOT NULL,
  `unit` VARCHAR(30) DEFAULT NULL,
  `reference_range` VARCHAR(60) DEFAULT NULL,
  `flag` ENUM('Normal', 'High', 'Low', 'Critical') DEFAULT 'Normal',
  `tested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `lab_orders`(`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. PHARMACY FORMULARY INVENTORY TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `medicines_inventory`;
CREATE TABLE `medicines_inventory` (
  `medicine_id` INT AUTO_INCREMENT PRIMARY KEY,
  `drug_code` VARCHAR(30) NOT NULL UNIQUE,
  `brand_name` VARCHAR(120) NOT NULL,
  `category` VARCHAR(60) NOT NULL,
  `dosage_form` VARCHAR(40) NOT NULL,
  `strength` VARCHAR(40) NOT NULL,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `reorder_level` INT NOT NULL DEFAULT 50,
  `unit_price` DECIMAL(10, 2) NOT NULL,
  `batch_number` VARCHAR(50) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `manufacturer` VARCHAR(100) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_stock_qty` (`stock_quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. PRESCRIPTIONS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `prescription_id` INT AUTO_INCREMENT PRIMARY KEY,
  `prescription_number` VARCHAR(40) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `consultation_id` INT DEFAULT NULL,
  `doctor_user_id` INT NOT NULL,
  `pharmacist_user_id` INT DEFAULT NULL,
  `status` ENUM('Pending', 'Partially Dispensed', 'Dispensed') DEFAULT 'Pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `dispensed_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
  FOREIGN KEY (`consultation_id`) REFERENCES `consultations`(`consultation_id`) ON DELETE SET NULL,
  FOREIGN KEY (`doctor_user_id`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`pharmacist_user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. BILLING & INVOICES TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `invoice_id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(40) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `tax_amount` DECIMAL(10, 2) DEFAULT 0.00,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `paid_amount` DECIMAL(10, 2) DEFAULT 0.00,
  `balance` DECIMAL(10, 2) NOT NULL,
  `payment_method` ENUM('Cash', 'M-Pesa STK', 'Health Insurance (SHA)', 'Credit Card') DEFAULT 'M-Pesa STK',
  `payment_reference` VARCHAR(80) DEFAULT NULL,
  `payment_status` ENUM('Unpaid', 'Partial', 'Paid') DEFAULT 'Paid',
  `cashier_user_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `settled_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE,
  FOREIGN KEY (`cashier_user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. AUDIT TRAIL LOGS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `log_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `user_role` VARCHAR(50) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(60) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- INITIAL STAFF SEED DATA (Password for all accounts: password)
-- =====================================================================
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `full_name`, `email`, `phone`, `role`, `department`) VALUES
(1, 'admin',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@afyasmart.ac.ke',    '+254712345678', 'admin',      'Hospital Administration'),
(2, 'dr_kamau',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Mercy Kamau, MD',  'dr.kamau@afyasmart.ac.ke',  '+254723456789', 'doctor',     'Outpatient Clinical Services'),
(3, 'nurse_faith',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Faith Wanjiku, BSN',   'nurse.faith@afyasmart.ac.ke','+254734567890', 'nurse',      'Emergency & Triage Station'),
(4, 'pharm_otieno', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kevin Otieno, BPharm', 'pharmacy@afyasmart.ac.ke',    '+254745678901', 'pharmacist', 'Main Hospital Pharmacy'),
(5, 'lab_mutua',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Mutua, MLS',     'lab@afyasmart.ac.ke',         '+254756789012', 'lab_tech',   'Diagnostic Pathology Lab'),
(6, 'finance_njeri', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dennis Njeri, CPA',   'billing@afyasmart.ac.ke',     '+254767890123', 'accountant', 'Billing & Cashier Revenue Desk');

-- =====================================================================
-- PHARMACY INVENTORY INITIAL SEED DATA
-- =====================================================================
INSERT INTO `medicines_inventory` (`medicine_id`, `drug_code`, `brand_name`, `category`, `dosage_form`, `strength`, `stock_quantity`, `reorder_level`, `unit_price`, `batch_number`, `expiry_date`, `manufacturer`) VALUES
(1, 'AL-20-120', 'Artemether 20mg / Lumefantrine 120mg (Coartem)', 'Antimalarial',     'Tablet', '20mg/120mg', 420, 100, 650.00,  'BN-AL-2024-88', '2026-11-30', 'Novartis Pharma AG'),
(2, 'PCM-500',   'Paracetamol Tablets (Panadol Extra)',             'Analgesic',        'Tablet', '500mg',      1850, 400, 15.00,   'BN-PC-99120',   '2027-04-15', 'GlaxoSmithKline (GSK)'),
(3, 'AMX-625',   'Amoxicillin + Clavulanic Acid (Augmentin)',       'Antibiotic',       'Tablet', '625mg',      18,   50,  1250.00, 'BN-AUG-7721',   '2026-08-20', 'GSK Healthcare'),
(4, 'AML-10',    'Amlodipine Besylate',                             'Antihypertensive', 'Tablet', '10mg',       580,  150, 35.00,   'BN-AML-2024-03', '2027-01-30', 'Pfizer Inc.'),
(5, 'MET-500',   'Metformin Hydrochloride (Glucophage)',            'Antidiabetic',     'Tablet', '500mg',      65,   100, 25.00,   'BN-MET-3301',   '2026-09-15', 'Merck Healthcare');

-- =====================================================================
-- INITIAL PATIENTS SEED DATA
-- =====================================================================
INSERT INTO `patients` (`patient_id`, `opd_number`, `full_name`, `dob`, `gender`, `phone`, `blood_group`, `insurance_provider`, `current_status`) VALUES
(19, 'OPD-2025-019', 'Samuel Mwangi',  '1989-05-14', 'Male',   '+254 712 345 678', 'O+', 'Cash', 'Triaged'),
(23, 'OPD-2025-023', 'Matty Jeff',     '1995-10-22', 'Female', '+254 712 998 877', 'A+', 'SHA',  'Discharged'),
(31, 'OPD-2025-031', 'Brian Kiprono',  '2001-03-17', 'Male',   '+254 722 112 233', 'B+', 'SHA',  'Registered'),
(32, 'OPD-2025-032', 'Junior Sam',     '1998-08-09', 'Male',   '+254 794 474 826', 'O+', 'SHA',  'In_Consultation');