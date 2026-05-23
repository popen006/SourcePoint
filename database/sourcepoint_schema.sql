-- =============================================
-- SourcePoint Database Schema
-- CamNorte Event Aggregator
-- =============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS sourcepoint_db;
USE sourcepoint_db;

-- =============================================
-- 1. USERS TABLE (Registered Residents)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    mobile VARCHAR(15) NOT NULL,
    role ENUM('resident','admin') NOT NULL DEFAULT 'resident',
    organization VARCHAR(150),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 2. ADMINS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 3. CATEGORIES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default categories (ignore if they already exist)
INSERT IGNORE INTO categories (name, description) VALUES
('Health', 'Health programs, medical missions, and wellness activities'),
('Training', 'Skills training and capacity building workshops'),
('Seminar', 'Educational seminars and conferences'),
('Outreach', 'Community outreach and extension programs'),
('Environment', 'Environmental protection and cleanup activities'),
('Education', 'Scholarship programs and educational support'),
('Livelihood', 'Livelihood programs and economic development');

-- =============================================
-- 4. EVENTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category_id INT,
    location VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    target_participants INT DEFAULT 0,
    organizer VARCHAR(150) NOT NULL,
    partner_organizations TEXT,
    cover_image VARCHAR(255),
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 5. SUBMISSIONS TABLE (Organization Event Proposals)
-- =============================================
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    contact_email VARCHAR(100) NOT NULL,
    contact_mobile VARCHAR(15) NULL DEFAULT NULL,
    event_title VARCHAR(200) NOT NULL,
    event_description TEXT NOT NULL,
    event_category VARCHAR(50),
    event_location VARCHAR(255),
    event_date DATE,
    event_organizer VARCHAR(150),
    partner_organizations TEXT,
    cover_image VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 6. EVENT REGISTRATIONS TABLE (Who joined which event)
-- =============================================
CREATE TABLE IF NOT EXISTS event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_registration (event_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 7. CONTACT MESSAGES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 8. OTP CODES TABLE (Password Reset Verification)
-- =============================================
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 9. SMS NOTIFICATIONS LOG TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS sms_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    mobile VARCHAR(15) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- INSERT DEFAULT ADMIN ACCOUNT into users table (ignore if already exists)
-- Password: admin123 (will be hashed by PHP)
-- =============================================
INSERT IGNORE INTO users (fullname, username, email, mobile, role, password)
VALUES ('System Administrator', 'admin', 'admin@sourcepoint.com', '09123456789', 'admin', '$2y$10$NaD2uA0UxJHnfUxuXve7oO5m31GbsokWurKqJAvxKWjIRLkjoMJNi');

-- =============================================
-- INSERT SAMPLE EVENTS (for testing, ignore if they already exist)
-- =============================================
INSERT IGNORE INTO events (title, description, category_id, location, event_date, target_participants, organizer, partner_organizations, status) VALUES
('Medical Mission 2026', 'Free medical checkup, dental services, and medicine distribution for all residents.', 1, 'Daet, Camarines Norte', '2026-06-15', 500, 'LGU Daet', 'Red Cross, CamNorte Province', 'upcoming'),
('Coastal Cleanup Drive', 'Community coastal cleanup and mangrove planting along the Daet coastline.', 5, 'Barangay Bagasbas, Daet', '2026-06-20', 200, 'DENR CamNorte', 'LGU Daet, Coast Guard, CSC', 'upcoming'),
('Skills Training: Digital Literacy', 'Free basic computer and internet skills training for senior citizens and out-of-school youth.', 2, 'CamNorte State College, Daet', '2026-07-05', 100, 'CamNorte State College', 'DTI, DOST, LGU Daet', 'upcoming'),
('Provincial Health Summit', 'Annual health summit discussing community health programs and disease prevention.', 3, 'Capitol Building, Daet', '2026-07-12', 300, 'Provincial Health Office', 'WHO, Local Hospitals', 'upcoming'),
('Tree Planting Activity', 'Reforestation activity in Barangay Awitan, Daet.', 5, 'Barangay Awitan, Daet', '2026-07-18', 150, 'LGU Daet Environment Office', 'DENR, Boy Scouts, Local Schools', 'upcoming');
