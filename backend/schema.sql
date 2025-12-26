
-- Schema for blood
-- Run: mysql -u root -p < backend/schema.sql

CREATE DATABASE IF NOT EXISTS `blood` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `blood`;

-- Users
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','blood_bank','hospital','donor') NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  blood_bank_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Donors
DROP TABLE IF EXISTS donors;
CREATE TABLE donors (
  donor_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  blood_group VARCHAR(5),
  gender VARCHAR(20),
  date_of_birth DATE,
  weight DECIMAL(5,2) DEFAULT NULL,
  medical_conditions TEXT,
  last_health_check DATE DEFAULT NULL,
  preferred_bank_id INT DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blood banks
DROP TABLE IF EXISTS blood_banks;
CREATE TABLE blood_banks (
  blood_bank_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(512) DEFAULT NULL,
  city VARCHAR(255) DEFAULT NULL,
  contact_number VARCHAR(50) DEFAULT NULL,
  user_id INT DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hospitals (role-specific table)
DROP TABLE IF EXISTS hospitals;
CREATE TABLE hospitals (
  hospital_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  full_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  password VARCHAR(255) DEFAULT NULL,
  hospital_name VARCHAR(255) DEFAULT NULL,
  hospital_license VARCHAR(255) DEFAULT NULL,
  hospital_address VARCHAR(512) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hospital users (individual persons who register under a hospital)
DROP TABLE IF EXISTS hospital_users;
CREATE TABLE hospital_users (
  hospital_user_id INT AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT DEFAULT NULL,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  hospital_name VARCHAR(255) DEFAULT NULL,
  hospital_license VARCHAR(255) DEFAULT NULL,
  hospital_address VARCHAR(512) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hospital_users_email (email),
  FOREIGN KEY (hospital_id) REFERENCES hospitals(hospital_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blood inventory
DROP TABLE IF EXISTS blood_inventory;
CREATE TABLE blood_inventory (
  inventory_id INT AUTO_INCREMENT PRIMARY KEY,
  blood_bank_id INT NOT NULL,
  blood_group VARCHAR(5) NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  expiry_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (blood_bank_id) REFERENCES blood_banks(blood_bank_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Donations
DROP TABLE IF EXISTS donations;
CREATE TABLE donations (
  donation_id INT AUTO_INCREMENT PRIMARY KEY,
  donor_id INT NOT NULL,
  blood_bank_id INT NOT NULL,
  donation_date DATETIME NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (donor_id) REFERENCES donors(donor_id) ON DELETE CASCADE,
  FOREIGN KEY (blood_bank_id) REFERENCES blood_banks(blood_bank_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Appointments
DROP TABLE IF EXISTS appointments;
CREATE TABLE appointments (
  appointment_id INT AUTO_INCREMENT PRIMARY KEY,
  donor_id INT NOT NULL,
  blood_bank_id INT NOT NULL,
  appointment_date DATETIME NOT NULL,
  status ENUM('scheduled','confirmed','cancelled','completed') DEFAULT 'scheduled',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (donor_id) REFERENCES donors(donor_id) ON DELETE CASCADE,
  FOREIGN KEY (blood_bank_id) REFERENCES blood_banks(blood_bank_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blood requests
DROP TABLE IF EXISTS blood_requests;
CREATE TABLE blood_requests (
  request_id INT AUTO_INCREMENT PRIMARY KEY,
  requester_id INT NOT NULL,
  requester_type ENUM('hospital','donor','other') DEFAULT 'hospital',
  blood_group VARCHAR(5) NOT NULL,
  units_required INT NOT NULL,
  priority ENUM('low','normal','high','emergency') DEFAULT 'normal',
  status ENUM('pending','approved','rejected','fulfilled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Request routing
DROP TABLE IF EXISTS request_routing;
CREATE TABLE request_routing (
  routing_id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  blood_bank_id INT NOT NULL,
  routing_status ENUM('assigned','accepted','fulfilled','cancelled') DEFAULT 'assigned',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES blood_requests(request_id) ON DELETE CASCADE,
  FOREIGN KEY (blood_bank_id) REFERENCES blood_banks(blood_bank_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit logs
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
  audit_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  action VARCHAR(100),
  entity_type VARCHAR(100),
  entity_id INT DEFAULT NULL,
  details TEXT,
  ip_address VARCHAR(50),
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(50) DEFAULT 'info',
  related_id INT DEFAULT NULL,
  related_type VARCHAR(100) DEFAULT NULL,
  status ENUM('unread','read') DEFAULT 'unread',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simple seed: admin user (password is 'admin123' hashed using PHP's password_hash)
-- NOTE: run the following in PHP or replace with your own hashed password.
-- INSERT INTO users (name, email, password, role, status) VALUES ('Administrator','admin@example.com','$2y$10$REPLACE_WITH_HASH', 'admin', 'active');

-- Indexes
ALTER TABLE users ADD INDEX idx_role (role);
ALTER TABLE donors ADD INDEX idx_blood_group (blood_group);
ALTER TABLE blood_inventory ADD INDEX idx_bank_group (blood_bank_id, blood_group);

COMMIT;
