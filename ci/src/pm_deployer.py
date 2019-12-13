# -*- coding: utf-8 -*-
import os

from ciutil.deploy.ssh_worker import SshWorker, SshInfo
from ciutil.utils.util import compress_dir
from pathlib import Path
from shutil import copy2, copytree


class PMDeployer(SshWorker):

    project_data = ['lpm_core', 'lpm_files', 'lpm_libs', 'lpm_scripts', 'lpm_themes', '.htaccess']
    exclude_data = ['_dp', '_private', 'CHANGELOG.md', 'README.md', 'lpm-config.inc.template.php', '.git']

    def __init__(self, ssh_info: SshInfo, upload_path, remote_app_path, git_branch):
        super().__init__(ssh_info)
        self.git_branch = git_branch
        self.upload_path = upload_path
        self.remote_app_path = remote_app_path

    def deploy(self):
        self.connect()
        cmd = f'cd {self.upload_path} && git checkout {self.git_branch} && git pull'
        self.ssh_cmd(cmd)

        exclude = ' '.join(self.exclude_data)
        cmd = f'cd {self.upload_path} && rm -rf {exclude}'
        self.ssh_cmd(cmd)

        cmd = f'cp -r {self.upload_path}/. {self.remote_app_path}'
        self.ssh_cmd(cmd)




