# -*- coding: utf-8 -*-
import os

from ciutil.deploy.ssh_worker import SshWorker, SshInfo
from ciutil.utils.util import compress_dir
from pathlib import Path
from shutil import copy2, copytree


class Deployer(SshWorker):
    def __init__(self, ssh_info: SshInfo, remote_app_dir):
        super().__init__(ssh_info)
        self.remote_app_dir = remote_app_dir
        # self.src_dir = Path(src_dir).resolve()
        # self.deploy_dir = Path(self.src_dir).resolve()

    def deploy(self):
        cmd = f'cd {self.remote_app_dir} && pwd && git checkout master && git pull'
        






