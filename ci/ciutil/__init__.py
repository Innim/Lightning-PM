# -*- coding: utf-8 -*-
from ciutil import command_line

__all__ = ["command_line", "bash", "git_util", "util", "notifier.py"]


def name():
    return "Вспомогательный функционал CI"


def description():
    return "Модуль содержит команды для реализации gitlab-ci на runner"


def version():
    return "version 0.1"


def bash(cmd, mute=False, encoding='utf-8') -> command_line.ShellOut:
    """
    :return: Возвращает кортеж с результатами работы команды:
     result[0] - stdout,
     result[1] - stderr,
     result[2] - exitcode
    """
    return command_line.bash(cmd, mute=mute, encoding=encoding)


def wbash(cmd, mute=False, encoding='utf-8'):
    """
    :return: Возвращает кортеж с результатами работы команды:
     result[0] - stdout,
     result[1] - stderr,
     result[2] - exitcode
    """
    return command_line.bash(cmd, mute=mute, encoding=encoding)
