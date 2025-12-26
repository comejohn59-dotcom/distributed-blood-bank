-- create_tables_bloodbank.sql
-- Creates central `users`, `blood_banks`, and `blood_inventory` tables
-- Run against the `blood` database. Example:
-- mysql -u root -p -P 3306 blood < create_tables_bloodbank.sql

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','blood_bank','hospital','donor') NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blood_banks (
  blood_bank_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(512) DEFAULT NULL,
  city VARCHAR(255) DEFAULT NULL,
  contact_number VARCHAR(50) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blood_inventory (
  inventory_id INT AUTO_INCREMENT PRIMARY KEY,
  blood_bank_id INT NOT NULL,
  blood_group VARCHAR(5) NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  expiry_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (blood_bank_id) REFERENCES blood_banks(blood_bank_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: sample insert for testing (UNCOMMENT and adjust values to use)
-- 1) Generate a password hash on your machine:
--    php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT) . PHP_EOL;"
-- 2) Replace <HASH> below with the generated string and run the two INSERTs.
-- INSERT INTO users (name,email,password,role,status)
-- VALUES ('Test Bank','bank@example.com','<HASH>','blood_bank','active');
-- INSERT INTO blood_banks (user_id,name,city,contact_number)
-- VALUES (LAST_INSERT_ID(), 'Test Bank','YourCity','0123456789');
