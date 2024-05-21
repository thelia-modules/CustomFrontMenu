
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_front_menu_child
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_front_menu_child`;

CREATE TABLE `custom_front_menu_child`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `menu_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `custom_front_menu_child_fi_100111` (`menu_id`),
    CONSTRAINT `custom_front_menu_child_fk_100111`
        FOREIGN KEY (`menu_id`)
        REFERENCES `custom_front_menu` (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_front_menu
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_front_menu`;

CREATE TABLE `custom_front_menu`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
