# -*- coding: utf-8 -*-
from ciutil.core.worker import CIWorker
from ciutil.deploy.ssh_worker import SshInfo
from src.deploy import Deployer
from src.pm_info import PMInfo


class PMWorker(CIWorker):
    def __init__(self):
        info = PMInfo()
        super().__init__(info=info)

    def deploy(self):
        print('deploying...')
        ssh_info = SshInfo(host=self.info.deploy_host)
        deployer = Deployer()
