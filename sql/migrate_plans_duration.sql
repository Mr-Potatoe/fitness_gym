-- First add the new column if it doesn't exist
ALTER TABLE plans ADD COLUMN IF NOT EXISTS duration_months INT;

-- Migrate duration days to months
UPDATE plans 
SET duration_months = CEILING(duration / 30)
WHERE duration IS NOT NULL;

-- Ensure duration_months is within valid range (1-36)
UPDATE plans 
SET duration_months = 
    CASE 
        WHEN duration_months < 1 THEN 1
        WHEN duration_months > 36 THEN 36
        ELSE duration_months
    END;

-- Drop old duration column
ALTER TABLE plans DROP COLUMN IF EXISTS duration;
