-- Link training needs to performance (reviews/cycles).
-- Run this in phpMyAdmin on database hr_system once.
-- If you get "Duplicate column name", the columns already exist; skip this.

ALTER TABLE `training_needs_assessment`
  ADD COLUMN `review_id` int(11) DEFAULT NULL AFTER `status`,
  ADD COLUMN `cycle_id` int(11) DEFAULT NULL AFTER `review_id`,
  ADD KEY `review_id` (`review_id`),
  ADD KEY `cycle_id` (`cycle_id`),
  ADD CONSTRAINT `training_needs_assessment_ibfk_review` FOREIGN KEY (`review_id`) REFERENCES `performance_reviews` (`review_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `training_needs_assessment_ibfk_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `performance_review_cycles` (`cycle_id`) ON DELETE SET NULL;
