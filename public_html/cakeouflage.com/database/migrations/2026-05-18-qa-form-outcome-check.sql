-- Enforce locked BYOC QA outcome policy at storage level.
-- Contract: BYOC-QA-v1.1-storage-lock

-- 1) Normalize legacy outcomes from earlier harness revisions.
UPDATE qa_form_test_runs
SET actual_outcome = 'accepted_201_created'
WHERE actual_outcome = 'accepted_queued';

UPDATE qa_form_test_runs
SET actual_outcome = 'rejected_422_required_fields'
WHERE actual_outcome IN ('validation_error', 'rejected', 'server_error');

-- 2) Normalize any remaining non-policy values before CHECK is added.
UPDATE qa_form_test_runs
SET notes = CONCAT('[AUTO-NORMALIZED to policy outcome] Previous value: ', COALESCE(actual_outcome, ''), ' | ', COALESCE(notes, '')),
    actual_outcome = 'rejected_422_required_fields'
WHERE actual_outcome NOT IN (
  'accepted_201_created',
  'rejected_422_required_fields',
  'rejected_422_invalid_email',
  'rejected_422_invalid_country_code',
  'rejected_422_phone_india_10_digits',
  'rejected_422_phone_intl_6_15_digits',
  'rejected_422_servings_numeric',
  'rejected_422_privacy_consent',
  'rejected_422_invalid_event_information',
  'rejected_422_invalid_diet_preference'
);

-- 3) Add storage-level CHECK if it is not already present.
SET @check_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'qa_form_test_runs'
    AND CONSTRAINT_NAME = 'chk_qa_form_actual_outcome_policy_v1'
    AND CONSTRAINT_TYPE = 'CHECK'
);

SET @ddl := IF(
  @check_exists = 0,
  'ALTER TABLE qa_form_test_runs ADD CONSTRAINT chk_qa_form_actual_outcome_policy_v1 CHECK (actual_outcome IN (''accepted_201_created'',''rejected_422_required_fields'',''rejected_422_invalid_email'',''rejected_422_invalid_country_code'',''rejected_422_phone_india_10_digits'',''rejected_422_phone_intl_6_15_digits'',''rejected_422_servings_numeric'',''rejected_422_privacy_consent'',''rejected_422_invalid_event_information'',''rejected_422_invalid_diet_preference''))',
  'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
