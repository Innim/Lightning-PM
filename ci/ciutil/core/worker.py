# -*- encoding: utf-8 -*-
import os

import ciutil
from ciutil.slack.notifier import format_message
from ciutil.core.info import Info
from ciutil.core.errors import CIError
from ciutil.command_line import ShellOut
from ciutil import git_util


class CIWorker:
    """Класс предоставляющий функционал для CI/CD"""

    def __init__(self, info: Info):
        self.info = info

    def goto_work_dir(self):
        """В качестве текущей директории устанавливаем рабочую директорию CI"""
        os.chdir(self.info.work_dir)

    def do_cmd(self, cmd, fail_words: list = (), fail_msg: str = ''):
        """Выполняет комманду cmd и
        проверяем вывод stdout и stderr на наличие fail-фраз,
        если фраза найдена генерируется исключение WorkerError"""

        out = self.bash(cmd)

        if self.is_failed_cmd(out[0] + out[1], fail_words):
            fail_msg = fail_msg or "Fail command: {cmd}".format(cmd=cmd)
            raise CIError(title="Error", text=fail_msg)

    def bash(self, cmd) -> ShellOut:
        """Выполнеяет команду

        :return: Возвращает кортеж с результатами работы команды:
         result[0] - stdout,
         result[1] - stderr,
         result[2] - exitcode
        """
        return ciutil.bash(cmd)

    def is_failed_cmd(self, output: str, fail_words: list):
        """Проверяет наличие fail-фраз в выводе команды"""
        result = False

        for fail in fail_words:
            result = result or (fail and fail in output)
            if result:
                break

        return result

    def init_worker(self):
        """Инициализация worker"""
        # получает данные git-репозитория.
        self.fill_git_info()
        # устанавливаем текущую директорию как корневую.
        self.info.this_root_dir()
        # переходим в рабочую директорию.
        self.goto_work_dir()

    def fill_git_info(self):
        """Получает и устанавливает данные git-репозитория."""
        # получаем сообщение коммита
        self.info.commit_message = git_util.get_commit_message()
        # получаем дату коммита.
        self.info.commit_date = git_util.get_commit_date()
