-- Run after bfm_clothing.sql on an existing installation.
ALTER TABLE users ADD COLUMN username VARCHAR(80) NULL AFTER email, ADD COLUMN role ENUM('customer','staff','admin','super_admin') NOT NULL DEFAULT 'customer' AFTER password_hash, ADD COLUMN status ENUM('active','disabled') NOT NULL DEFAULT 'active' AFTER role;
ALTER TABLE users ADD UNIQUE KEY uq_users_username (username);
CREATE TABLE IF NOT EXISTS roles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(40) NOT NULL UNIQUE,description VARCHAR(255) NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS permissions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80) NOT NULL UNIQUE,description VARCHAR(255) NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS user_roles (user_id INT UNSIGNED NOT NULL,role_id INT UNSIGNED NOT NULL,PRIMARY KEY(user_id,role_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS role_permissions (role_id INT UNSIGNED NOT NULL,permission_id INT UNSIGNED NOT NULL,PRIMARY KEY(role_id,permission_id),FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE,FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NULL,action VARCHAR(100) NOT NULL,entity_type VARCHAR(60) NULL,entity_id INT UNSIGNED NULL,details JSON NULL,ip_address VARCHAR(45) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB;
INSERT IGNORE INTO roles(name,description) VALUES ('customer','Shopping and account access'),('staff','Order fulfillment access'),('admin','Store management access'),('super_admin','All access');
UPDATE users SET role='customer' WHERE role IS NULL OR role='';
-- Legacy admins can be migrated manually into users with password_hash preserved:
-- INSERT INTO users(first_name,last_name,email,password_hash,role) SELECT name,'',email,password_hash,IF(role='super_admin','super_admin','admin') FROM admins;
