/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

-- Database storage for the OpenGraph image templates
CREATE TABLE IF NOT EXISTS "#__socialmagick_templates" (
    "id"                SERIAL         NOT NULL,
    "title"             VARCHAR(255),
    "enabled"           INTEGER        NOT NULL DEFAULT '1',
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

INSERT INTO "#__socialmagick_templates"
(id, title, enabled, created, created_by, modified, modified_by, checked_out, checked_out_time, ordering, params)
VALUES
    (1, 'Overlay', 1, '2025-09-04 08:34:31', 70, null, 0, 0, null, 0, '{"template-w":1200,"template-h":640,"base-color":"#000000","base-color-alpha":"0","base-image":"media\\/com_socialmagick\\/images\\/overlay.png#joomlaImage:\\/\\/local-media\\/com_socialmagick\\/images\\/overlay.png?width=1200&height=630","use-article-image":"1","image_source":"customfullintro","image_field":"ogimage","static_image":"","image-z":"under","image-cover":"1","image-width":1200,"image-height":630,"image-x":0,"image-y":0,"overlay_text":"1","text-font":"OpenSans-Bold.ttf","font-size":32,"text-color":"#ffffff","text-height":270,"text-width":500,"text-align":"center","text-y-center":"1","text-y-adjust":3,"text-y-absolute":0,"text-x-center":"1","text-x-adjust":0,"text-x-absolute":0}'),
    (2, 'Solid', 1, '2025-09-04 12:23:45', 70, null, 0, 0, null, 0, '{"template-w":1200,"template-h":640,"base-color":"#000000","base-color-alpha":"0","base-image":"media\\/com_socialmagick\\/images\\/solid.png#joomlaImage:\\/\\/local-plugins\\/system\\/socialmagick\\/images\\/solid.png?width=1200&height=630","use-article-image":"0","image_source":"customfullintro","image_field":"ogimage","static_image":"","image-z":"under","image-cover":"1","image-width":1200,"image-height":630,"image-x":0,"image-y":0,"overlay_text":"1","text-font":"OpenSans-Bold.ttf","font-size":32,"text-color":"#ffffff","text-height":280,"text-width":600,"text-align":"center","text-y-center":"1","text-y-adjust":0,"text-y-absolute":0,"text-x-center":"1","text-x-adjust":0,"text-x-absolute":0}'),
    (3, 'Cutout', 1, '2025-09-04 12:25:12', 70, null, 0, 0, null, 0, '{"template-w":1200,"template-h":640,"base-color":"#000000","base-color-alpha":"0","base-image":"media\\/com_socialmagick\\/images\\/cutout.png#joomlaImage:\\/\\/local-media\\/com_socialmagick\\/images\\/cutout.png?width=1200&height=630","use-article-image":"1","image_source":"customfullintro","image_field":"ogimage","static_image":"","image-z":"under","image-cover":"0","image-width":410,"image-height":420,"image-x":700,"image-y":115,"overlay_text":"1","text-font":"OpenSans-Bold.ttf","font-size":32,"text-color":"#ffffff","text-height":415,"text-width":430,"text-align":"left","text-y-center":"1","text-y-adjust":0,"text-y-absolute":0,"text-x-center":"0","text-x-adjust":0,"text-x-absolute":165}'),
    (4, 'Logo', 1, '2025-09-08 21:14:06', 70, null, 0, 0, null, 0, '{"template-w":1200,"template-h":630,"base-color":"#339092","base-color-alpha":"100","base-image":"","use-article-image":"1","image_source":"static","image_field":"ogimage","static_image":"media\\/com_socialmagick\\/images\\/logo-white-256.png#joomlaImage:\\/\\/local-media\\/com_socialmagick\\/images\\/logo-white-256.png?width=256&height=256","image-z":"over","image-cover":"0","image-width":96,"image-height":96,"image-x":1088,"image-y":518,"overlay_text":"1","text-font":"OpenSans-Bold.ttf","font-size":86,"text-color":"#ffffff","text-height":502,"text-width":1000,"text-align":"left","text-y-center":"1","text-y-adjust":0,"text-y-absolute":64,"text-x-center":"1","text-x-adjust":0,"text-x-absolute":0}')
ON CONFLICT (id) DO NOTHING;

CREATE TABLE IF NOT EXISTS `#__socialmagick_images`
(
    "hash" CHAR(32) NOT NULL,
    "last_access" TIMESTAMP NOT NULL,
    PRIMARY KEY ("hash")
);