-- In-house vs External training (simple approach)
-- Adds delivery_type, provider_name; makes trainer_id nullable for External sessions.

ALTER TABLE training_sessions
  ADD COLUMN delivery_type ENUM('In-house','External') NOT NULL DEFAULT 'In-house' AFTER status,
  ADD COLUMN provider_name VARCHAR(255) NULL AFTER delivery_type;

-- Allow NULL trainer for External sessions (provider delivers)
ALTER TABLE training_sessions
  MODIFY COLUMN trainer_id INT(11) NULL;

-- Backfill: existing rows stay In-house with existing trainer_id
-- (delivery_type default handles it; no data change needed)
