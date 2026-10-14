-- NetRescue Central 2.1 reference schema (MySQL)
-- Prefer Laravel migrations for actual installation.

CREATE TABLE devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_uuid CHAR(36) NOT NULL UNIQUE,
  display_name VARCHAR(255) NOT NULL,
  hostname VARCHAR(255) NULL,
  connection_type VARCHAR(24) NOT NULL DEFAULT 'agent',
  is_local TINYINT(1) NOT NULL DEFAULT 0,
  operating_system VARCHAR(255) NULL,
  os_version VARCHAR(255) NULL,
  agent_version VARCHAR(64) NULL,
  last_ip VARCHAR(45) NULL,
  adapter_name VARCHAR(255) NULL,
  gateway_ip VARCHAR(45) NULL,
  dns_servers JSON NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'unknown',
  health_score TINYINT UNSIGNED NULL,
  last_seen_at DATETIME NULL,
  last_health_check_at DATETIME NULL,
  auth_token_hash CHAR(64) NULL UNIQUE,
  registered_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

-- health_checks, incidents and recovery_actions retain the original NetRescue
-- measurement/diagnostic fields and now include nullable device_id foreign keys.

CREATE TABLE enrollment_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  max_uses SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  uses SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE agent_commands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id BIGINT UNSIGNED NOT NULL,
  incident_id BIGINT UNSIGNED NULL,
  recovery_action_id BIGINT UNSIGNED NULL,
  command_code VARCHAR(64) NOT NULL,
  payload JSON NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'queued',
  queued_at DATETIME NOT NULL,
  dispatched_at DATETIME NULL,
  completed_at DATETIME NULL,
  expires_at DATETIME NULL,
  result_output TEXT NULL,
  result_error TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
