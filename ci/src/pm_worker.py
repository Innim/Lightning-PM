# -*- coding: utf-8 -*-
from ciutil.core.worker import CIWorker
from src.pm_info import PMInfo


class PMWorker(CIWorker):
    def __init__(self):
        info = PMInfo()
        super().__init__(info=info)

    def deploy(self):
        print('deploying...')
