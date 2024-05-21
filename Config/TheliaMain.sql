
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
    `tree_left` INTEGER,
    `tree_right` INTEGER,
    `tree_level` INTEGER,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_front_menu_content
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_front_menu_content`;

CREATE TABLE `custom_front_menu_content`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(55) NOT NULL,
    `url` VARCHAR(100),
    `menu_item` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `custom_front_menu_content_fi_d26e96` (`menu_item`),
    CONSTRAINT `custom_front_menu_content_fk_d26e96`
        FOREIGN KEY (`menu_item`)
        REFERENCES `custom_front_menu_item` (`id`)
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
