# -*- coding: utf-8 -*-

import subprocess
import sys
from datetime import datetime
from collections import namedtuple


# Результат выполнения команды в shell.
ShellOut = namedtuple('ShellOut', ['out', 'err', 'code'])


def bash(cmd, mute=False, encoding='utf-8') -> ShellOut:
    """Выполненяет комманду.

    :param cmd: Комманда.
    :param mute: Будет ли расспечатан резуьтат работы комманты, True - нет.
    :param encoding: Кодировака текста для вывода резульататов работы команды.
    :type mute: bool
    :type cmd: str
    :type encoding: str

    :return: Возвращает кортеж с результатами работы команды:
     result[0] - stdout,
     result[1] - stderr,
     result[2] - exitcode
    :rtype: tuple
    """
    print("#" * 40)
    now = datetime.strftime(datetime.now(), '%H:%M:%S')
    print("[{0}] run cmd: {1}".format(now, cmd))
    print("#" * 40)
    p = subprocess.Popen(cmd,
                         shell=True,
                         stdout=subprocess.PIPE,
                         stderr=subprocess.PIPE)
    #p.wait()

    raw_result = p.communicate()

    try:
        result = (
            raw_result[0].decode(encoding),
            raw_result[1].decode(encoding),
            p.returncode
        )

    except:
        print("Ошибка декодирования utf-8")
        #result = raw_result
        result = (
            raw_result[0],
            raw_result[1],
            p.returncode
        )
    now = datetime.strftime(datetime.now(), '%H:%M:%S')
    print("[{}] >>> cmd done.".format(now))
    # print("#" * 40)
    # print(">>> cmd: {}".format(cmd))
    # print("#" * 40)
    if not mute:
        print("----> exit code: {}".format(result[2]))
        try:
            # print("----> output: {}".format(result[0], encoding='cp1251'))
            print("----> output: {}".format(result[0], encoding='utf-8'))
        except Exception as err:
            print("\nОшибка вывода данных в консоль")
            print(err)
        try:
            print("----> error: {}".format(result[1]))
        except Exception as err:
            print("\nОшибка вывода данных в консоль")
            print(err)

    print("-" * 40 + "\n")

    return ShellOut(*result)
