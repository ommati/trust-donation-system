-- Database schema for Trust Donation Management System

CREATE DATABASE IF NOT EXISTS trust_donation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trust_donation;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO users (username, password, fullname) VALUES
('admin', '$2y$10$nvbAOEAnVaLrW9QNnjYx.OHrrBKaGLMJk5NKeKOLrEgo5tD1obo92', 'Administrator');

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
