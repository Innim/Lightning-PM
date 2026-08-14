# -*- coding: utf-8 -*-
from ciutil.core.info import Info
from os import getenv


class PMInfo(Info):
    def __init__(self):
        super().__init__()

    deploy_host = getenv('DEPLOY_HOST', '')

    deploy_port = getenv('DEPLOY_PORT', '')

    deploy_user = getenv('DEPLOY_USER', '')

    deploy_password = getenv('DEPLOY_PASSWORD', '')

    deploy_upload_path = getenv('DEPLOY_UPLOAD_PATH', '')

    deploy_app_path = getenv('DEPLOY_APP_PATH', '')

    deploy_git_user = getenv('DEPLOY_GIT_USER', '')

    deploy_git_passwd = getenv('DEPLOY_GIT_PASSWD', '')

    # Применять ли миграции схемы БД после выкладки кода.
    # Выключается значением 0/false/no.
    deploy_run_migrations = getenv('DEPLOY_RUN_MIGRATIONS', '1').strip().lower() \
        not in ('0', 'false', 'no', '')

    # Команда применения миграций, выполняется в каталоге приложения.
    # Если приложение работает в контейнере, а php на хосте нет — укажите здесь
    # вызов через docker exec, например:
    # docker exec <container> php /var/www/html/lpm-cli/migrate.php apply
    deploy_migrate_cmd = getenv('DEPLOY_MIGRATE_CMD', 'php lpm-cli/migrate.php apply')

    # @property
    # def deploy_password(self):
    #     pass_env = self.get_env('DEPLOY_PASSWORD_ENV')
    #     return self.get_env(pass_env)
