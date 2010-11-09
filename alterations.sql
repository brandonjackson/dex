/* music_albums */

ALTER TABLE `music_albums` CHANGE `album_id` `id` INT( 10 ) NOT NULL AUTO_INCREMENT,
CHANGE `album_title` `title` VARCHAR( 300 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
CHANGE `album_date` `date` DATE NOT NULL ,
CHANGE `album_desc` `description` VARCHAR( 1000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
CHANGE `date_added` `created` TIMESTAMP NOT NULL;
ALTER TABLE `music_albums` DROP `flag`;
ALTER TABLE `music_albums` DROP `label_id`;
RENAME TABLE `music_albums` TO `albums`;