-- =====================================================================
-- KCCA VRSP — trader support, chairman notices and complaints
-- Applied alongside schema.sql. Idempotent.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. PRESCRIBED ATTIRE AND PRICING, BY TRADE
-- ---------------------------------------------------------------------
-- One prescribed set of workwear per trade, priced as the Authority
-- supplies it. The colour is the point: a red apron reads as a butcher
-- and a white one as a cook, from across a market, which is what makes
-- the rule visible without anybody reading a sign.
--
-- issued_by decides who hands it over. In a public market the Authority
-- supplies; in a private market the chairman of that market does, because
-- the Authority has no presence there. Same obligation, different hand.
DROP TABLE IF EXISTS `trade_attire`;
CREATE TABLE `trade_attire` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trade_code`     VARCHAR(40)  NOT NULL,
  `garment`        VARCHAR(120) NOT NULL,
  `colour`         VARCHAR(60)  NOT NULL,
  `colour_hex`     CHAR(7)      NOT NULL DEFAULT '#DC351D',
  `unit_price_ugx` INT UNSIGNED NOT NULL DEFAULT 0,
  `issued_by`      ENUM('kcca','chairman') NOT NULL DEFAULT 'kcca',
  `sizes`          VARCHAR(120) DEFAULT NULL,
  `notes`          VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attire_trade` (`trade_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. THE SUPPORT CATALOGUE
-- ---------------------------------------------------------------------
-- Anything handed to a trader to help the trade: the prescribed attire,
-- mosquito nets, food, dustbins, tools. A catalogue rather than a column
-- on the trade, because what is available changes and what was issued does
-- not.
DROP TABLE IF EXISTS `support_items`;
CREATE TABLE `support_items` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(40)  NOT NULL,
  `name`           VARCHAR(160) NOT NULL,
  `category`       ENUM('attire','mosquito-net','food','sanitation','tools','other')
                     NOT NULL DEFAULT 'other',
  `unit_price_ugx` INT UNSIGNED NOT NULL DEFAULT 0,
  `requires_receipt` TINYINT(1) NOT NULL DEFAULT 1,
  `notes`          VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. WHAT WAS HANDED TO WHOM, AND WHETHER IT ARRIVED
-- ---------------------------------------------------------------------
-- A receipt, not a record of intention. received_at stays NULL until the
-- trader confirms they have it, and the follow-up list is built from what
-- has been outstanding too long — otherwise "we distributed" is
-- indistinguishable from "they received", which is the whole thing this
-- table exists to tell apart.
DROP TABLE IF EXISTS `support_issues`;
CREATE TABLE `support_issues` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`      VARCHAR(30)  NOT NULL,
  `vendor_id`      INT UNSIGNED NOT NULL,
  `item_id`        INT UNSIGNED NOT NULL,
  `quantity`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `size`           VARCHAR(20)  DEFAULT NULL,
  `issued_on`      DATE NOT NULL,
  -- Exactly one of these is set: an officer issued it, or a chairman did.
  `issued_by`        INT UNSIGNED DEFAULT NULL,
  `issued_by_chairman` INT UNSIGNED DEFAULT NULL,
  `received_at`   DATETIME DEFAULT NULL,
  `follow_up_on`  DATE DEFAULT NULL,
  `notes`         VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_issue_ref` (`reference`),
  KEY `ix_issue_vendor` (`vendor_id`),
  KEY `ix_issue_received` (`received_at`),
  CONSTRAINT `fk_issue_vendor` FOREIGN KEY (`vendor_id`)
    REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_issue_item` FOREIGN KEY (`item_id`)
    REFERENCES `support_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_issue_officer` FOREIGN KEY (`issued_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_issue_chairman` FOREIGN KEY (`issued_by_chairman`)
    REFERENCES `market_chairmen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. NOTICES FROM THE AUTHORITY TO CHAIRMEN
-- ---------------------------------------------------------------------
-- An officer writes one; a chairman receives it and publishes it to the
-- traders of their market. The two steps are separate columns because
-- "the Authority sent it" and "the traders were told" are different events,
-- and only the second is evidence the notice reached anybody.
DROP TABLE IF EXISTS `chairman_notices`;
CREATE TABLE `chairman_notices` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`      VARCHAR(30)  NOT NULL,
  `subject`        VARCHAR(200) NOT NULL,
  `body`           TEXT NOT NULL,
  `category`       VARCHAR(60)  DEFAULT 'general',
  -- NULL audience means every chairman.
  `audience`       ENUM('all','chairman','division','market') NOT NULL DEFAULT 'all',
  `chairman_id`    INT UNSIGNED DEFAULT NULL,
  `division_id`    INT UNSIGNED DEFAULT NULL,
  `market_id`      INT UNSIGNED DEFAULT NULL,
  `issued_by`      INT UNSIGNED DEFAULT NULL,
  `issued_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `requires_ack`   TINYINT(1) NOT NULL DEFAULT 1,
  `acknowledge_by` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notice_ref` (`reference`),
  CONSTRAINT `fk_notice_chairman` FOREIGN KEY (`chairman_id`)
    REFERENCES `market_chairmen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notice_division` FOREIGN KEY (`division_id`)
    REFERENCES `divisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notice_market` FOREIGN KEY (`market_id`)
    REFERENCES `markets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notice_officer` FOREIGN KEY (`issued_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per chairman the notice reached, and what they did with it.
DROP TABLE IF EXISTS `chairman_notice_receipts`;
CREATE TABLE `chairman_notice_receipts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notice_id`   INT UNSIGNED NOT NULL,
  `chairman_id` INT UNSIGNED NOT NULL,
  `read_at`     DATETIME DEFAULT NULL,
  `forwarded_at` DATETIME DEFAULT NULL,
  `forwarded_note` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt` (`notice_id`, `chairman_id`),
  CONSTRAINT `fk_receipt_notice` FOREIGN KEY (`notice_id`)
    REFERENCES `chairman_notices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_receipt_chairman` FOREIGN KEY (`chairman_id`)
    REFERENCES `market_chairmen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. COMPLAINTS RAISED BY A TRADER, ROUTED THROUGH THE CHAIRMAN
-- ---------------------------------------------------------------------
-- A trader's complaint goes to their chairman first, not to the Authority.
-- The chairman is the person in the market who can answer it; the
-- Authority is the escalation. forward_to_authority_at is NULL until it
-- actually escalated, so "the chairman handled it" and "the chairman
-- passed it on" stay distinguishable.
DROP TABLE IF EXISTS `trader_complaints`;
CREATE TABLE `trader_complaints` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`      VARCHAR(30)  NOT NULL,
  `vendor_id`      INT UNSIGNED NOT NULL,
  `chairman_id`    INT UNSIGNED DEFAULT NULL,
  `category`       ENUM('attire','allocation','fee','sanitation','enforcement','market-condition','other')
                     NOT NULL DEFAULT 'other',
  `subject`        VARCHAR(200) NOT NULL,
  `body`           TEXT NOT NULL,
  `status`         ENUM('with-chairman','forwarded','with-authority','resolved','declined')
                     NOT NULL DEFAULT 'with-chairman',
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `chairman_note`  VARCHAR(1000) DEFAULT NULL,
  `forwarded_at`   DATETIME DEFAULT NULL,
  `forwarded_by`   INT UNSIGNED DEFAULT NULL,
  `authority_reply` TEXT DEFAULT NULL,
  `resolved_at`    DATETIME DEFAULT NULL,
  `resolution`     VARCHAR(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_complaint_ref` (`reference`),
  KEY `ix_complaint_chairman` (`chairman_id`, `status`),
  KEY `ix_complaint_vendor` (`vendor_id`),
  CONSTRAINT `fk_complaint_vendor` FOREIGN KEY (`vendor_id`)
    REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_complaint_chairman` FOREIGN KEY (`chairman_id`)
    REFERENCES `market_chairmen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_complaint_forwarder` FOREIGN KEY (`forwarded_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;