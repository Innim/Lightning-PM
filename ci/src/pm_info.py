# -*- coding: utf-8 -*-
from ciutil.core.info import Info
from os import getenv


class PMInfo(Info):
    def __init__(self):
        super().__init__()

    deploy_host = getenv('DEPLOY_HOST', '')

    deploy_port = getenv('DEPLOY_PORT', '')

    deploy_user = getenv('DEPLOY_USER', '')

    deploy_upload_path = getenv('DEPLOY_UPLOAD_PATH', '')

    deploy_app_path = getenv('DEPLOY_APP_PATH', '')

    deploy_git_user = getenv('DEPLOY_GIT_USER', '')

    deploy_git_passwd = getenv('DEPLOY_GIT_PASSWD', '')

    @property
    def deploy_password(self):
        pass_env = self.get_env('DEPLOY_PASSWORD_ENV')
        return self.get_env(pass_env)
