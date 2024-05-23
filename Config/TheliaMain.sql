
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_front_menu_item
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_front_menu_item`;

CREATE TABLE `custom_front_menu_item`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `view` VARCHAR(255) NOT NULL,
    `view_id` INTEGER,
    `tree_left` INTEGER,
    `tree_right` INTEGER,
    `tree_level` INTEGER,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_front_menu_item_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_front_menu_item_i18n`;

CREATE TABLE `custom_front_menu_item_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `url` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `custom_front_menu_item_i18n_fk_a026f4`
        FOREIGN KEY (`id`)
        REFERENCES `custom_front_menu_item` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
