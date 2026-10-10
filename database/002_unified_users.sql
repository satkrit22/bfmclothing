USE `bfm_clothing`;
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('customer','staff','admin','super_admin') NOT NULL DEFAULT 'customer' AFTER password_hash;
INSERT INTO users (first_name,last_name,email,password_hash,role,is_active)
SELECT SUBSTRING_INDEX(name,' ',1), TRIM(SUBSTRING(name, LENGTH(SUBSTRING_INDEX(name,' ',1))+1)), email, password_hash, CASE WHEN role='super_admin' THEN 'super_admin' ELSE 'admin' END, is_active FROM admins a
WHERE NOT EXISTS (SELECT 1 FROM users u WHERE LOWER(u.email)=LOWER(a.email));
