# -*- coding: utf-8 -*-
import re

from ciutil.slack.notifier import GitlabSlackNotifier, GitlabMessageInfo
from src.pm_deployer import MigrationsResult
from src.pm_info import PMInfo


class PMNotifier(GitlabSlackNotifier):

    # Файл-источник версии приложения (define('VERSION', '...')).
    VERSION_FILE = 'lpm-core/version.inc.php'
    # Раздел «Что нового» в приложении (страница ChangelogPage, uid = changelog);
    # якорь версии совпадает со slug из ChangelogParser — 'v' + версия.
    CHANGELOG_PATH = 'changelog'
    # Раздел админки со состоянием и применением миграций схемы БД (StatusPage).
    ADMIN_STATUS_PATH = 'status'
    # Базовая CLI-команда применения миграций — для инструкции пользователю.
    # Намеренно не берём DEPLOY_MIGRATE_CMD: та адаптирована под запуск из CI
    # (например, через docker exec) и как ручная команда не годится.
    MIGRATE_CLI_CMD = 'php lpm-cli/migrate.php apply'

    def __init__(self, slack_bot_token, info: PMInfo):
        super().__init__(slack_bot_token)
        self.pm_info = info
        self.set_pm_info()

    def deploy_message(self, deploy_type: str, migrations: MigrationsResult = None):
        title = "DEPLOY for {deploy_type} is SUCCESS".format(deploy_type=deploy_type.upper())
        brief_text = f'Деплой для "{deploy_type.upper()}"-окружения успешно завершен\n\n' \
                     f'{self.info.env_url}\n\n' \
                     f'{self._version_line()}' \
                     f'{self._migrations_line(migrations)}'
        text = ''

        self.success_message(title=title, brief_text=brief_text, text=text)

    def _version_line(self) -> str:
        """Строка с версией приложения и ссылкой на раздел «Что нового»."""
        version = self._read_app_version()
        if not version:
            return ''

        url = self._changelog_url(version)
        if url:
            return f'Версия: <{url}|{version}>\n\n'
        return f'Версия: {version}\n\n'

    def _migrations_line(self, migrations: MigrationsResult) -> str:
        """Сообщение о состоянии миграций схемы БД по итогу деплоя."""
        # Автоприменение выполнялось — показываем сам вывод команды: он уже
        # человекочитаемый («Применена …» либо «Нет миграций для применения»).
        if migrations is not None and migrations.ran:
            output = (migrations.output or '').strip()
            if output:
                return 'Миграции схемы БД:\n```\n' + output + '\n```'
            return 'Миграции схемы БД применены автоматически.'

        # Автоприменение выключено — миграции нужно применить вручную.
        admin_url = self._admin_status_url()
        admin = f' или в админке — {admin_url}' if admin_url else ''
        return '*Примените миграции схемы БД вручную* — в CLI:\n' \
               f'`{self.MIGRATE_CLI_CMD}`{admin}'

    def _read_app_version(self) -> str:
        try:
            with open(self.VERSION_FILE, encoding='utf-8') as f:
                content = f.read()
        except OSError:
            return ''

        match = re.search(r"define\(\s*['\"]VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]", content)
        return match.group(1) if match else ''

    def _changelog_url(self, version: str) -> str:
        """Ссылка на раздел версии в «Что нового» задеплоенного приложения."""
        base = (self.info.env_url or '').rstrip('/')
        if not base:
            return ''
        return f'{base}/{self.CHANGELOG_PATH}#v{version}'

    def _admin_status_url(self) -> str:
        base = (self.info.env_url or '').rstrip('/')
        return f'{base}/{self.ADMIN_STATUS_PATH}' if base else ''

    def set_pm_info(self):
        info = GitlabMessageInfo(slack_bot_token=self.pm_info.slack_bot_token,
                                 slack_channel=self.pm_info.slack_channel,
                                 slack_username=self.pm_info.slack_username,
                                 slack_icon=self.pm_info.slack_icon_url,
                                 project_url=self.pm_info.project_url,
                                 project_path_slug=self.pm_info.project_path_slug,
                                 commit_sha=self.pm_info.commit_sha,
                                 commit_message=self.pm_info.commit_message,
                                 gitlab_user_email=self.pm_info.gitlab_user_email,
                                 git_branch=self.pm_info.git_branch,
                                 pipeline_id=self.pm_info.pipeline_id,
                                 job_id=self.pm_info.job_id,
                                 commit_date=self.pm_info.commit_date,
                                 env_name=self.pm_info.env_name,
                                 env_url=self.pm_info.env_url)
        self.set_info(info)
