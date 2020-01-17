# -*- coding: utf-8 -*-
import paramiko
import time

from ciutil.core.errors import CIError
from ciutil import bash


class CIDeployer:

    def __init__(self):
        self.ssh = None
        self.sftp = None

        self.host = ''
        self.port = 22
        self.username = ''
        self.password = ''
        self.upload_path = ''

    def upload(self):
        """Загрузжает приложения на хост."""
        pass

    def before_deploy(self):
        """Выполянет действия перед деплоем."""
        pass

    def deploy(self):
        """Выполняет развертывание приложения на хосте."""
        pass

    def after_deploy(self):
        """Выполняет действия после деплоя."""
        pass

    def run(self):
        """Запускает загрузку и развертываение приложения на хосте."""
        # if not self.ssh or not self.sftp:
        #     print('Ошибка выполнения команды! Необходимо установить ssh-соединение')
        #     raise WorkerError(title='SSH ERROR', text='Необходимо установить ssh-соединение.')

        self.upload()
        self.before_deploy()
        self.deploy()
        self.after_deploy()
        self.end()

    def start(self):
        self.connect()
        self.run()

    def connect(self):
        """Создает ssh, sftp соединение"""
        print(f'ssh: connecting to {self.username}@{self.host}:{self.port}')
        self.ssh = paramiko.SSHClient()
        self.ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy)
        self.ssh.connect(
            hostname=self.host,
            port=self.port,
            username=self.username,
            password=self.password
        )

        self.sftp = self.ssh.open_sftp()
        print('ssh: connected')

    def end(self):
        if self.ssh:
            self.ssh.close()
            print('ssh: connect closed')

    def cmd(self, cmd, mute=False, encoding='utf-8'):
        """Выполняет команду на локальном хосте.

        :return: Возвращает кортеж с результатами работы команды:
         result[0] - stdout,
         result[1] - stderr,
         result[2] - exitcode
        """
        return bash(cmd, mute, encoding)

    def ssh_cmd(self, cmd: str, mute: bool = False, fatal: bool = True):
        """Выполняет команду на удаленном хосте через ssh-соединение."""
        if not mute:
            print("#" * 40)
            print(">>> ssh-cmd: {}".format(cmd))
            print("#" * 40)

        stdin, stdout, stderr = self.ssh.exec_command(cmd)
        while not stdout.channel.exit_status_ready() and \
                not stdout.channel.recv_ready():
            time.sleep(1)

        out_str = ''.join(stdout.readlines())
        err_str = ''.join(stderr.readlines())

        exit_status = stdout.channel.recv_exit_status()

        if not mute:
            print("----> status: {}".format(exit_status))
            print("----> output: \n{}".format(out_str))
            print("----> error: \n{}".format(err_str))

        if fatal and exit_status != 0:
            raise CIError(title='SSH ERROR', text=f'Ошибка при выполнении команды:\n{cmd}\nstderr:\n{err_str}')

        return exit_status, out_str, err_str

    def sftp_put(self, local_path: str, remote_path: str):
        try:
            print(f'sftp: uploading "{local_path}" to "{remote_path}"')
            self.sftp.put(local_path, remote_path)
            print('sftp: file uploaded')
        except FileNotFoundError:
            msg = f'Ошибка выгрузки данных!\nНеверный локальный "{local_path}" или удаленный путь "{remote_path}".'
            raise CIError(title='SFTP error', text=msg)
