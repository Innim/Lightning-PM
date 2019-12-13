# -*- coding: utf-8 -*-
import os

from ciutil.deploy.ssh_worker import SshWorker, SshInfo
from ciutil.utils.util import compress_dir, random_str, generate_date_stamp
from pathlib import Path
from shutil import copy2, copytree


class PMDeployer(SshWorker):

    # project_data = ['lpm_core', 'lpm_files', 'lpm_libs', 'lpm_scripts', 'lpm_themes', '.htaccess']
    exclude_data = ['_dp', '_private', 'CHANGELOG.md', 'README.md', 'lpm-config.inc.template.php', '.git']

    def __init__(self, ssh_info: SshInfo, upload_path, remote_app_path, git_branch, git_user, git_passwd, git_project):
        super().__init__(ssh_info)
        self.git_project = git_project
        self.git_passwd = git_passwd
        self.git_user = git_user
        self.git_branch = git_branch
        self.upload_path = upload_path
        self.remote_app_path = remote_app_path

    def deploy(self):
        self.connect()

        tmp_dir = f'{self.upload_path}/{random_str(5)}_{generate_date_stamp()}'
        cmd = f'mkdir {tmp_dir}'
        self.ssh_cmd(cmd)

        # cmd = f'cd {tmp_dir} && git checkout {self.git_branch} && git pull'
        # self.ssh_cmd(cmd)
        s = '://'
        ind = self.git_project.find(s) + len(s)
        uri = self.git_project[:ind] + f'{self.git_user}:{self.git_passwd}@' + self.git_project[ind:] + '.git'

        # git_clone = f'git clone {self.git_user}:{self.git_passwd}@{self.git_project}.git'
        git_clone = f'git clone {uri} ./'
        cmd = f'cd {tmp_dir} && {git_clone} && git checkout {self.git_branch} && git pull'
        self.ssh_cmd(cmd)

        exclude = ' '.join(self.exclude_data)
        cmd = f'cd {tmp_dir} && rm -rf {exclude}'
        self.ssh_cmd(cmd)

        cmd = f'cp -r {tmp_dir}/. {self.remote_app_path}'
        self.ssh_cmd(cmd)

        cmd = f'rm -rf {tmp_dir}'
        self.ssh_cmd(cmd)




