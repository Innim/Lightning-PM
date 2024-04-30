-- Adminer 4.7.1 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `lpm_comments`;
CREATE TABLE `lpm_comments` (
  `id` bigint(19) NOT NULL AUTO_INCREMENT COMMENT 'идентификатор комментария',
  `instanceType` tinyint(1) NOT NULL COMMENT 'тип инстанции, к которой оставляется коммент',
  `instanceId` bigint(19) NOT NULL COMMENT 'идентификатор инстанции, к которой оставляется коммент',
  `authorId` bigint(19) NOT NULL COMMENT 'идентификатор автора комментария',
  `date` datetime NOT NULL COMMENT 'дата',
  `text` text CHARACTER SET utf8mb4 NOT NULL COMMENT 'текст',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'клмментарий удалён',
  PRIMARY KEY (`id`),
  KEY `instanceType` (`instanceType`,`instanceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='таблица комментариев';


DROP TABLE IF EXISTS `lpm_fixed_instance`;
CREATE TABLE `lpm_fixed_instance` (
  `userId` int(10) unsigned NOT NULL COMMENT 'Идентификатор пользователя',
  `instanceType` tinyint(3) unsigned NOT NULL COMMENT 'Тип инстанции',
  `instanceId` int(10) unsigned NOT NULL COMMENT 'Идентификатор инстанции',
  `dateFixed` datetime NOT NULL COMMENT 'Дата фиксации инстанции',
  PRIMARY KEY (`userId`,`instanceType`,`instanceId`,`dateFixed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица фиксации инстанции';


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
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`imgId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='загруженные пользователем изображения';


DROP TABLE IF EXISTS `lpm_instance_targets`;
CREATE TABLE `lpm_instance_targets` (
  `instanceType` int NOT NULL COMMENT 'Тип экземпляра',
  `instanceId` int NOT NULL COMMENT 'ID экземпляра',
  `content` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci COMMENT 'Содержимое целей',
  PRIMARY KEY (`instanceType`,`instanceId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci COMMENT='Цели указанной сущности.';


DROP TABLE IF EXISTS `lpm_issues`;
CREATE TABLE `lpm_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projectId` int(11) NOT NULL,
  `idInProject` int(11) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `hours` decimal(10,1) NOT NULL,
  `desc` text CHARACTER SET utf8mb4 NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `authorId` bigint(19) NOT NULL,
  `createDate` datetime NOT NULL,
  `startDate` datetime NOT NULL,
  `completeDate` datetime DEFAULT NULL,
  `completedDate` datetime DEFAULT NULL COMMENT 'дата реального завершения',
  `priority` tinyint(2) NOT NULL DEFAULT '49' COMMENT 'приоритет задачи',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `projectId` (`projectId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `lpm_issue_branch`;
CREATE TABLE `lpm_issue_branch` (
  `issueId` bigint NOT NULL COMMENT 'ID задачи',
  `repositoryId` int NOT NULL COMMENT 'ID репозитория',
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Название ветки',
  `date` datetime NOT NULL COMMENT 'Дата записи',
  `lastCommit` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'ID последнего коммита',
  `mergedInDevelop` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Отметка о влитии в develop',
  `userId` bigint NOT NULL COMMENT 'Идентификатор пользователя',
  PRIMARY KEY (`issueId`,`repositoryId`,`name`),
  KEY `repositoryId_name` (`repositoryId`,`name`),
  KEY `issueId` (`issueId`),
  KEY `issueId_mergedInDevelop` (`issueId`,`mergedInDevelop`),
  KEY `repositoryId_lastCommit` (`repositoryId`,`lastCommit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci COMMENT='Ветка задачи на GitLab репозитории.';

DROP TABLE IF EXISTS `lpm_issue_comment`;
CREATE TABLE `lpm_issue_comment` (
  `commentId` bigint NOT NULL COMMENT 'ID комментария',
  `type` varchar(255) NOT NULL COMMENT 'Тип комментария',
  `data` varchar(255) NOT NULL COMMENT 'Данные комментария',
  PRIMARY KEY (`commentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Данные комментария к задаче';

DROP TABLE IF EXISTS `lpm_issue_counters`;
CREATE TABLE `lpm_issue_counters` (
  `issueId` bigint(20) NOT NULL COMMENT 'идентификатор задачи',
  `commentsCount` int(8) NOT NULL DEFAULT '0' COMMENT 'количество комментариев',
  `imgsCount` int(11) NOT NULL DEFAULT '0' COMMENT 'количество картинок, прикрепленных к задаче',
  PRIMARY KEY (`issueId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='счетчики для задачи';


DROP TABLE IF EXISTS `lpm_issue_labels`;
CREATE TABLE `lpm_issue_labels` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
  `projectId` int(11) NOT NULL DEFAULT '0' COMMENT 'Проект (0 если метка общая)',
  `label` varchar(255) CHARACTER SET utf8mb4 NOT NULL COMMENT 'Текст метки',
  `countUses` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Количество использований',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `projectId` (`projectId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Метки для задач';

DROP TABLE IF EXISTS `lpm_issue_linked`;
CREATE TABLE `lpm_issue_linked` (
  `issueId` int(11) NOT NULL COMMENT 'ID основной задачи',
  `linkedIssueId` int(11) NOT NULL COMMENT 'ID связанной задачи',
  `created` datetime NOT NULL COMMENT 'Дата создания связи',
  PRIMARY KEY (`issueId`,`linkedIssueId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Связанные задачи';

DROP TABLE IF EXISTS `lpm_issue_member_info`;
CREATE TABLE `lpm_issue_member_info` (
  `instanceId` bigint(18) NOT NULL COMMENT 'идентификатор задачи',
  `userId` bigint(18) NOT NULL COMMENT 'идентификатор пользователя',
  `sp` decimal(10,1) NOT NULL COMMENT 'Значения SP за задачу',
  PRIMARY KEY (`instanceId`,`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='информация об участнике задачи';


DROP TABLE IF EXISTS `lpm_issue_mr`;
CREATE TABLE `lpm_issue_mr` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `mrId` int(20) NOT NULL COMMENT 'ID MR',
  `issueId` bigint(20) NOT NULL COMMENT 'ID задачи',
  `state` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Состояние MR',
  `repositoryId` int(20) NOT NULL COMMENT 'ID репозитория',
  `branch` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Название ветки',
  PRIMARY KEY (`id`),
  KEY `mrId_state` (`mrId`,`state`),
  KEY `mrId` (`mrId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='GitLab MR от исполнителей по задачам.';


DROP TABLE IF EXISTS `lpm_members`;
CREATE TABLE `lpm_members` (
  `userId` bigint(10) NOT NULL,
  `instanceType` smallint(2) NOT NULL,
  `instanceId` bigint(19) NOT NULL,
  `extraId` bigint(19) NOT NULL DEFAULT '0',
  PRIMARY KEY (`userId`,`instanceType`,`instanceId`,`extraId`),
  KEY `instanceType_instanceId` (`instanceType`,`instanceId`),
  KEY `instanceId` (`instanceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `lpm_options`;
CREATE TABLE `lpm_options` (
  `option` varchar(20) NOT NULL COMMENT 'опция',
  `value` text CHARACTER SET utf8mb4 NOT NULL COMMENT 'её значение',
  PRIMARY KEY (`option`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='таблица настроек';

INSERT INTO `lpm_options` (`option`, `value`) VALUES
('cookieExpire',  '30'),
('currentTheme',  'default'),
('title', 'Innim LLC'),
('subtitle',  'Менеджер проектов'),
('logo',  'imgs/lightning-logo.png'),
('fromEmail', 'donotreply@innim.ru'),
('fromName',  'Innim LLC PM'),
('emailSubscript',  'Это письмо отправлено автоматически, не отвечайте на него. \r\nВы можете отключить отправку уведомлений в настройках профиля');

DROP TABLE IF EXISTS `lpm_projects`;
CREATE TABLE `lpm_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(255) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `desc` text CHARACTER SET utf8mb4 NOT NULL,
  `date` datetime NOT NULL,
  `lastUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'время последнего обновления проекта',
  `issuesCount` int(9) NOT NULL DEFAULT '0' COMMENT 'количество созданных задач',
  `scrum` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Проект использует Scrum',
  `isArchive` tinyint(1) NOT NULL DEFAULT '0',
  `slackNotifyChannel` varchar(255) NOT NULL COMMENT 'имя канала для оповещений в Slack',
  `masterId` bigint(19) NOT NULL COMMENT 'идентификатор пользователя, являющегося мастером в проекте',
  `defaultIssueMemberId` int(11) NOT NULL COMMENT 'Исполнитель умолчанию',
  `gitlabGroupId` int(11) NOT NULL COMMENT 'Идентификатор группы проектов на GitLab',
  `gitlabProjectIds` varchar(255) NOT NULL COMMENT 'ID связанных проектов GitLab',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `lpm_recovery_emails`;
CREATE TABLE `lpm_recovery_emails` (
  `id` bigint(19) unsigned NOT NULL AUTO_INCREMENT,
  `userId` bigint(19) NOT NULL,
  `recoveryKey` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `expDate` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `lpm_scrum_snapshot`;
CREATE TABLE `lpm_scrum_snapshot` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `sid` int(11) NOT NULL COMMENT 'Идентификатор snapshot-а',
  `added` datetime NOT NULL COMMENT 'Дата добавления стикера на доску',
  `issue_uid` int(11) NOT NULL COMMENT 'Глобавльный идентификатор задачи',
  `issue_pid` int(11) NOT NULL COMMENT 'Идентификатор задачи в проекте',
  `issue_name` varchar(255) CHARACTER SET utf8mb4 NOT NULL COMMENT 'Название задачи',
  `issue_state` tinyint(2) NOT NULL COMMENT 'Состояние задачи',
  `issue_sp` varchar(255) CHARACTER SET utf8 NOT NULL COMMENT 'Количество SP',
  `issue_members_sp` text CHARACTER SET utf8 NOT NULL COMMENT 'Количество SP по участникам',
  `issue_priority` tinyint(2) NOT NULL COMMENT 'Приоритет задачи',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `lpm_scrum_snapshot_list`;
CREATE TABLE `lpm_scrum_snapshot_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор snapshot-а',
  `idInProject` int(11) NOT NULL COMMENT 'Порядковый номер снепшота по проекту',
  `pid` int(11) NOT NULL COMMENT 'Идентификатор проекта',
  `creatorId` bigint(19) NOT NULL COMMENT 'Идентификатор создателя snapshot-а',
  `started` datetime NOT NULL COMMENT 'Дата начала спринта',
  `created` datetime NOT NULL COMMENT 'Время создания snapshot-а',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `lpm_scrum_sticker`;
CREATE TABLE `lpm_scrum_sticker` (
  `issueId` bigint(18) NOT NULL COMMENT 'идентификатор задачи',
  `state` tinyint(2) NOT NULL COMMENT 'состояние стикера',
  `added` datetime NOT NULL COMMENT 'дата добавления',
  PRIMARY KEY (`issueId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='стикер на scrum доске';


DROP TABLE IF EXISTS `lpm_users`;
CREATE TABLE `lpm_users` (
  `userId` bigint(19) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `firstName` varchar(128) NOT NULL,
  `lastName` varchar(128) NOT NULL,
  `nick` varchar(255) NOT NULL,
  `lastVisit` datetime NOT NULL,
  `regDate` datetime NOT NULL,
  `role` tinyint(1) NOT NULL DEFAULT '0',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `secret` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'скрытый пользователь',
  `slackName` varchar(255) NOT NULL COMMENT 'имя в Slack',
  `gitlabToken` varchar(255) NOT NULL COMMENT 'Gitlab токен',
  `gitlabId` bigint(20) NOT NULL COMMENT 'идентификатор на GitLab',
  PRIMARY KEY (`userId`),
  UNIQUE KEY `email` (`email`),
  KEY `gitlabId` (`gitlabId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `lpm_users_log`;
CREATE TABLE `lpm_users_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `userId` int(11) NOT NULL COMMENT 'Идентификатор пользователя',
  `date` datetime NOT NULL COMMENT 'Дата совершения действия',
  `type` mediumint(5) NOT NULL COMMENT 'Тип действия',
  `entityId` int(11) NOT NULL COMMENT 'Идентификатор сущности, с которой было произведено действие',
  `comment` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Комментарий действия',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Лог действий пользователя';


DROP TABLE IF EXISTS `lpm_users_pref`;
CREATE TABLE `lpm_users_pref` (
  `userId` bigint(20) NOT NULL COMMENT 'идентификатор пользователя',
  `seAddIssue` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оповещать на email о добавлении новой задачи',
  `seEditIssue` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'оповещать на email об изменении задачи',
  `seIssueState` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оповещать на email об изменения состояния задачи',
  `seIssueComment` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оставлен комментарий к задаче',
  PRIMARY KEY (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='настройки пользователя';


DROP TABLE IF EXISTS `lpm_user_auth`;
CREATE TABLE `lpm_user_auth` (
  `id` bigint(18) NOT NULL AUTO_INCREMENT,
  `cookieHash` varchar(32) NOT NULL,
  `userAgent` varchar(255) NOT NULL COMMENT 'информация о браузере юзера',
  `userId` bigint(18) NOT NULL COMMENT 'индентификатор пользователя',
  `hasCreated` datetime NOT NULL COMMENT 'дата создания записи',
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Данные авторизации по куки';

