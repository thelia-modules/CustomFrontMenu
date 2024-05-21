
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_front_menu_items
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_front_menu_items`;

CREATE TABLE `custom_front_menu_items`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `text_link` VARCHAR(255),
    `type` VARCHAR(255),
    `type_id` INTEGER,
    `url` VARCHAR(255),
    `locale` VARCHAR(255),
    `parent` INTEGER,
    `depth` INTEGER,
    `position` INTEGER,
    `menu` INTEGER,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_front_menu
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_front_menu`;

CREATE TABLE `custom_front_menu`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `menu_name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `name_REF` (`menu_name`)
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
