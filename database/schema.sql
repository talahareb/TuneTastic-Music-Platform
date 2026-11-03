-- TuneTastic academic portfolio demo. No payment card or transaction data is stored.
CREATE DATABASE IF NOT EXISTS tunetastic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tunetastic;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS beats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    genre VARCHAR(60) NOT NULL DEFAULT 'Other',
    description VARCHAR(500) NOT NULL DEFAULT '',
    price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    audio_path VARCHAR(100) NOT NULL,
    audio_mime VARCHAR(60) NOT NULL,
    cover_path VARCHAR(100) NULL,
    cover_mime VARCHAR(60) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (owner_id),
    CONSTRAINT fk_beats_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT UNSIGNED NOT NULL,
    beat_id INT UNSIGNED NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_buyer_beat (buyer_id, beat_id),
    CONSTRAINT fk_purchase_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_beat FOREIGN KEY (beat_id) REFERENCES beats(id) ON DELETE CASCADE
) ENGINE=InnoDB;
