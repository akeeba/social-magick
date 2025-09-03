/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

-- Database storage for the OpenGraph image templates
CREATE TABLE IF NOT EXISTS "#__socialmagick_templates" (
    "id"                SERIAL         NOT NULL,
    "title"             VARCHAR(255),
    "created"           TIMESTAMP      NULL     DEFAULT NULL,
    "created_by"        INTEGER        NOT NULL DEFAULT '0',
    "modified"          TIMESTAMP      NULL     DEFAULT NULL,
    "modified_by"       INTEGER        NOT NULL DEFAULT '0',
    "checked_out"       INTEGER        NOT NULL DEFAULT '0',
    "checked_out_time"  TIMESTAMP      NULL     DEFAULT NULL,
    "ordering"          BIGINT         NOT NULL DEFAULT '0',
    "params"            TEXT           NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS `#__socialmagick_images`
(
    "hash" CHAR(32) NOT NULL,
    "last_access" TIMESTAMP NOT NULL,
    PRIMARY KEY ("hash")
);