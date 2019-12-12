# -*- coding: utf-8 -*-


class CIError(Exception):
    ERROR = 'error'
    WARNING = 'warning'

    """Ошибки CIWorker-а"""
    def __init__(self, title: str = 'ERROR', text: str = '', error_type: str = 'error'):
        self.title = title
        self.text = text
        self.error_type = error_type


class WorkerDebugError(Exception):
    """Ошибка отладки. Можно использовать как заглушку."""
    pass

