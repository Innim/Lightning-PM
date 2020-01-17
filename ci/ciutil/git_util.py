# -*- coding: utf-8 -*-

from ciutil import bash
from datetime import datetime
from typing import List


def set_config(user='', email='', push_url='', origin_url=''):
    """Задает данные для git config
        если параметр не задан или равент пустой строке, то не будет изменен
    """

    if user:
        bash('git config user.name {name}'.format(name=user))
    if email:
        bash('git config user.email {email}'.format(email=email))
    if push_url:
        bash('git config remote.origin.pushurl {url}'.format(url=push_url))
    # if origin_url:
    #     bash('git config set-url origin {url}'.format(url=origin_url))


def change_branch(new_branch):
    """Смена активной ветки, если ветки нет - будет создана"""
    return bash('git checkout -B {branch}'.format(branch=new_branch))


def pull(remote_branch):
    """Забирает изменения с удаленной ветки в текущую."""
    return bash(f'git pull origin {remote_branch}')


def merge(from_branch):
    """Выполняет слияние указанной ветки с текущей."""
    return bash(f'git merge {from_branch}')


def push(remote_branch):
    """Отправляет изменнения на удаленный репозиторий."""
    return bash(f'git push origin {remote_branch}')


def push_tags(tag: str = ''):
    """Пушит указанную метку, если метка не передана пушатся все метки."""
    if not tag:
        tag = '--tags'
    return bash(f'git push origin {tag}')


def merge_from_to_and_push(from_branch, to_branch):
    """Мерджит from_branch в to_branch.
    :return: Возвращает stdout, stderr, exitcode последней команды.
    """
    change_branch(new_branch=to_branch)
    pull(remote_branch=to_branch)
    merge(from_branch=from_branch)
    return push(remote_branch=to_branch)


def tag(tag_name: str, message: str = ''):
    """Создает метку."""
    message = f'{message}' if message else 'tag created by CI/CD'
    return bash(f'git tag -a "{tag_name}" -m "{message}"')


def push_all_change(branch, message):
    """Делает коммит последних изменений и отправляет данные на удаленный репозиторий """
    bash('git status')
    bash('git add .')
    bash(f'git commit -a -m "{message}"')
    return bash(f'git push origin {branch}')


def push_change(branch, message, files: List[str]):
    """Делает коммит изменений указанные файлов."""
    files = ' '.join(files)
    bash('git status')
    bash(f'git add {files}')
    bash(f'git commit -m "{message}"')
    return bash(f'git push origin {branch}')


def get_commit_message():
    """Возвращает сообщение коммита"""
    git_log = bash('git log -1 --pretty=%B', encoding='utf-8')
    return git_log[0].strip('\n')


def get_commit_date():
    """Возвращает дату коммита."""
    git_log = bash('git log -1 --pretty=%ct')
    t = int(git_log[0]) + 3*60*60  # корректеровка вермени под MSK
    return datetime.utcfromtimestamp(t).strftime('%Y-%m-%d %H:%M:%S') + ' (MSK)'


def fetch():
    bash("git fetch")


def reset_change(files: List['str']):
    """Сбрасывает изменения в указанных файлах."""
    files = ' '.join(files)
    bash(f'git checkout -- {files}')


def reset_all_changes():
    """Отменяет все изменения файлов."""
    bash('git checkout --force')
    bash('git clean -n')
    bash('git clean -fd')


def remove_remote_branch(branch):
    """Удаление ветки на внешнем репозитории."""
    return bash(f'git push origin :{branch}')

