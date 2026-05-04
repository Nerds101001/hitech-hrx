INSERT INTO leave_policy_profiles (name, created_at, updated_at) VALUES ('Ludhaina', NOW(), NOW());
SET @profile_id = LAST_INSERT_ID();

-- Paid Leave Rule (ID 3): 1 per month, Carry Forward = 1
INSERT INTO leave_policy_profile_rules (profile_id, leave_type_id, is_applicable, max_per_month, is_carry_forward, created_at, updated_at) 
VALUES (@profile_id, 3, 1, 1, 1, NOW(), NOW());

-- Short Leave Rule (ID 11): 1 per month, Carry Forward = 0
INSERT INTO leave_policy_profile_rules (profile_id, leave_type_id, is_applicable, short_leave_per_month, short_leave_hours, is_carry_forward, created_at, updated_at) 
VALUES (@profile_id, 11, 1, 1, 2.00, 0, NOW(), NOW());
