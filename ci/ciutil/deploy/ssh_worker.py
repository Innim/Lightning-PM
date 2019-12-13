# -*- coding: utf-8 -*-

import datetime

from fabric import Config, Connection
from typing import Union
from collections import namedtuple

# параметры для ssh-подключения.
SshInfo = namedtuple('SshInfo', 'host port user password')


class SshWorker:
    """Обертка для использования библиотеки Fabric."""
    def __init__(self, ssh_info: SshInfo):
        self.ssh_info = ssh_info
        self.fab: Union[Connection, None] = None

    def connect(self):
        self.fab = Connection(host=self.ssh_info.host,
                              port=self.ssh_info.port,
                              user=self.ssh_info.user,
                              connect_kwargs={'password': self.ssh_info.password})

    def ssh_cmd(self, cmd: str, warn=False):
        print(f'[{datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")}] >> {cmd}')
        result = self.fab.run(command=cmd, warn=warn)
        return result

    def put(self, local_src, remote_dst):
        result = self.fab.put(local_src, remote_dst)
        print(f'Uploaded "{result.local}" to "{result.remote}"')

