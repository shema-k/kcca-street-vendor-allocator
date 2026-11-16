-- Add missing support tables without changing existing tables or records.
CREATE TABLE IF NOT EXISTS `trade_attire` (
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

CREATE TABLE IF NOT EXISTS `support_items` (
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

CREATE TABLE IF NOT EXISTS `support_issues` (
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

CREATE TABLE IF NOT EXISTS `chairman_notices` (
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

CREATE TABLE IF NOT EXISTS `chairman_notice_receipts` (
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

CREATE TABLE IF NOT EXISTS `trader_complaints` (
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