-- Database schema for Trust Donation Management System

CREATE DATABASE IF NOT EXISTS trust_donation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trust_donation;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    is_phone_verified TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    login_otp_hash VARCHAR(255) NULL,
    login_otp_expires_at DATETIME NULL,
    login_otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    login_otp_last_sent_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Authorized users login with phone OTP. No default password user is included.

CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(40) NOT NULL UNIQUE,
    donation_date DATE NOT NULL,
    donor_name VARCHAR(150) NOT NULL,
    mobile VARCHAR(30),
    address TEXT,
    amount DECIMAL(10,2) NOT NULL,
    payment_mode ENUM('Cash','UPI','Bank Transfer','Cheque') NOT NULL DEFAULT 'Cash',
    purpose VARCHAR(255),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_donor_name (donor_name),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_donation_date (donation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
