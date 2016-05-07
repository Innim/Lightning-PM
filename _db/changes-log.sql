ALTER TABLE  `lpm_issue_counters` ADD  `imgsCount` INT( 11 ) NOT NULL DEFAULT  '0' COMMENT  'количество картинок, прикрепленных к задаче' AFTER `commentsCount`;
INSERT INTO `lpm_issue_counters` (issueId,imgsCount) SELECT `is`.`id`, (select count(*) from `lpm_images` as `i` where i.itemId = is.id) as `count` FROM `lpm_issues` as `is` WHERE `deleted` = 0 on duplicate key update `imgsCount` = VALUES(`imgsCount`);

# 2014.10.10
ALTER TABLE `lpm_users`
CHANGE `pass` `pass` varchar(255) COLLATE 'utf8_general_ci' NOT NULL AFTER `email`,
COMMENT='';

#### Вставка поля idInProject
ALTER TABLE `lpm_issues` ADD `idInProject` INT(11) NOT NULL AFTER `projectId`;

#### Заполнение поля idInProject
DELIMITER //

DROP PROCEDURE IF EXISTS idInProject;
CREATE PROCEDURE `idInProject`()
BEGIN
DECLARE id INT(11);
DECLARE done INT DEFAULT FALSE;
DECLARE cur CURSOR FOR SELECT DISTINCT `projectId` FROM `lpm_issues`;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
OPEN cur;
read_loop: LOOP
    FETCH cur INTO id;
    IF done THEN
      LEAVE read_loop;
    END IF;
    SET @x:=0; UPDATE `lpm_issues` SET `idInProject` = @x:=@x+1 WHERE `projectId` = id ORDER BY `id`;
  END LOOP;

  CLOSE cur;
END//

CALL idInProject();

DROP PROCEDURE IF EXISTS idInProject;

#### Вставка поля блокировки для пользователей
ALTER TABLE `lpm_users` ADD `locked` TINYINT(1) NOT NULL DEFAULT '0' AFTER `role`;

## 2014.11.19
ALTER TABLE `lpm_images` ADD `deleted` TINYINT(1) NOT NULL DEFAULT '0' ;

## 2014.11.21
ALTER TABLE `lpm_issues` ADD `hours` int(11) NOT NULL AFTER `name`;

#### Таблица для воостановления пароля
CREATE TABLE IF NOT EXISTS `lpm_recovery_emails` (
  `id` bigint(19) unsigned zerofill NOT NULL auto_increment,
  `userId` bigint(19) NOT NULL,
  `recoveryKey` varchar(255) NOT NULL,
  `expDate` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY (`userId`)

)

-- 2014-10-13 07:07:37

ALTER TABLE `lpm_projects` ADD `isArchive` BOOLEAN NOT NULL DEFAULT FALSE AFTER `issuesCount`;
-- 2015-08-24 14:38:00