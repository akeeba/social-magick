/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

-- Database storage for the OpenGraph image templates
CREATE TABLE IF NOT EXISTS `#__socialmagick_templates` (
    `id`                SERIAL,
    `title`             VARCHAR(255),
    `created`           DATETIME                       NULL     DEFAULT NULL,
    `created_by`        INT(11)                        NOT NULL DEFAULT '0',
    `modified`          DATETIME                       NULL     DEFAULT NULL,
    `modified_by`       INT(11)                        NOT NULL DEFAULT '0',
    `checked_out`       INT(11)                        NOT NULL DEFAULT '0',
    `checked_out_time`  DATETIME                       NULL     DEFAULT NULL,
    `ordering`          BIGINT(20)                     NOT NULL DEFAULT '0',
    `params`            MEDIUMTEXT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__socialmagick_images` (
    `hash` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_access` datetime NOT NULL,
    PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;