# -*- coding: utf-8 -*-
import os

from ciutil.deploy.ssh_worker import SshWorker, SshInfo
from ciutil.utils.util import compress_dir
from pathlib import Path
from shutil import copy2, copytree


class PMDeployer(SshWorker):
    def __init__(self, ssh_info: SshInfo, upload_path, remote_app_path):
        super().__init__(ssh_info)
        self.upload_dir = upload_path
        self.remote_app_dir = remote_app_path
        # self.src_dir = Path(src_dir).resolve()
        # self.deploy_dir = Path(self.src_dir).resolve()

    def deploy(self):
        self.connect()
        cmd = f'cd {self.upload_dir} && pwd && git checkout master && git pull'
        self.ssh_cmd(cmd)
        
