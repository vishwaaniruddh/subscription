CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255),
    contact_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_project_client (client_id),
    INDEX idx_project_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    service_type ENUM('web', 'mobile', 'other') NOT NULL,
    user_limit INT NOT NULL CHECK (user_limit > 0),
    active_user_count INT DEFAULT 0 CHECK (active_user_count >= 0),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (end_date >= start_date),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_service_project (project_id),
    INDEX idx_service_dates (start_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    user_identifier VARCHAR(255) NOT NULL,
    status ENUM('active', 'deactivated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deactivated_at TIMESTAMP NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_user_service (service_id),
    INDEX idx_user_identifier (user_identifier)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscription_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_history_service (service_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL COMMENT 'client, project, service, user, subscription',
    entity_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'created, updated, deleted, renewed, extended, user_registered, user_deactivated',
    description TEXT NOT NULL,
    old_data JSON DEFAULT NULL,
    new_data JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_entity (entity_type, entity_id),
    INDEX idx_activity_action (action),
    INDEX idx_activity_created (created_at)
) ENGINE=InnoDB;
