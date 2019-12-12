# -*- coding: utf-8 -*-
import sys

import fire

from ciutil.core.errors import CIError
from src.pm_notifier import PMNotifier
from src.pm_worker import PMWorker

worker: PMWorker = PMWorker()
worker.init_worker()
worker.goto_work_dir()

notifier: PMNotifier = PMNotifier(slack_bot_token=worker.info.slack_bot_token, info=worker.info)


def runner(msg_title: str):
    """Декорирует запуск функций, добавляя обрабутку ошибок и отправку сообщений.
    """
    def maker(func):
        def wrapper():
            try:
                func()
            except CIError as err:
                title = f'ERROR! {msg_title}. {err.title}'
                log_error(title=title, text=err.text)
                notifier.fail_message(title=title, text=err.text, brief_text='CI/CD errors:')
                exit(1)
            except:
                msg = str(sys.exc_info()[1])
                title = f'ERROR! {msg_title}'
                log_error(title=title, text=msg)
                notifier.fail_message(title=title, text=msg, brief_text='Errors:')
                exit(1)
            finally:
                # worker.remove_tmp_files()
                pass

        return wrapper

    return maker


def log_error(title, text):
    print('\n' * 3 + '!!!! WorkerError:')
    print(title)
    print(text)
    print('-' * 80)


@runner(msg_title='Deploy to PRODUCTION')
def prod_deploy():
    worker.deploy()
    notifier.deploy_message(deploy_type='production')


if __name__ == '__main__':
    fire.Fire()