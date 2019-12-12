# -*- coding: utf-8 -*-
import zipfile
import os
import time
from string import ascii_letters
from random import choice

import paramiko
import datetime
import fnmatch


def random_str(len=10):
    """Рэндомная строка указанной длины"""
    return ''.join([choice(ascii_letters) for i in range(len)])


def generate_date_stamp():
    """Создает строку дата-время 2017-11-25_24-45-59"""
    date = datetime.datetime.now().\
        replace(microsecond=0).isoformat(sep='_').replace(':', '-')
    return "{dt}".format(dt=date)


def compress_dir(dir, zip_name, cut_path=None, exclude_list=None):
    """Архевирует директорию.

    :param exclude_list: Список файлов которые не будут включены в архив. ['*.log', '/project/build/123.tmp']
    :param dir: Директория, которая будет заархивирована.
    :type dir: str

    :param zip_name: Имя архива.
    :type zip_name: str

    :param cut_path: Часть полного пути, которая будет отброшена от
        имен файлов помещенных в архив.
    :type cut_path: str

    :return: ZipFile.
    :rtype: object

    """

    def check_file(file_path: str, pattern_list: list) -> bool:
        for pattern in pattern_list:
            if fnmatch.fnmatch(file_path, pattern):
                return True
        return False

    if exclude_list is None:
        exclude_list = []

    print(f'compressing dir {dir} to archive {zip_name}')
    zip = zipfile.ZipFile(zip_name, 'w')

    for p, dirs, files in os.walk(dir):
        for f in files:
            path = os.path.join(p, f)
            if not check_file(path, exclude_list):
                print(f' add file: {path}')
                arcname = os.path.join(p.replace(cut_path, ''), f) if cut_path else None
                zip.write(path, arcname=arcname)
            else:
                print(f' exclude file: {path}')

    zip.close()
    print('compressed')
    return zip


def unzip_file(zip_path, file_name, extract_path):
    """Извлекает файл из архива."""
    zip = zipfile.ZipFile(zip_path)
    zip.extract(file_name, extract_path)
    zip.close()


def create_ssh_connect(host: str, port: int, user: str, password: str):
    """Создает ssh/sftp соединение"""

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy)
    ssh.connect(
        hostname=host,
        port=port,
        username=user,
        password=password
    )

    sftp = ssh.open_sftp()

    return ssh, sftp


def ssh_exec(ssh, cmd: str):
    print("#" * 40)
    print(">>> cmd: {}".format(cmd))
    print("#" * 40)

    stdin, stdout, stderr = ssh.exec_command(cmd)

    while not stdout.channel.exit_status_ready() and not stdout.channel.recv_ready():
        time.sleep(1)

    print("----> status: {}".format(stdout.channel.recv_exit_status()))
    out_str = ''.join(stdout.readlines())
    print("----> output: \n{}".format(out_str))
    err_str = ''.join(stderr.readlines())
    print("----> error: \n{}".format(err_str))
    

def count_files_and_dirs(path):
    """Возвращает количество файлов и директорий в указанной директории.

    :param path: Путь к директории для которой будет производиться подсчет.
    :return: Кортеж (количество файлов, количество директорий)
    :rtype: tuple
    """
    if not os.path.isdir(path):
        raise Exception('Указанный путь "{}", '
                        'должен быть существующей директорией'.format(path))
    dir_count = 0
    file_count = 0
    for file in os.listdir(path):
        p = os.path.join(path, file)
        if os.path.isdir(p):
            dir_count += 1
        elif os.path.isfile(p):
            file_count += 1
    return file_count, dir_count


def create_file(file_name: str, txt: str):
    """Создает и записывает данные в текстовый файл."""
    with open(file_name, 'w') as fp:
        fp.write(txt)

