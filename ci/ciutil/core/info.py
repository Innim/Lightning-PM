# -*- coding: utf-8 -*-

import os
from os.path import abspath, join
from pathlib import Path

from ciutil.slack.notifier import GitlabMessageInfo


class Info:
    """ Информация окружения для скрипта

        переменные класса оканчивающиеся на 'key'
        содеражат название переменной окружения.
        При изменении значения переменной необходимо вызвать метод update

        Желательно использовать абсолютные пути

    """

    git_user_pass_key = "GIT_USER_PASSWORD"
    git_user_key = "GIT_USER"
    git_user_email_key = "GIT_USER_EMAIL"
    ci_prefix_key = "CI_PREFIX"
    push_url_key = "PUSH_URL"
    work_dir_key = "WORK_DIR"
    config_suffix_key = "CONFIG_SUFFIX"
    branch_setting_dir_key = "BRANCH_SETTINGS_DIR"
    slack_webhook_key = "SLACK_WEBHOOK_URL"

    CI_DIR = 'ci'
    BRANCHES_DIR = Path(CI_DIR, 'branch')
    TEMPLATE_DIR = Path(CI_DIR, 'template')

    @staticmethod
    def get_env(key, default=None):
        return os.getenv(key, default)

    def __init__(self):
        self.git_user = ''
        self.git_user_password = ''
        self.git_user_email = ''
        self.git_branch = ''
        self.ci_prefix = ''
        self.push_url = ''
        # рабочая директория для CI,
        # абсолютный путь до директории указанной в переменной окружения WORK_DIR
        self.work_dir = ''
        self.current_branch_dir = ''
        self.branches_dir = ''
        self.config_suffix = ''
        self.root_dir = ''
        self.ci_dir = self.CI_DIR
        self.template_dir = self.TEMPLATE_DIR
        self.slack_webhook_url = ''
        self.slack_bot_token = ''
        self.slack_username = 'innim'
        self.slack_channel = '#notifications'
        self.commit_message = ''
        self.commit_date = ''
        self.slack_icon_url = ''

        self.update()

    def update(self):
        self.git_user = os.getenv(Info.git_user_key, '')
        self.git_user_password = os.getenv(Info.git_user_pass_key, '')
        self.git_user_email = os.getenv(Info.git_user_email_key, '')
        self.git_branch = os.getenv('CI_COMMIT_REF_NAME', '')
        self.ci_prefix = os.getenv(Info.ci_prefix_key, '[CI]')
        self.push_url = os.getenv(Info.push_url_key, '')
        self.work_dir = abspath(os.getenv(Info.work_dir_key, ''))
        self.branches_dir = join(self.work_dir, Info.BRANCHES_DIR)
        self.current_branch_dir = join(self.branches_dir, self.git_branch)
        self.config_suffix = os.getenv(self.config_suffix_key, '')
        self.ci_dir = join(self.work_dir, self.CI_DIR)
        self.template_dir = join(self.work_dir, self.TEMPLATE_DIR)
        self.slack_webhook_url = os.getenv(self.slack_webhook_key, '')
        self.slack_bot_token = os.getenv('SLACK_BOT_TOKEN')
        self.project_url = os.getenv('CI_PROJECT_URL', '')
        self.gitlab_user_email = os.getenv('GITLAB_USER_EMAIL', '')
        self.project_path_slug = os.getenv('CI_PROJECT_PATH_SLUG', '')
        self.pipeline_id = os.getenv('CI_PIPELINE_ID', '')
        self.job_id = os.getenv('CI_JOB_ID', '')
        self.commit_sha = os.getenv('CI_COMMIT_SHA', '')
        self.env_name = os.getenv('CI_ENVIRONMENT_NAME', '')
        self.env_slug = os.getenv('CI_ENVIRONMENT_SLUG', '')
        self.env_url = os.getenv('CI_ENVIRONMENT_URL', '')

        self.pipeline_url = "{project_url}/pipelines/{pipe_id}"\
            .format(project_url=self.project_url, pipe_id=self.pipeline_id)

        self.job_url = "{project_url}/-/jobs/{job_id}"\
            .format(project_url=self.project_url, job_id=self.job_id)

        self.artifacts_url = self.job_url + "/artifacts/download"

        self.slack_username = os.getenv('SLACK_USER')
        self.slack_channel = os.getenv('SLACK_CHANNEL')

    def this_root_dir(self):
        """Устанавливает в качестве корневой директории текущую"""
        self.root_dir = os.getcwd()
        print(f'set CI root dir: {self.root_dir}')

    def get_gitlab_slack_info(self) -> GitlabMessageInfo:
        """Возвращает информацию для Slack-сообщений."""
        info = GitlabMessageInfo(slack_bot_token=self.slack_bot_token,
                                 slack_channel=self.slack_channel,
                                 slack_username=self.slack_username,
                                 slack_icon=self.slack_icon_url,
                                 project_url=self.project_url,
                                 project_path_slug=self.project_path_slug,
                                 commit_sha=self.commit_sha,
                                 commit_message=self.commit_message,
                                 gitlab_user_email=self.gitlab_user_email,
                                 git_branch=self.git_branch,
                                 pipeline_id=self.pipeline_id,
                                 job_id=self.job_id,
                                 commit_date=self.commit_date)
        return info








