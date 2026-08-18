# -*- coding: utf-8 -*-
from ciutil.core.worker import CIWorker
from ciutil.deploy.ssh_worker import SshInfo
from src.pm_deployer import PMDeployer, MigrationsResult
from src.pm_info import PMInfo


class PMWorker(CIWorker):
    def __init__(self):
        info = PMInfo()
        super().__init__(info=info)
        # Итог применения миграций последнего деплоя, заполняется в deploy().
        self.migrations = MigrationsResult(ran=False, output='')

    def deploy(self):
        print('deploying...')
        ssh_info = SshInfo(host=self.info.deploy_host,
                           port=self.info.deploy_port,
                           user=self.info.deploy_user,
                           password=self.info.deploy_password)

        deployer = PMDeployer(ssh_info=ssh_info,
                              upload_path=self.info.deploy_upload_path,
                              remote_app_path=self.info.deploy_app_path,
                              git_branch=self.info.git_branch,
                              git_user=self.info.deploy_git_user,
                              git_passwd=self.info.deploy_git_passwd,
                              git_project=self.info.project_url,
                              run_migrations=self.info.deploy_run_migrations,
                              migrate_cmd=self.info.deploy_migrate_cmd)

        deployer.deploy()
        self.migrations = deployer.migrations
