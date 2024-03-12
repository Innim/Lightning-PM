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


ALTER TABLE `lpm_projects`
ADD `scrum` tinyint NOT NULL DEFAULT '0' COMMENT 'Проект использует Scrum' AFTER `issuesCount`;
CREATE TABLE `lpm_scrum_sticker` (
  `issueId` bigint(18) NOT NULL COMMENT 'идентификатор задачи',
  `state` tinyint(2) NOT NULL COMMENT 'состояние стикера',
  PRIMARY KEY (`issueId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='стикер на scrum доске';

-- 2017-03-04 17:24:00

-- 2016-08-01 14:55:00
CREATE TABLE `lpm_user_auth`  (
  `id` bigint(18) AUTO_INCREMENT,
  `cookieHash` varchar(32) NOT NULL ,
  `userAgent` varchar(255) NOT NULL COMMENT 'информация о браузере юзера',
  `userId` bigint(18) NOT NULL COMMENT 'индентификатор пользователя',
  `hasCreated` datetime NOT NULL COMMENT 'дата создания записи',
   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Данные авторизации по куки';

ALTER TABLE `lpm_user_auth` ADD INDEX(`userId`);

ALTER TABLE `lpm_users` DROP COLUMN `cookieHash`;

-- 2017-11-03 18:00:00

CREATE TABLE `lpm_scrum_snapshot_list`
( `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор snapshot-а' ,
  `pid` INT(11) NOT NULL COMMENT 'Идентификатор проекта' ,
  `creatorId` BIGINT(19) NOT NULL COMMENT 'Идентификатор создателя snapshot-а' ,
  `created` DATETIME NOT NULL COMMENT 'Время создания snapshot-а' ,
  PRIMARY KEY (`id`)) ENGINE = InnoDB;

CREATE TABLE `lpm_scrum_snapshot` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи' ,
  `sid` INT(11) NOT NULL COMMENT 'Идентификатор snapshot-а' ,
  `issue_uid` INT(11) NOT NULL COMMENT 'Глобавльный идентификатор задачи' ,
  `issue_pid` INT(11) NOT NULL COMMENT 'Идентификатор задачи в проекте' ,
  `issue_name` VARCHAR(255) NOT NULL COMMENT 'Название задачи' ,
  `issue_state` TINYINT(2) NOT NULL COMMENT 'Состояние задачи' ,
  `issue_sp` VARCHAR(255) NOT NULL COMMENT 'Количество SP' ,
  `issue_priority` TINYINT(2) NOT NULL COMMENT 'Приоритет задачи' ,
  PRIMARY KEY (`id`)) ENGINE = InnoDB;

ALTER TABLE `lpm_scrum_snapshot`
CHANGE `issue_name` `issue_name` varchar(255) COLLATE 'utf8_general_ci' NOT NULL COMMENT 'Название задачи' AFTER `issue_pid`,
CHANGE `issue_sp` `issue_sp` varchar(255) COLLATE 'utf8_general_ci' NOT NULL COMMENT 'Количество SP' AFTER `issue_state`;

-- 2017-11-18 10:03:00

ALTER TABLE `lpm_issues` CHANGE `hours` `hours` DECIMAL(10,1) NOT NULL;

-- 2017-12-16 10:01:00

#### Вставка поля idInProject для scrum snapshot
ALTER TABLE `lpm_scrum_snapshot_list` ADD `idInProject` INT(11) NOT NULL COMMENT 'Порядковый номер снепшота по проекту' AFTER `id`;


#### Заполнение поля idInProject для scrum snapshot
CREATE TEMPORARY TABLE IF NOT EXISTS `lmp_scrum_snapshot_list_tmp_0_11_38` AS (SELECT `id`, `pid` FROM `lpm_scrum_snapshot_list`);
UPDATE `lpm_scrum_snapshot_list` SET `idInProject` = (SELECT COUNT(`id`) FROM `lmp_scrum_snapshot_list_tmp_0_11_38` WHERE
  `lmp_scrum_snapshot_list_tmp_0_11_38`.`pid` = `lpm_scrum_snapshot_list`.`pid` AND
  `lmp_scrum_snapshot_list_tmp_0_11_38`.`id` < `lpm_scrum_snapshot_list`.`id`) + 1;
DROP TEMPORARY TABLE `lmp_scrum_snapshot_list_tmp_0_11_38`;ALTER TABLE `lpm_issues` CHANGE `hours` `hours` DECIMAL(10,1) NOT NULL;

-- 2017-12-01 14:12:00

CREATE TABLE `lpm_issue_labels` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор' ,
  `projectId` INT NOT NULL DEFAULT '0' COMMENT 'Проект (0 если метка общая)' ,
  `label` VARCHAR(255) NOT NULL COMMENT 'Текст метки' ,
  `countUses` INT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Количество использований' ,
  PRIMARY KEY (`id`), INDEX (`projectId`)
) ENGINE = InnoDB CHARSET=utf8 COLLATE utf8_general_ci COMMENT = 'Метки для задач';

ALTER TABLE `lpm_issue_labels` ADD `deleted` TINYINT(1) NOT NULL DEFAULT '0' AFTER `countUses`;

-- 2018-02-02 12:16:00

ALTER TABLE `lpm_users`
ADD `slackName` varchar(255) NOT NULL COMMENT 'имя в Slack' AFTER `secret`;

ALTER TABLE `lpm_projects`
ADD `slackNotifyChannel` varchar(255) NOT NULL COMMENT 'имя канала для оповещений в Slack';

ALTER TABLE `lpm_projects`
ADD `masterId` bigint(19) NOT NULL COMMENT 'идентификатор пользователя, являющегося мастером в проекте';

-- 0.7a.001

CREATE TABLE `lpm_issue_member_info` (
  `instanceId` bigint(18) NOT NULL COMMENT 'идентификатор задачи',
  `userId` bigint(18) NOT NULL COMMENT 'идентификатор пользователя',
  `sp` decimal(10,1) NOT NULL COMMENT 'Значения SP за задачу',
  PRIMARY KEY (`instanceId`,`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='информация об участнике задачи';

-- 0.7a.002

ALTER TABLE `lpm_scrum_snapshot`
ADD `issue_members_sp` text COLLATE 'utf8_general_ci' NOT NULL COMMENT 'Количество SP по участникам' AFTER `issue_sp`;

-- 0.7a.003

-- 2019-08-28 17:55:00
ALTER TABLE `lpm_projects` ADD `defaultIssueMemberId` INT NOT NULL COMMENT 'Исполнитель умолчанию';

-- 0.8a.001

-- 2019-09-30 17:20:00
CREATE TABLE `lpm_users_log` (
  `id` int(11) NOT NULL COMMENT 'Идентификатор записи' AUTO_INCREMENT PRIMARY KEY,
  `userId` int(11) NOT NULL COMMENT 'Идентификатор пользователя',
  `date` datetime NOT NULL COMMENT 'Дата совершения действия',
  `type` mediumint(5) NOT NULL COMMENT 'Тип действия',
  `entityId` int(11) NOT NULL COMMENT 'Идентификатор сущности, с которой было произведено действие'
) COMMENT='Лог действий пользователя';

-- v.0.8a.004

-- 2019-09-30 17:46:00
ALTER TABLE `lpm_users_log`
ADD `comment` varchar(255) NOT NULL COMMENT 'Комментарий действия';

-- v.0.8a.005

ALTER TABLE `lpm_recovery_emails`
CHANGE `id` `id` bigint(19) unsigned NOT NULL AUTO_INCREMENT FIRST;

-- v.0.8a.008

ALTER TABLE `lpm_users`
ADD `gitlabToken` varchar(255) COLLATE 'utf8_general_ci' NOT NULL COMMENT 'Gitlab токен';

-- 0.9.1

ALTER TABLE `lpm_scrum_sticker`
ADD `added` datetime NOT NULL COMMENT 'дата добавления' AFTER `state`;

ALTER TABLE `lpm_scrum_snapshot`
ADD `added` datetime NOT NULL COMMENT 'Дата добавления стикера на доску' AFTER `sid`;

# Удаляем все неактиные стикеры, они теперь не нужны
DELETE FROM `lpm_scrum_sticker` WHERE `state` NOT IN (1, 2, 3, 4);

ALTER TABLE `lpm_scrum_snapshot_list`
ADD `started` datetime NOT NULL COMMENT 'Дата начала спринта' AFTER `creatorId`;

# Update snapshot started dates 
CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_snapshot_dates_0_9_6` AS (SELECT `pid`, `idInProject`, `created` FROM `lpm_scrum_snapshot_list`);
UPDATE `lpm_scrum_snapshot_list` `a` SET `started` = (SELECT `created` FROM `tmp_snapshot_dates_0_9_6` `b` WHERE `b`.`pid` = `a`.`pid` AND `b`.`idInProject` < `a`.`idInProject` ORDER BY `b`.`idInProject` DESC LIMIT 1);
DROP TEMPORARY TABLE `tmp_snapshot_dates_0_9_6`;

# Update sticker added dates by previous snapshot
CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_last_snapshot_date_0_9_6` AS (SELECT `i`.`projectId`, MAX(`a`.`created`) AS `date` FROM `lpm_scrum_snapshot_list` `a`, `lpm_issues` `i`, `lpm_scrum_sticker` `s` WHERE `i`.`id` = `s`.`issueId` AND `a`.`pid` = `i`.`projectId` GROUP BY `i`.`projectId`);
UPDATE `lpm_scrum_sticker` `s` SET `added` = (SELECT `d`.`date` FROM `tmp_last_snapshot_date_0_9_6` `d`, `lpm_issues` `i` WHERE `d`.`projectId` = `i`.`projectId` AND `i`.`id` = `s`.`issueId`) WHERE `added` = '0000-00-00 00:00:00';
UPDATE `lpm_scrum_sticker` `s` SET `added` = NOW()  WHERE `added` = '0000-00-00 00:00:00';
DROP TEMPORARY TABLE `tmp_last_snapshot_date_0_9_6`;

-- 0.9.6

CREATE TABLE `lpm_issue_mr` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `mrId` int(20) NOT NULL COMMENT 'ID MR',
  `issueId` bigint(20) NOT NULL COMMENT 'ID задачи',
  `state` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Состояние MR',
  PRIMARY KEY (`id`),
  KEY `mrId_state` (`mrId`,`state`),
  KEY `mrId` (`mrId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='GitLab MR от исполнителей по задачам.';

-- 0.9.7

ALTER TABLE `lpm_comments`
CHANGE `text` `text` text COLLATE 'utf8mb4_general_ci' NOT NULL COMMENT 'текст' AFTER `date`;

ALTER TABLE `lpm_issues`
CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `parentId`,
CHANGE `desc` `desc` text COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `hours`;

ALTER TABLE `lpm_issue_labels`
CHANGE `label` `label` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL COMMENT 'Текст метки' AFTER `projectId`;

ALTER TABLE `lpm_options`
CHANGE `value` `value` text COLLATE 'utf8mb4_general_ci' NOT NULL COMMENT 'её значение' AFTER `option`;

ALTER TABLE `lpm_projects`
CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `uid`,
CHANGE `desc` `desc` text COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `name`;

ALTER TABLE `lpm_scrum_snapshot`
CHANGE `issue_name` `issue_name` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL COMMENT 'Название задачи' AFTER `issue_pid`;

-- 0.9.17

DROP TABLE IF EXISTS `lpm_fixed_instance`;
CREATE TABLE `lpm_fixed_instance` (
  `userId` int(10) unsigned NOT NULL COMMENT 'Идентификатор пользователя',
  `instanceType` tinyint(3) unsigned NOT NULL COMMENT 'Тип инстанции',
  `instanceId` int(10) unsigned NOT NULL COMMENT 'Идентификатор инстанции',
  `dateFixed` datetime NOT NULL COMMENT 'Дата фиксации инстанции',
  PRIMARY KEY (`userId`,`instanceType`,`instanceId`,`dateFixed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица фиксации инстанции';

-- v0.9.21

ALTER TABLE `lpm_projects`
ADD `gitlabGroupId` int(11) NOT NULL COMMENT 'Идентификатор группы проектов на GitLab';

-- v0.9.22

CREATE TABLE `lpm_issue_branch` (
  `issueId` bigint(20) NOT NULL COMMENT 'ID задачи',
  `repositoryId` int(20) NOT NULL COMMENT 'ID репозитория',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Название ветки',
  `date` datetime NOT NULL COMMENT 'Дата записи',
  PRIMARY KEY (`issueId`, `repositoryId`, `name`),
  KEY `repositoryId_name` (`repositoryId`,`name`),
  KEY `issueId` (`issueId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Ветка задачи на GitLab репозитории.';

-- v0.9.28

ALTER TABLE `lpm_issue_branch`
ADD `lastСommit` varchar(255) NOT NULL COMMENT 'ID последнего коммита',
ADD `mergedInDevelop` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Отметка о влитии в develop' AFTER `lastСommit`;

ALTER TABLE `lpm_issue_branch`
ADD INDEX `issueId_mergedInDevelop` (`issueId`, `mergedInDevelop`);

ALTER TABLE `lpm_issue_branch`
ADD INDEX `repositoryId_lastСommit` (`repositoryId`, `lastСommit`);

ALTER TABLE `lpm_users`
ADD `gitlabId` bigint(20) NOT NULL COMMENT 'идентификатор на GitLab';

ALTER TABLE `lpm_users`
ADD INDEX `gitlabId` (`gitlabId`);

-- надо сбросить токены, чтобы записались заново, уже с gitlabId
UPDATE `lpm_users` SET `gitlabToken` = '';

-- v0.9.30

ALTER TABLE `lpm_issue_mr`
ADD `repositoryId` int(20) NOT NULL COMMENT 'ID репозитория',
ADD `branch` varchar(255) COLLATE 'utf8_unicode_ci' NOT NULL COMMENT 'Название ветки' AFTER `repositoryId`;

-- v0.9.33

ALTER TABLE `lpm_issues`
CHANGE `completeDate` `completeDate` datetime NULL AFTER `startDate`;

-- v0.10.3

ALTER TABLE `lpm_issue_branch`
ADD `userId` bigint(19) NOT NULL COMMENT 'Идентификатор пользователя';

-- v0.10.5

ALTER TABLE `lpm_members`
CHANGE `instanceType` `instanceType` smallint(2) NOT NULL AFTER `userId`;

-- v0.10.7

CREATE TABLE `lpm_instance_targets` (
    `instanceType` int(20) DEFAULT NULL COMMENT 'Тип экземпляра',
    `instanceId` int(20) DEFAULT NULL COMMENT 'ID экземпляра',
    `content` longtext COLLATE 'utf8_unicode_ci' DEFAULT NULL COMMENT 'Содержимое целей',
    PRIMARY KEY (`instanceType`,`instanceId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Цели указанной сущности.';

-- v0.10.8

CREATE TABLE `lpm_issue_linked` (
  `issueId` int(11) NOT NULL COMMENT 'ID основной задачи',
  `linkedIssueId` int(11) NOT NULL COMMENT 'ID связанной задачи',
  `created` datetime NOT NULL COMMENT 'Дата создания связи',
  PRIMARY KEY (`issueId`,`linkedIssueId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Связанные задачи';

ALTER TABLE `lpm_issues` DROP `parentId`;

-- v0.10.9

ALTER TABLE `lpm_members`
ADD `extraId` bigint(19) NOT NULL DEFAULT '0';

ALTER TABLE `lpm_members`
ADD PRIMARY KEY `userId_instanceType_instanceId_extraId` (`userId`, `instanceType`, `instanceId`, `extraId`),
DROP INDEX `PRIMARY`;

-- v0.10.10

ALTER TABLE `lpm_instance_targets`
CHANGE `instanceType` `instanceType` int NOT NULL COMMENT 'Тип экземпляра' FIRST,
CHANGE `instanceId` `instanceId` int NOT NULL COMMENT 'ID экземпляра' AFTER `instanceType`;

-- cyrillic letter in the name
ALTER TABLE `lpm_issue_branch`
CHANGE `lastСommit` `lastCommit` varchar(255) COLLATE 'utf8_unicode_ci' NOT NULL COMMENT 'ID последнего коммита' AFTER `date`;

ALTER TABLE `lpm_issue_branch`
ADD INDEX `repositoryId_lastCommit` (`repositoryId`, `lastCommit`),
DROP INDEX `repositoryId_lastСommit`;

-- v0.12.1

DROP TABLE IF EXISTS `lpm_workers`;
DROP TABLE IF EXISTS `lpm_work_study`;

-- v0.13.4

CREATE TABLE `lpm_issue_comment` (
  `commentId` bigint NOT NULL COMMENT 'ID комментария',
  `type` varchar(255) NOT NULL COMMENT 'Тип комментария',
  `data` varchar(255) NOT NULL COMMENT 'Данные комментария',
  PRIMARY KEY (`commentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Данные комментария к задаче';

-- v0.13.5

ALTER TABLE `lpm_members`
ADD INDEX `instanceType_instanceId` (`instanceType`, `instanceId`);

ALTER TABLE `lpm_issues`
ADD INDEX `projectId` (`projectId`);

-- 0.13.10

ALTER TABLE `lpm_issue_branch`
ADD `initialCommit` varchar(255) COLLATE 'utf8_unicode_ci' NOT NULL COMMENT 'ID изначального коммита' AFTER `date`;

-- 0.13.27

--NEXT
