-- =====================================================================
-- KAMPALA CAPITAL CITY AUTHORITY (KCCA)
-- Vendor Relocation & Space Allocation System (VRSP)
-- Database schema
--
-- Legal basis: KCCA Act 2010 (Cap 195), Markets Act 2023,
--              Trade Licensing Act Cap 101 (as amended 2015)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. OFFICIAL REFERENCE DATA
-- ---------------------------------------------------------------------

-- The five Division Urban Councils of Kampala Capital City.
-- Areas, parishes, LC1s and populations are from the
-- KCCA Strategic Plan FY2025/26 - FY2029/30 (Table 1.1).
DROP TABLE IF EXISTS `divisions`;
CREATE TABLE `divisions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(12)  NOT NULL,
  `name`           VARCHAR(80)  NOT NULL,
  `mayor`          VARCHAR(120) NOT NULL,
  `town_clerk`     VARCHAR(120) DEFAULT NULL,
  `area_sqkm`      DECIMAL(6,1) NOT NULL,
  `parishes`       SMALLINT UNSIGNED NOT NULL,
  `lc1_wards`      SMALLINT UNSIGNED NOT NULL,
  `population`     INT UNSIGNED NOT NULL,
  `lat`            DECIMAL(10,7) NOT NULL,
  `lng`            DECIMAL(10,7) NOT NULL,
  `polygon_json`   TEXT         DEFAULT NULL,
  `summary`        TEXT         DEFAULT NULL,
  `profile_image`  VARCHAR(255) DEFAULT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_div_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parishes. Kampala Capital City has 101 parishes.
DROP TABLE IF EXISTS `parishes`;
CREATE TABLE `parishes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `lat`         DECIMAL(10,7) DEFAULT NULL,
  `lng`         DECIMAL(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_parish_div` (`division_id`),
  CONSTRAINT `fk_parish_div` FOREIGN KEY (`division_id`)
    REFERENCES `divisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. MARKET REGISTER  (register of markets - Markets Act 2023, s.17)
-- ---------------------------------------------------------------------

-- `data_basis` records where each figure comes from:
--   'official'  - published by KCCA
--   'derived'   - computed from official city totals
--   'estimated' - modelled, marked as such in the interface
DROP TABLE IF EXISTS `markets`;
CREATE TABLE `markets` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`             VARCHAR(20)  NOT NULL,
  `name`             VARCHAR(120) NOT NULL,
  `division_id`      INT UNSIGNED NOT NULL,
  `category`         ENUM('public','private') NOT NULL DEFAULT 'public',
  `market_type`      ENUM('permanent','seasonal','temporary') NOT NULL DEFAULT 'permanent',
  `ownership`        VARCHAR(160) DEFAULT NULL,
  `parish`           VARCHAR(120) DEFAULT NULL,
  `lat`              DECIMAL(10,7) NOT NULL,
  `lng`              DECIMAL(10,7) NOT NULL,
  `capacity`         INT UNSIGNED NOT NULL DEFAULT 0,
  `registered_vendors` INT UNSIGNED NOT NULL DEFAULT 0,
  `vacant_spaces`    INT UNSIGNED NOT NULL DEFAULT 0,
  `data_basis`       ENUM('official','derived','estimated') NOT NULL DEFAULT 'estimated',
  `water_available`  TINYINT(1) NOT NULL DEFAULT 0,
  `power_available`  TINYINT(1) NOT NULL DEFAULT 0,
  `toilets`          TINYINT(1) NOT NULL DEFAULT 0,
  `waste_collection` TINYINT(1) NOT NULL DEFAULT 0,
  `storage_rooms`    TINYINT(1) NOT NULL DEFAULT 0,
  `cold_chain`       TINYINT(1) NOT NULL DEFAULT 0,
  `accessible_ramps` TINYINT(1) NOT NULL DEFAULT 0,
  `category_mix`     VARCHAR(255) DEFAULT NULL,
  `transport_stand`  VARCHAR(120) DEFAULT NULL,
  `opened_year`      SMALLINT UNSIGNED DEFAULT NULL,
  `condition_score`  TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `admin_name`       VARCHAR(120) DEFAULT NULL,
  `admin_phone`      VARCHAR(40)  DEFAULT NULL,
  `status`           ENUM('operational','upgrading','under-rehabilitation','closed') NOT NULL DEFAULT 'operational',
  `notes`            TEXT DEFAULT NULL,
  `source_note`      VARCHAR(255) DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_market_code` (`code`),
  KEY `ix_market_div` (`division_id`),
  KEY `ix_market_cat` (`category`),
  CONSTRAINT `fk_market_div` FOREIGN KEY (`division_id`)
    REFERENCES `divisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual pitch within a market (Markets Act 2023 definition of "pitch")
DROP TABLE IF EXISTS `market_spaces`;
CREATE TABLE `market_spaces` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `market_id`    INT UNSIGNED NOT NULL,
  `space_code`   VARCHAR(30)  NOT NULL,
  `section`      VARCHAR(80)  DEFAULT NULL,
  -- Position of the individual pitch inside the market. Generated by the
  -- seeder from the market's own coordinates, laid out in section blocks,
  -- so the pitch register can be drawn on a map stall by stall.
  `lat`          DECIMAL(10,7) DEFAULT NULL,
  `lng`          DECIMAL(10,7) DEFAULT NULL,
  `size_sqm`     DECIMAL(6,2) NOT NULL DEFAULT 9.0,
  `status`       ENUM('vacant','occupied','reserved','under-maintenance') NOT NULL DEFAULT 'vacant',
  `monthly_rent_ugx` INT UNSIGNED NOT NULL DEFAULT 0,
  `has_water`    TINYINT(1) NOT NULL DEFAULT 0,
  `has_power`    TINYINT(1) NOT NULL DEFAULT 0,
  `near_toilet`  TINYINT(1) NOT NULL DEFAULT 0,
  `step_free`    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_space_code` (`space_code`),
  KEY `ix_space_market` (`market_id`),
  KEY `ix_space_status` (`status`),
  CONSTRAINT `fk_space_market` FOREIGN KEY (`market_id`)
    REFERENCES `markets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. TRADE ZONES  (unlawful / congested occupation to be vacated)
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `trade_zones`;
CREATE TABLE `trade_zones` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`              VARCHAR(20)  NOT NULL,
  `name`              VARCHAR(160) NOT NULL,
  `division_id`       INT UNSIGNED NOT NULL,
  `parish`            VARCHAR(120) DEFAULT NULL,
  `road_or_feature`   VARCHAR(160) DEFAULT NULL,
  `zone_type`         ENUM('roadside','pavement','veranda','open-park','junction','bus-stage','roundabout','entrance') NOT NULL,
  `lat`               DECIMAL(10,7) NOT NULL,
  `lng`               DECIMAL(10,7) NOT NULL,
  `polygon_json`      TEXT DEFAULT NULL,
  -- observed / field-survey measures
  `estimated_vendors` INT UNSIGNED NOT NULL DEFAULT 0,
  `footpath_obstruction_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `carriageway_encroachment_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `months_occupied`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `complaints_30d`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `strategic_corridor` TINYINT(1) NOT NULL DEFAULT 0,
  `near_public_asset` VARCHAR(120) DEFAULT NULL,
  `peak_days`         VARCHAR(80) DEFAULT NULL,
  `peak_hours`        VARCHAR(60) DEFAULT NULL,
  `category_mix`      VARCHAR(255) DEFAULT NULL,
  `status`            ENUM('under-survey','notice-issued','clearing','relocating','cleared','litigation-hold') NOT NULL DEFAULT 'under-survey',
  `survey_date`       DATE DEFAULT NULL,
  `notified_date`     DATE DEFAULT NULL,
  `deadline_date`     DATE DEFAULT NULL,
  `notes`             TEXT DEFAULT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zone_code` (`code`),
  KEY `ix_zone_div` (`division_id`),
  KEY `ix_zone_status` (`status`),
  CONSTRAINT `fk_zone_div` FOREIGN KEY (`division_id`)
    REFERENCES `divisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. VENDOR REGISTER  (register of vendors - Markets Act 2023, s.18)
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_code`         VARCHAR(24)  NOT NULL,
  `full_name`           VARCHAR(140) NOT NULL,
  `national_id`         VARCHAR(40)  DEFAULT NULL,
  `phone`               VARCHAR(40)  DEFAULT NULL,
  `alternative_phone`   VARCHAR(40)  DEFAULT NULL,
  `email`               VARCHAR(120) DEFAULT NULL,
  `gender`              ENUM('male','female','other') NOT NULL DEFAULT 'male',
  `date_of_birth`       DATE DEFAULT NULL,
  `division_id`         INT UNSIGNED NOT NULL,
  `parish`              VARCHAR(120) DEFAULT NULL,
  `current_zone_id`     INT UNSIGNED DEFAULT NULL,
  `business_category`   ENUM('food-cooking','fresh-produce','meat-fish','beverages','crops-grains','household-goods','clothing-textiles','electronics-repairs','salon-services','mobile-services','handicraft','livestock','other') NOT NULL,
  `business_description` VARCHAR(200) DEFAULT NULL,
  `trading_days`        VARCHAR(60) DEFAULT NULL,
  `started_year`        SMALLINT UNSIGNED DEFAULT NULL,
  `monthly_turnover_ugx` INT UNSIGNED NOT NULL DEFAULT 0,
  `startup_capital_ugx` INT UNSIGNED NOT NULL DEFAULT 0,
  `household_size`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `dependants`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_person_with_disability` TINYINT(1) NOT NULL DEFAULT 0,
  `is_single_parent`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_youth`            TINYINT(1) NOT NULL DEFAULT 0,
  `is_veteran_ex_enforcee` TINYINT(1) NOT NULL DEFAULT 0,
  `has_trading_licence` TINYINT(1) NOT NULL DEFAULT 0,
  `licence_class`       ENUM('unlicensed','street-grade-1','street-grade-2','market-standard') NOT NULL DEFAULT 'unlicensed',
  `licence_year`        SMALLINT UNSIGNED DEFAULT NULL,
  `association`         VARCHAR(160) DEFAULT NULL,
  `photo`               VARCHAR(255) DEFAULT NULL,
  `status`              ENUM('surveyed','notified','offered-space','relocated','declined','deferred','complaint') NOT NULL DEFAULT 'surveyed',
  `registered_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendor_code` (`vendor_code`),
  KEY `ix_vendor_zone` (`current_zone_id`),
  KEY `ix_vendor_div` (`division_id`),
  KEY `ix_vendor_cat` (`business_category`),
  KEY `ix_vendor_status` (`status`),
  CONSTRAINT `fk_vendor_zone` FOREIGN KEY (`current_zone_id`)
    REFERENCES `trade_zones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vendor_div` FOREIGN KEY (`division_id`)
    REFERENCES `divisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. ALLOCATION  (the relocation decision record)
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `allocations`;
CREATE TABLE `allocations` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`     VARCHAR(30)  NOT NULL,
  `vendor_id`     INT UNSIGNED NOT NULL,
  `market_id`     INT UNSIGNED NOT NULL,
  `market_space_id` INT UNSIGNED DEFAULT NULL,
  `distance_km`   DECIMAL(6,2) NOT NULL DEFAULT 0,
  `total_score`   DECIMAL(6,2) NOT NULL DEFAULT 0,
  `score_breakdown` TEXT DEFAULT NULL,
  `reasoning`     TEXT DEFAULT NULL,
  `rank`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status`        ENUM('proposed','offered','accepted','occupied','declined','expired','withdrawn') NOT NULL DEFAULT 'proposed',
  `recommendation_id` INT UNSIGNED DEFAULT NULL,
  `source`        ENUM('manual','model') NOT NULL DEFAULT 'manual',
  `offer_expires` DATE DEFAULT NULL,
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at`    DATETIME DEFAULT NULL,
  /* Set to 1 while the allocation is live (proposed/offered/accepted/
     occupied) and NULL once closed. MySQL treats NULL as distinct in a
     UNIQUE index, so closed rows do not collide while live ones cannot. */
  `live_flag`       TINYINT UNSIGNED
                     GENERATED ALWAYS AS (
                       CASE WHEN `status` IN ('proposed','offered','accepted','occupied')
                            THEN 1 ELSE NULL END
                     ) STORED,
  `decided_by`      INT UNSIGNED DEFAULT NULL,
  `decision_note`   VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alloc_ref` (`reference`),
  KEY `ix_alloc_vendor` (`vendor_id`),
  KEY `ix_alloc_market` (`market_id`),
  KEY `ix_alloc_status` (`status`),
  /* A vendor may hold at most one live allocation, and a pitch may be
     allocated to at most one vendor. Enforced here rather than in PHP so
     that a race between two officers cannot produce a double booking. */
  UNIQUE KEY `uq_alloc_live_vendor` (`vendor_id`, `live_flag`),
  UNIQUE KEY `uq_alloc_live_space`  (`market_space_id`, `live_flag`),
  CONSTRAINT `fk_alloc_vendor` FOREIGN KEY (`vendor_id`)
    REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_market` FOREIGN KEY (`market_id`)
    REFERENCES `markets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_space` FOREIGN KEY (`market_space_id`)
    REFERENCES `market_spaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. ENFORCEMENT  (notices, ultimatums, clear-up operations)
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `enforcement_actions`;
CREATE TABLE `enforcement_actions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`      VARCHAR(30)  NOT NULL,
  `zone_id`        INT UNSIGNED NOT NULL,
  `action_type`    ENUM('survey','notice','ultimatum','eviction','relocation-support','follow-up','litigation') NOT NULL,
  `status`         ENUM('planned','issued','in-progress','completed','suspended','escalated') NOT NULL DEFAULT 'planned',
  `issued_date`    DATE NOT NULL,
  `deadline_date`  DATE DEFAULT NULL,
  `completed_date` DATE DEFAULT NULL,
  `officers_deployed` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `vendors_affected` INT UNSIGNED NOT NULL DEFAULT 0,
  `vendors_relocated` INT UNSIGNED NOT NULL DEFAULT 0,
  `verandas_cleared` INT UNSIGNED NOT NULL DEFAULT 0,
  `materials_removed_m3` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `authorised_by`  VARCHAR(140) DEFAULT NULL,
  `officer_name`   VARCHAR(140) DEFAULT NULL,
  `officer_number` VARCHAR(40)  DEFAULT NULL,
  `outcome`        TEXT DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enf_ref` (`reference`),
  KEY `ix_enf_zone` (`zone_id`),
  KEY `ix_enf_status` (`status`),
  CONSTRAINT `fk_enf_zone` FOREIGN KEY (`zone_id`)
    REFERENCES `trade_zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5b. TRADE CATEGORIES
--     The product taxonomy the viability model reasons over.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `trade_categories`;
CREATE TABLE `trade_categories` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(40)  NOT NULL,
  `name`           VARCHAR(120) NOT NULL,
  `description`    VARCHAR(255) DEFAULT NULL,
  -- demand index 0-100: how reliably this trade draws customers in Kampala
  `demand_index`   TINYINT UNSIGNED NOT NULL DEFAULT 50,
  -- typical daily takings in UGX, used as a sanity check on projections
  `typical_daily_take_ugx` INT UNSIGNED NOT NULL DEFAULT 50000,
  -- what one customer typically spends. Revenue projection is built from
  -- this and the projected customer count, not from an arbitrary factor.
  `avg_spend_per_customer_ugx` INT UNSIGNED NOT NULL DEFAULT 8000,
  -- fraction of customers lost if the goods are unavailable (0-1)
  `substitution_risk` DECIMAL(4,3) NOT NULL DEFAULT 0.200,
  -- share of customers who arrive on foot rather than by vehicle
  `walk_in_share`  DECIMAL(4,3) NOT NULL DEFAULT 0.600,
  `requires_cold_chain` TINYINT(1) NOT NULL DEFAULT 0,
  `requires_power` TINYINT(1) NOT NULL DEFAULT 0,
  `requires_water` TINYINT(1) NOT NULL DEFAULT 0,
  `perishable`     TINYINT(1) NOT NULL DEFAULT 0,
  `seasonality`    VARCHAR(160) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6b. FOOTFALL OBSERVATIONS
--     Counted movements at a trading location. The model learns from these;
--     nothing is estimated without a stated confidence.
-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
-- 6b-ii. MARKET TRADE MIX
--      Which trades are already established at each market. Without this
--      the competition factor is always zero, because vendors are only
--      recorded against a market once an allocation is accepted.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `market_trade_mix`;
CREATE TABLE `market_trade_mix` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `market_id`         INT UNSIGNED NOT NULL,
  `business_category` VARCHAR(40) NOT NULL,
  `vendor_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mix` (`market_id`, `business_category`),
  KEY `ix_mix_cat` (`business_category`),
  CONSTRAINT `fk_mix_market` FOREIGN KEY (`market_id`)
    REFERENCES `markets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `footfall_observations`;
CREATE TABLE `footfall_observations` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `market_id`     INT UNSIGNED NOT NULL,
  `observed_on`   DATE NOT NULL,
  `hour_band`     TINYINT UNSIGNED NOT NULL,          -- 0-5, six 4-hour bands
  `day_type`      ENUM('weekday','saturday','sunday','holiday') NOT NULL DEFAULT 'weekday',
  `people_in`     INT UNSIGNED NOT NULL DEFAULT 0,
  `people_out`    INT UNSIGNED NOT NULL DEFAULT 0,
  `traders_present` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `source`        ENUM('manual-count','sensor','estimate','trader-log') NOT NULL DEFAULT 'manual-count',
  `observer`      VARCHAR(140) DEFAULT NULL,
  `weather`       ENUM('clear','rain','overcast') DEFAULT NULL,
  `event_note`    VARCHAR(160) DEFAULT NULL,          -- market day, school holiday …
  `note`          VARCHAR(255) DEFAULT NULL,
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ff_market_date` (`market_id`, `observed_on`),
  KEY `ix_ff_date` (`observed_on`),
  CONSTRAINT `fk_ff_market` FOREIGN KEY (`market_id`)
    REFERENCES `markets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6c. LAND-USE AND TRADING RESTRICTIONS
--     Statutory or physical constraints on what may trade at a location.
--     A pitch subject to a restriction cannot be recommended for a
--     category the restriction excludes.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `land_use_restrictions`;
CREATE TABLE `land_use_restrictions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `market_id`    INT UNSIGNED NOT NULL,
  `code`         VARCHAR(40)  NOT NULL,
  `title`        VARCHAR(160) NOT NULL,
  `description`  TEXT DEFAULT NULL,
  -- a restriction may forbid a whole category, or only part of the site
  `excluded_categories` VARCHAR(255) DEFAULT NULL,   -- comma-separated codes; empty = all
  `applies_to_section`  VARCHAR(80) DEFAULT NULL,    -- empty = the whole market
  `authority`    VARCHAR(160) DEFAULT NULL,
  `legal_basis`  VARCHAR(200) DEFAULT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to`   DATE DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lur_market_code` (`market_id`, `code`),
  KEY `ix_lur_market` (`market_id`),
  CONSTRAINT `fk_lur_market` FOREIGN KEY (`market_id`)
    REFERENCES `markets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5c. RECOMMENDATIONS
--     Output of the reach/viability model. Every recommendation stores the
--     factors that produced it, so an officer can see why the model chose
--     a location, and an approval can be audited later.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `recommendations`;
CREATE TABLE `recommendations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`      VARCHAR(30)  NOT NULL,
  `vendor_id`      INT UNSIGNED NOT NULL,
  `market_id`      INT UNSIGNED NOT NULL,
  `space_id`       INT UNSIGNED DEFAULT NULL,
  `rank`           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `reach_score`    DECIMAL(6,2) NOT NULL DEFAULT 0,
  `viability_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `footfall_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `confidence`     DECIMAL(5,4) NOT NULL DEFAULT 0,
  `projected_customers_day` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `projected_revenue_ugx`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  `distance_km`    DECIMAL(6,2) NOT NULL DEFAULT 0,
  `model_version`  VARCHAR(20)  NOT NULL DEFAULT 'v1.0',
  `factors_json`   TEXT NOT NULL,                    -- full per-factor breakdown
  `explanation`    TEXT NOT NULL,                    -- plain-language reasoning
  `warnings_json`  TEXT DEFAULT NULL,                -- restrictions hit, low confidence …
  `status`         ENUM('proposed','approved','rejected','allocated','expired','superseded') NOT NULL DEFAULT 'proposed',
  `decided_by`     INT UNSIGNED DEFAULT NULL,
  `decided_at`     DATETIME DEFAULT NULL,
  `decision_note`  VARCHAR(500) DEFAULT NULL,
  `expires_on`     DATE DEFAULT NULL,
  `generated_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rec_ref` (`reference`),
  KEY `ix_rec_vendor` (`vendor_id`),
  KEY `ix_rec_market` (`market_id`),
  KEY `ix_rec_status` (`status`),
  CONSTRAINT `fk_rec_vendor` FOREIGN KEY (`vendor_id`)
    REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_market` FOREIGN KEY (`market_id`)
    REFERENCES `markets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_space` FOREIGN KEY (`space_id`)
    REFERENCES `market_spaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. SYSTEM USERS  (role-based access)
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(60)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(140) NOT NULL,
  `title`         VARCHAR(140) DEFAULT NULL,
  `directorate`   VARCHAR(160) DEFAULT NULL,
  `role`          ENUM('administrator','executive','mayor','planner','enforcement','revenue','market-admin','auditor','viewer') NOT NULL DEFAULT 'viewer',
  `division_id`   INT UNSIGNED DEFAULT NULL,
  `email`         VARCHAR(120) DEFAULT NULL,
  `phone`         VARCHAR(40)  DEFAULT NULL,
  -- Whether the name and title are published by KCCA or illustrative. An
  -- officer's name is what the audit trail attributes an action to, so it
  -- matters that a reader can tell which is which.
  `name_basis`    ENUM('published','illustrative') NOT NULL DEFAULT 'illustrative',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `ix_user_role` (`role`),
  CONSTRAINT `fk_user_div` FOREIGN KEY (`division_id`)
    REFERENCES `divisions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. AUDIT TRAIL
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `actor`       VARCHAR(140) NOT NULL,
  `action`      VARCHAR(80) NOT NULL,
  `entity`      VARCHAR(60) DEFAULT NULL,
  `entity_id`   INT UNSIGNED DEFAULT NULL,
  `detail`      TEXT DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_user` (`user_id`),
  KEY `ix_audit_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. STRATEGIC PLAN INDICATORS  (KCCA Strategic Plan FY2025/26-29/30)
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `strategic_indicators`;
CREATE TABLE `strategic_indicators` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_no`  TINYINT UNSIGNED NOT NULL,
  `objective`     VARCHAR(200) NOT NULL,
  `indicator`     VARCHAR(200) NOT NULL,
  `baseline`      VARCHAR(60)  DEFAULT NULL,
  `target_2030`   VARCHAR(60)  DEFAULT NULL,
  `current_value` VARCHAR(60)  DEFAULT NULL,
  `unit`          VARCHAR(40)  DEFAULT NULL,
  `direction`     ENUM('increase','decrease') NOT NULL DEFAULT 'increase',
  `source`        VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. OFFICIAL KCCA ONLINE SERVICES (for the Resources / links page)
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `kcca_services`;
CREATE TABLE `kcca_services` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(120) NOT NULL,
  `url`       VARCHAR(255) NOT NULL,
  `category`  VARCHAR(60)  NOT NULL,
  `purpose`   VARCHAR(255) DEFAULT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. PUBLIC NOTICES / MEDIA
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `notices`;
CREATE TABLE `notices` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `headline`    VARCHAR(255) NOT NULL,
  `body`        TEXT NOT NULL,
  `published_on` DATE NOT NULL,
  `source_url`  VARCHAR(255) DEFAULT NULL,
  `category`    VARCHAR(60) DEFAULT 'Trade Order',
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. RELOCATION TRACKING
--     Every state change of an allocation, so progress can be followed
--     over time and not merely read off the current row.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `relocation_events`;
CREATE TABLE `relocation_events` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `allocation_id` INT UNSIGNED DEFAULT NULL,
  `vendor_id`     INT UNSIGNED DEFAULT NULL,
  `market_id`     INT UNSIGNED DEFAULT NULL,
  `from_status`   VARCHAR(30) DEFAULT NULL,
  `to_status`     VARCHAR(30) NOT NULL,
  `event_type`    ENUM('created','offered','accepted','declined','withdrawn',
                       'occupied','relocated','cancelled','expired','note') NOT NULL,
  `actor`         VARCHAR(140) NOT NULL,
  `note`          VARCHAR(500) DEFAULT NULL,
  `occurred_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_rel_alloc` (`allocation_id`),
  KEY `ix_rel_vendor` (`vendor_id`),
  KEY `ix_rel_time` (`occurred_at`),
  CONSTRAINT `fk_rel_alloc` FOREIGN KEY (`allocation_id`)
    REFERENCES `allocations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. SYSTEM SETTINGS  (policy values editable without a code change)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(80)  NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `label`         VARCHAR(160) NOT NULL,
  `description`   VARCHAR(500) DEFAULT NULL,
  `group_name`    VARCHAR(60)  NOT NULL DEFAULT 'general',
  `input_type`    ENUM('number','text','textarea','boolean','select') NOT NULL DEFAULT 'text',
  `min_value`     DECIMAL(12,4) DEFAULT NULL,
  `max_value`     DECIMAL(12,4) DEFAULT NULL,
  `options_json`  TEXT DEFAULT NULL,
  `is_editable`   TINYINT(1) NOT NULL DEFAULT 1,
  `updated_by`    VARCHAR(140) DEFAULT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  KEY `ix_set_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. BACKUPS
--     Record of every export taken, so a data-protection question can be
--     answered from the system itself.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`  VARCHAR(40) NOT NULL,
  `kind`       ENUM('csv','sql','json') NOT NULL DEFAULT 'csv',
  `report`     VARCHAR(60) NOT NULL,
  `row_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `bytes`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `path`       VARCHAR(255) DEFAULT NULL,
  `checksum`   VARCHAR(64) DEFAULT NULL,
  `requested_by` VARCHAR(140) NOT NULL,
  `reason`     VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup_ref` (`reference`),
  KEY `ix_backup_report` (`report`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 15. STALL REQUESTS
--     A trader's own choice of pitch. Distinct from an allocation, which
--     is the Authority's decision: a request starts with the trader, is
--     checked against their record, and only then becomes an allocation.
--     Keeping the two apart means a stall is never "taken" just because
--     somebody clicked it.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `stall_requests`;
CREATE TABLE `stall_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`    VARCHAR(30)  NOT NULL,
  `space_id`     INT UNSIGNED NOT NULL,
  `market_id`    INT UNSIGNED NOT NULL,
  -- The trader may be registered already, or applying for the first time.
  `vendor_id`    INT UNSIGNED DEFAULT NULL,
  `full_name`    VARCHAR(140) NOT NULL,
  `phone`        VARCHAR(20)  NOT NULL,
  `national_id`  VARCHAR(20)  DEFAULT NULL,
  `business_category` VARCHAR(40) DEFAULT NULL,
  `trade_detail` VARCHAR(200) DEFAULT NULL,
  `reason`       VARCHAR(500) DEFAULT NULL,
  `status`       ENUM('pending','reviewing','approved','rejected','cancelled','expired')
                   NOT NULL DEFAULT 'pending',
  `decided_by`   INT UNSIGNED DEFAULT NULL,
  `decided_at`   DATETIME DEFAULT NULL,
  `decision_note` VARCHAR(500) DEFAULT NULL,
  `allocation_id` INT UNSIGNED DEFAULT NULL,
  `source_ip`    VARCHAR(45)  DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_req_ref` (`reference`),
  KEY `ix_req_space` (`space_id`),
  KEY `ix_req_status` (`status`),
  KEY `ix_req_market` (`market_id`),
  CONSTRAINT `fk_req_space` FOREIGN KEY (`space_id`)
    REFERENCES `market_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_req_market` FOREIGN KEY (`market_id`)
    REFERENCES `markets` (`id`) ON DELETE CASCADE,
  /* One open request per pitch: a pitch already requested by somebody
     else is shown as unavailable, not as something two traders can both
     be offered. */
  /* 1 while the request is open, NULL once it is decided. As with
     allocations, NULL is distinct in a UNIQUE index, so a decided
     request does not block the pitch while an open one does. */
  `live_req`     TINYINT UNSIGNED
                   GENERATED ALWAYS AS (
                     CASE WHEN `status` IN ('pending','reviewing') THEN 1 ELSE NULL END
                   ) STORED,
  UNIQUE KEY `uq_req_open_space` (`space_id`, `live_req`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;