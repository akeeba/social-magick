/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

CREATE TABLE IF NOT EXISTS `#__socialmagick_images`
(
    "hash" CHAR(32) NOT NULL,
    "last_access" TIMESTAMP NOT NULL,
    PRIMARY KEY ("hash")
);