# -*- coding: utf-8 -*-

from ciutil.deploy.ssh_worker import SshWorker, SshInfo
from ciutil.utils.util import random_str, generate_date_stamp


class PMDeployer(SshWorker):
    """Деплой приложения."""

    # файлы и директории которые не заливаются на боевой.
    exclude_data = ['.dev', 'ai', 'ci',  'docs', '.gitignore', '_private', 'AGENTS.md', 'CLAUDE.md', 'README.md', 'lpm-config.inc.template.php', '.git']

    def __init__(self, ssh_info: SshInfo, upload_path, remote_app_path, git_branch, git_user, git_passwd, git_project,
                 run_migrations=True, migrate_cmd='php lpm-cli/migrate.php apply'):
        super().__init__(ssh_info)
        self.git_project = git_project
        self.git_passwd = git_passwd
        self.git_user = git_user
        self.git_branch = git_branch
        self.upload_path = upload_path
        self.remote_app_path = remote_app_path
        self.run_migrations = run_migrations
        self.migrate_cmd = migrate_cmd

    def deploy(self):
        self.connect()

        # создаем временную директорию.
        tmp_dir = f'{self.upload_path}/{random_str(5)}_{generate_date_stamp()}'
        cmd = f'mkdir {tmp_dir}'
        self.ssh_cmd(cmd)

        # приводим адрес к виду http://user:password@git.innim.ru:1414/innim/LightningPM.git
        s = '://'
        ind = self.git_project.find(s) + len(s)
        uri = self.git_project[:ind] + f'{self.git_user}:{self.git_passwd}@' + self.git_project[ind:] + '.git'

        # сливаем git-репозиторий.
        git_clone = f'git clone {uri} ./'
        cmd = f'cd {tmp_dir} && {git_clone} && git checkout {self.git_branch} && git pull'
        self.ssh_cmd(cmd)

        # удаляем "лишние" файлы и директории.
        exclude = ' '.join(self.exclude_data)
        cmd = f'cd {tmp_dir} && rm -rf {exclude}'
        self.ssh_cmd(cmd)

        # копируем файлы в директорию с приложением.
        cmd = f'cp -r {tmp_dir}/. {self.remote_app_path}'
        self.ssh_cmd(cmd)

        # удаляем временную директорию.
        cmd = f'rm -rf {tmp_dir}'
        self.ssh_cmd(cmd)

        # применяем миграции схемы БД.
        # Выполняется после копирования кода: миграции лежат в самом приложении,
        # а новый код рассчитан на новую схему. Ненулевой код возврата прервёт
        # деплой с оповещением — схема при этом может остаться изменённой
        # частично, см. docs/db-migrations.md.
        if self.run_migrations:
            cmd = f'cd {self.remote_app_path} && {self.migrate_cmd}'
            self.ssh_cmd(cmd)
        else:
            print('Migrations are disabled (DEPLOY_RUN_MIGRATIONS), skipping.')

