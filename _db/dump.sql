-- Adminer 4.1.0 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `lpm_comments`;
CREATE TABLE `lpm_comments` (
  `id` bigint(19) NOT NULL AUTO_INCREMENT COMMENT 'идентификатор комментария',
  `instanceType` tinyint(1) NOT NULL COMMENT 'тип инстанции, к которой оставляется коммент',
  `instanceId` bigint(19) NOT NULL COMMENT 'идентификатор инстанции, к которой оставляется коммент',
  `authorId` bigint(19) NOT NULL COMMENT 'идентификатор автора комментария',
  `date` datetime NOT NULL COMMENT 'дата',
  `text` text NOT NULL COMMENT 'текст',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'клмментарий удалён',
  PRIMARY KEY (`id`),
  KEY `instanceType` (`instanceType`,`instanceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='таблица комментариев';


DROP TABLE IF EXISTS `lpm_images`;
CREATE TABLE `lpm_images` (
  `imgId` bigint(18) NOT NULL AUTO_INCREMENT COMMENT 'идентификатор изображения',
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'url (полный или относительно корня сайта)',
  `itemType` tinyint(2) NOT NULL COMMENT 'тип элемента, для которого добавлено изображение',
  `itemId` bigint(18) NOT NULL COMMENT 'идентификатор элемента',
  `userId` bigint(18) NOT NULL COMMENT 'идентификатор пользователя',
  `origName` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'оригинальное имя изображения',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'имя изображения',
  `desc` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'описание фото',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'дата добавления',
  PRIMARY KEY (`imgId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='загруженные пользователем изображения';


DROP TABLE IF EXISTS `lpm_issues`;
CREATE TABLE `lpm_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projectId` int(11) NOT NULL,
  `parentId` int(11) NOT NULL DEFAULT '0' COMMENT 'идентификатор родительской задачи',
  `name` varchar(255) NOT NULL,
  `hours` int(11) NOT NULL,
  `desc` text NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `authorId` bigint(19) NOT NULL,
  `createDate` datetime NOT NULL,
  `startDate` datetime NOT NULL,
  `completeDate` datetime NOT NULL,
  `completedDate` datetime DEFAULT NULL COMMENT 'дата реального завершения',
  `priority` tinyint(2) NOT NULL DEFAULT '49' COMMENT 'приоритет задачи',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `lpm_issue_counters`;
CREATE TABLE `lpm_issue_counters` (
  `issueId` bigint(20) NOT NULL COMMENT 'идентификатор задачи',
  `commentsCount` int(8) NOT NULL DEFAULT '0' COMMENT 'количество комментариев',
  `imgsCount` int(11) NOT NULL DEFAULT '0' COMMENT 'количество картинок, прикрепленных к задаче',
  PRIMARY KEY (`issueId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='счетчики для задачи';


DROP TABLE IF EXISTS `lpm_members`;
CREATE TABLE `lpm_members` (
  `userId` bigint(10) NOT NULL,
  `instanceType` tinyint(1) NOT NULL,
  `instanceId` bigint(19) NOT NULL,
  PRIMARY KEY (`userId`,`instanceType`,`instanceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `lpm_options`;
CREATE TABLE `lpm_options` (
  `option` varchar(20) NOT NULL COMMENT 'опция',
  `value` text NOT NULL COMMENT 'её значение',
  PRIMARY KEY (`option`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='таблица настроек';

INSERT INTO `lpm_options` (`option`, `value`) VALUES
('cookieExpire',	'30'),
('currentTheme',	'default'),
('title',	'FlaPS Group'),
('subtitle',	'Менеджер проектов'),
('logo',	'imgs/lightning-logo.png'),
('fromEmail',	'donotreply@flaps.ru'),
('fromName',	'FlaPS LLC PM'),
('emailSubscript',	'Это письмо отправлено автоматически, не отвечайте на него. \r\nВы можете отключить отправку уведомлений в настройках профиля');

DROP TABLE IF EXISTS `lpm_projects`;
CREATE TABLE `lpm_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `desc` text NOT NULL,
  `date` datetime NOT NULL,
  `lastUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'время последнего обновления проекта',
  `issuesCount` int(9) NOT NULL DEFAULT '0' COMMENT 'количество созданных задач',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `lpm_tags`;
CREATE TABLE `lpm_tags` (
  `tagId` bigint(18) NOT NULL COMMENT 'идентификатор тега',
  `itemId` bigint(18) NOT NULL COMMENT 'идентификатор инстанции',
  PRIMARY KEY (`tagId`,`itemId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='теги, привязанные к инстанциям';


DROP TABLE IF EXISTS `lpm_tags_list`;
CREATE TABLE `lpm_tags_list` (
  `tagId` bigint(18) NOT NULL AUTO_INCREMENT COMMENT 'идентификатор тега',
  `itemType` tinyint(2) NOT NULL COMMENT 'тип элементов, к которым применим тег',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'название тега',
  PRIMARY KEY (`tagId`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='теги';


DROP TABLE IF EXISTS `lpm_users`;
CREATE TABLE `lpm_users` (
  `userId` bigint(19) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `cookieHash` varchar(32) NOT NULL DEFAULT '' COMMENT 'хеш для переавторизации по кукам',
  `firstName` varchar(128) NOT NULL,
  `lastName` varchar(128) NOT NULL,
  `nick` varchar(255) NOT NULL,
  `lastVisit` datetime NOT NULL,
  `regDate` datetime NOT NULL,
  `role` tinyint(1) NOT NULL DEFAULT '0',
  `secret` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'скрытый пользователь',
  PRIMARY KEY (`userId`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `lpm_users` (`userId`, `email`, `pass`, `cookieHash`, `firstName`, `lastName`, `nick`, `lastVisit`, `regDate`, `role`, `secret`) VALUES
(14,	'test@test.com',	'$1$V7A9iC1B$3JkJzDQLyGIAdSWCjGwm8/',	'937f8fc04ad180df25d9feabbc741b05',	'Test',	'Test',	'test',	'2014-10-10 11:35:10',	'2014-10-10 11:35:00',	0,	0);

DROP TABLE IF EXISTS `lpm_users_pref`;
CREATE TABLE `lpm_users_pref` (
  `userId` bigint(20) NOT NULL COMMENT 'идентификатор пользователя',
  `seAddIssue` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оповещать на email о добавлении новой задачи',
  `seEditIssue` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'оповещать на email об изменении задачи',
  `seIssueState` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оповещать на email об изменения состояния задачи',
  `seIssueComment` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оставлен комментарий к задаче',
  PRIMARY KEY (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='настройки пользователя';

INSERT INTO `lpm_users_pref` (`userId`, `seAddIssue`, `seEditIssue`, `seIssueState`, `seIssueComment`) VALUES
(14,	1,	0,	1,	1);

DROP TABLE IF EXISTS `lpm_workers`;
CREATE TABLE `lpm_workers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `userId` bigint(18) NOT NULL COMMENT 'идентификатор пользователя',
  `hours` int(3) NOT NULL DEFAULT '0',
  `comingTime` time NOT NULL DEFAULT '00:00:00',
  `lunchBreak` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userId` (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `lpm_work_study`;
CREATE TABLE `lpm_work_study` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `workerId` bigint(20) NOT NULL DEFAULT '0',
  `date` date NOT NULL DEFAULT '0000-00-00',
  `comingTime` time NOT NULL DEFAULT '00:00:00',
  `leavingTime` time NOT NULL DEFAULT '00:00:00',
  `late` tinyint(1) NOT NULL DEFAULT '0',
  `lunchBreak` tinyint(1) NOT NULL DEFAULT '1',
  `mustHours` int(2) NOT NULL DEFAULT '0',
  `mustComingTime` time NOT NULL DEFAULT '00:00:00',
  `hoursAway` float NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `workerId` (`workerId`,`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `lpm_cookie`  (
  `cookieHash` varchar(32) NOT NULL ,
  `userAgent` varchar(64) NOT NULL COMMENT 'информация о браузере юзера',
  `userId` bigint(18) NOT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Данные авторизации по куки';

ALTER TABLE `lpm_users` DROP COLUMN `cookieHash`;