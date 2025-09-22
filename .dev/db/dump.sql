-- Adminer 4.8.1 MySQL 8.0.43 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `lpm_badges`;
CREATE TABLE `lpm_badges` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `type` varchar(255) NOT NULL COMMENT 'Тип бэйджа',
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Лейбл бейджа',
  `gitlabProjectId` int DEFAULT NULL COMMENT 'Id проекта на GitLab',
  `gitlabRef` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Ветка, тег или коммит в репозитории',
  `comment` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT 'Комментарий к бэйджу',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Таблица бэйджей';


DROP TABLE IF EXISTS `lpm_comments`;
CREATE TABLE `lpm_comments` (
  `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'идентификатор комментария',
  `instanceType` tinyint(1) NOT NULL COMMENT 'тип инстанции, к которой оставляется коммент',
  `instanceId` bigint NOT NULL COMMENT 'идентификатор инстанции, к которой оставляется коммент',
  `authorId` bigint NOT NULL COMMENT 'идентификатор автора комментария',
  `date` datetime NOT NULL COMMENT 'дата',
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'текст',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'комментарий удалён',
  PRIMARY KEY (`id`),
  KEY `instanceType` (`instanceType`,`instanceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COMMENT='таблица комментариев';


DROP TABLE IF EXISTS `lpm_file_links`;
CREATE TABLE `lpm_file_links` (
  `linkId` bigint NOT NULL AUTO_INCREMENT COMMENT 'идентификатор связи',
  `fileId` bigint NOT NULL COMMENT 'идентификатор файла',
  `itemType` tinyint NOT NULL COMMENT 'тип сущности',
  `itemId` bigint NOT NULL COMMENT 'идентификатор сущности',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'дата добавления связи',
  PRIMARY KEY (`linkId`),
  UNIQUE KEY `file_instance` (`fileId`,`itemType`,`itemId`),
  KEY `instance_lookup` (`itemType`,`itemId`),
  CONSTRAINT `lpm_file_links_file_fk` FOREIGN KEY (`fileId`) REFERENCES `lpm_files` (`fileId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='привязки файлов к сущностям';


DROP TABLE IF EXISTS `lpm_files`;
CREATE TABLE `lpm_files` (
  `fileId` bigint NOT NULL AUTO_INCREMENT COMMENT 'идентификатор файла',
  `uid` char(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'уникальный идентификатор файла',
  `userId` bigint NOT NULL COMMENT 'идентификатор пользователя, загрузившего файл',
  `path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'путь к файлу относительно директории загрузок',
  `origName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'оригинальное имя файла',
  `mimeType` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'MIME тип файла',
  `size` bigint NOT NULL COMMENT 'размер файла в байтах',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'дата добавления',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'файл удалён',
  PRIMARY KEY (`fileId`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='загруженные файлы';


DROP TABLE IF EXISTS `lpm_fixed_instance`;
CREATE TABLE `lpm_fixed_instance` (
  `userId` int unsigned NOT NULL COMMENT 'Идентификатор пользователя',
  `instanceType` tinyint unsigned NOT NULL COMMENT 'Тип инстанции',
  `instanceId` int unsigned NOT NULL COMMENT 'Идентификатор инстанции',
  `dateFixed` datetime NOT NULL COMMENT 'Дата фиксации инстанции',
  PRIMARY KEY (`userId`,`instanceType`,`instanceId`,`dateFixed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Таблица фиксации инстанции';


DROP TABLE IF EXISTS `lpm_images`;
CREATE TABLE `lpm_images` (
  `imgId` bigint NOT NULL AUTO_INCREMENT COMMENT 'идентификатор изображения',
  `url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'url (полный или относительно корня сайта)',
  `itemType` tinyint NOT NULL COMMENT 'тип элемента, для которого добавлено изображение',
  `itemId` bigint NOT NULL COMMENT 'идентификатор элемента',
  `userId` bigint NOT NULL COMMENT 'идентификатор пользователя',
  `origName` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'оригинальное имя изображения',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'имя изображения',
  `desc` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'описание фото',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'дата добавления',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`imgId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='загруженные пользователем изображения';


DROP TABLE IF EXISTS `lpm_instance_targets`;
CREATE TABLE `lpm_instance_targets` (
  `instanceType` int NOT NULL COMMENT 'Тип экземпляра',
  `instanceId` int NOT NULL COMMENT 'ID экземпляра',
  `content` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Содержимое целей',
  PRIMARY KEY (`instanceType`,`instanceId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Цели указанной сущности.';


DROP TABLE IF EXISTS `lpm_issue_branch`;
CREATE TABLE `lpm_issue_branch` (
  `issueId` bigint NOT NULL COMMENT 'ID задачи',
  `repositoryId` int NOT NULL COMMENT 'ID репозитория',
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Название ветки',
  `date` datetime NOT NULL COMMENT 'Дата записи',
  `initialCommit` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'ID изначального коммита',
  `lastCommit` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'ID последнего коммита',
  `mergedInDevelop` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Отметка о влитии в develop',
  `userId` bigint NOT NULL COMMENT 'Идентификатор пользователя',
  PRIMARY KEY (`issueId`,`repositoryId`,`name`),
  KEY `repositoryId_name` (`repositoryId`,`name`),
  KEY `issueId` (`issueId`),
  KEY `issueId_mergedInDevelop` (`issueId`,`mergedInDevelop`),
  KEY `repositoryId_lastCommit` (`repositoryId`,`lastCommit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Ветка задачи на GitLab репозитории';


DROP TABLE IF EXISTS `lpm_issue_comment`;
CREATE TABLE `lpm_issue_comment` (
  `commentId` bigint NOT NULL COMMENT 'ID комментария',
  `type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Тип комментария',
  `data` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Данные комментария',
  PRIMARY KEY (`commentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Данные комментария к задаче';


DROP TABLE IF EXISTS `lpm_issue_counters`;
CREATE TABLE `lpm_issue_counters` (
  `issueId` bigint NOT NULL COMMENT 'идентификатор задачи',
  `commentsCount` int NOT NULL DEFAULT '0' COMMENT 'количество комментариев',
  PRIMARY KEY (`issueId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COMMENT='счетчики для задачи';


DROP TABLE IF EXISTS `lpm_issue_labels`;
CREATE TABLE `lpm_issue_labels` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
  `projectId` int NOT NULL DEFAULT '0' COMMENT 'Проект (0 если метка общая)',
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Текст метки',
  `countUses` int unsigned NOT NULL DEFAULT '0' COMMENT 'Количество использований',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `projectId` (`projectId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Метки для задач';


DROP TABLE IF EXISTS `lpm_issue_linked`;
CREATE TABLE `lpm_issue_linked` (
  `issueId` int NOT NULL COMMENT 'ID основной задачи',
  `linkedIssueId` int NOT NULL COMMENT 'ID связанной задачи',
  `created` datetime NOT NULL COMMENT 'Дата создания связи',
  PRIMARY KEY (`issueId`,`linkedIssueId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Связанные задачи';


DROP TABLE IF EXISTS `lpm_issue_member_info`;
CREATE TABLE `lpm_issue_member_info` (
  `instanceId` bigint NOT NULL COMMENT 'идентификатор задачи',
  `userId` bigint NOT NULL COMMENT 'идентификатор пользователя',
  `sp` decimal(10,1) NOT NULL COMMENT 'Значения SP за задачу',
  PRIMARY KEY (`instanceId`,`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='информация об участнике задачи';


DROP TABLE IF EXISTS `lpm_issue_mr`;
CREATE TABLE `lpm_issue_mr` (
  `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `mrId` int NOT NULL COMMENT 'ID MR',
  `issueId` bigint NOT NULL COMMENT 'ID задачи',
  `state` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Состояние MR',
  `repositoryId` int NOT NULL COMMENT 'ID репозитория',
  `branch` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Название ветки',
  PRIMARY KEY (`id`),
  KEY `mrId_state` (`mrId`,`state`),
  KEY `mrId` (`mrId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='GitLab MR от исполнителей по задачам.';


DROP TABLE IF EXISTS `lpm_issues`;
CREATE TABLE `lpm_issues` (
  `id` int NOT NULL AUTO_INCREMENT,
  `projectId` int NOT NULL,
  `idInProject` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `hours` decimal(10,1) NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `authorId` bigint NOT NULL,
  `createDate` datetime NOT NULL,
  `modifiedDate` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'дата последнего изменения записи',
  `startDate` datetime NOT NULL,
  `completeDate` datetime DEFAULT NULL,
  `completedDate` datetime DEFAULT NULL COMMENT 'дата реального завершения',
  `priority` tinyint NOT NULL DEFAULT '49' COMMENT 'приоритет задачи',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `revision` varchar(48) NOT NULL COMMENT 'ревизия задачи',
  PRIMARY KEY (`id`),
  KEY `projectId` (`projectId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `lpm_members`;
CREATE TABLE `lpm_members` (
  `userId` bigint NOT NULL,
  `instanceType` smallint NOT NULL,
  `instanceId` bigint NOT NULL,
  `extraId` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`userId`,`instanceType`,`instanceId`,`extraId`),
  KEY `instanceType_instanceId` (`instanceType`,`instanceId`),
  KEY `instanceId` (`instanceId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `lpm_options`;
CREATE TABLE `lpm_options` (
  `option` varchar(20) NOT NULL COMMENT 'опция',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'её значение',
  PRIMARY KEY (`option`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COMMENT='таблица настроек';

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
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` varchar(255) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `date` datetime NOT NULL,
  `lastUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'время последнего обновления проекта',
  `issuesCount` int NOT NULL DEFAULT '0' COMMENT 'количество созданных задач',
  `scrum` tinyint NOT NULL DEFAULT '0' COMMENT 'Проект использует Scrum',
  `isArchive` tinyint(1) NOT NULL DEFAULT '0',
  `slackNotifyChannel` varchar(255) NOT NULL COMMENT 'имя канала для оповещений в Slack',
  `masterId` bigint NOT NULL COMMENT 'идентификатор пользователя, являющегося мастером в проекте',
  `defaultIssueMemberId` int NOT NULL COMMENT 'Исполнитель умолчанию',
  `gitlabGroupId` int NOT NULL COMMENT 'Идентификатор группы проектов на GitLab',
  `gitlabProjectIds` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'ID связанных проектов GitLab',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `lpm_recovery_emails`;
CREATE TABLE `lpm_recovery_emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `userId` bigint NOT NULL,
  `recoveryKey` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `expDate` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci


DROP TABLE IF EXISTS `lpm_scrum_snapshot`;
CREATE TABLE `lpm_scrum_snapshot` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `sid` int NOT NULL COMMENT 'Идентификатор snapshot-а',
  `added` datetime NOT NULL COMMENT 'Дата добавления стикера на доску',
  `issue_uid` int NOT NULL COMMENT 'Глобавльный идентификатор задачи',
  `issue_pid` int NOT NULL COMMENT 'Идентификатор задачи в проекте',
  `issue_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Название задачи',
  `issue_state` tinyint NOT NULL COMMENT 'Состояние задачи',
  `issue_sp` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT 'Количество SP',
  `issue_members_sp` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT 'Количество SP по участникам',
  `issue_priority` tinyint NOT NULL COMMENT 'Приоритет задачи',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `lpm_scrum_snapshot_list`;
CREATE TABLE `lpm_scrum_snapshot_list` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор snapshot-а',
  `idInProject` int NOT NULL COMMENT 'Порядковый номер снепшота по проекту',
  `pid` int NOT NULL COMMENT 'Идентификатор проекта',
  `creatorId` bigint NOT NULL COMMENT 'Идентификатор создателя snapshot-а',
  `started` datetime NOT NULL COMMENT 'Дата начала спринта',
  `created` datetime NOT NULL COMMENT 'Время создания snapshot-а',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;


DROP TABLE IF EXISTS `lpm_scrum_sticker`;
CREATE TABLE `lpm_scrum_sticker` (
  `issueId` bigint NOT NULL COMMENT 'идентификатор задачи',
  `state` tinyint NOT NULL COMMENT 'состояние стикера',
  `added` datetime NOT NULL COMMENT 'дата добавления',
  PRIMARY KEY (`issueId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='стикер на scrum доске';


DROP TABLE IF EXISTS `lpm_user_auth`;
CREATE TABLE `lpm_user_auth` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `cookieHash` varchar(32) NOT NULL,
  `userAgent` varchar(255) NOT NULL COMMENT 'информация о браузере юзера',
  `userId` bigint NOT NULL COMMENT 'индентификатор пользователя',
  `hasCreated` datetime NOT NULL COMMENT 'дата создания записи',
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Данные авторизации по куки';


DROP TABLE IF EXISTS `lpm_user_locks`;
CREATE TABLE `lpm_user_locks` (
  `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `userId` bigint NOT NULL COMMENT 'Идентификатор пользователя',
  `date` datetime NOT NULL COMMENT 'Дата получения блокировки',
  `expired` datetime NOT NULL COMMENT 'Дата истечения блокировки',
  `instanceType` tinyint NOT NULL COMMENT 'Тип заблокированной сущности',
  `instanceId` bigint NOT NULL COMMENT 'Id заблокированной сущности',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Блокировка удалена',
  PRIMARY KEY (`id`),
  KEY `instanceType_instanceId_deleted` (`instanceType`,`instanceId`,`deleted`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Таблица блокировок разных сущностей пользователями';


DROP TABLE IF EXISTS `lpm_users`;
CREATE TABLE `lpm_users` (
  `userId` bigint NOT NULL AUTO_INCREMENT,
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
  `gitlabId` bigint NOT NULL COMMENT 'идентификатор на GitLab',
  PRIMARY KEY (`userId`),
  UNIQUE KEY `email` (`email`),
  KEY `gitlabId` (`gitlabId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `lpm_users_log`;
CREATE TABLE `lpm_users_log` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
  `userId` int NOT NULL COMMENT 'Идентификатор пользователя',
  `date` datetime NOT NULL COMMENT 'Дата совершения действия',
  `type` mediumint NOT NULL COMMENT 'Тип действия',
  `entityId` int NOT NULL COMMENT 'Идентификатор сущности, с которой было произведено действие',
  `comment` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Комментарий действия',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Лог действий пользователя';


DROP TABLE IF EXISTS `lpm_users_pref`;
CREATE TABLE `lpm_users_pref` (
  `userId` bigint NOT NULL COMMENT 'идентификатор пользователя',
  `seAddIssue` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оповещать на email о добавлении новой задачи',
  `seEditIssue` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'оповещать на email об изменении задачи',
  `seIssueState` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оповещать на email об изменения состояния задачи',
  `seIssueComment` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'оставлен комментарий к задаче',
  `seAddIssueForPM` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'оповещать на email о добавлении новой задачи для PM',
  `seEditIssueForPM` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'оповещать на email об изменении задачи для PM',
  `seIssueStateForPM` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'оповещать на email об изменения состояния задачи для PM',
  `seIssueCommentForPM` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'оставлен комментарий к задаче для PM',
  PRIMARY KEY (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COMMENT='настройки пользователя';


-- 2025-09-22 07:27:43
