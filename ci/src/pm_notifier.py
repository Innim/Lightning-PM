# -*- coding: utf-8 -*-
from ciutil.slack.notifier import GitlabSlackNotifier, GitlabMessageInfo
from src.pm_info import PMInfo


class PMNotifier(GitlabSlackNotifier):

    def __init__(self, slack_bot_token, info: PMInfo):
        super().__init__(slack_bot_token)
        self.pm_info = info
        self.set_pm_info()

    def deploy_message(self, deploy_type: str):
        title = "DEPLOY for {deploy_type} is SUCCESS".format(deploy_type=deploy_type.upper())
        brief_text = f'Деплой для "{deploy_type.upper()}"-окружения успешно завершен\n\n' \
                     f'https://task.innim.ru\n\n' \
                     f'*Обновите БД, если требуется!*'
        text = ''

        self.success_message(title=title, brief_text=brief_text, text=text)

    def set_pm_info(self):
        info = GitlabMessageInfo(slack_bot_token=self.pm_info.slack_bot_token,
                                 slack_channel=self.pm_info.slack_channel,
                                 slack_username=self.pm_info.slack_username,
                                 slack_icon=self.pm_info.slack_icon_url,
                                 project_url=self.pm_info.project_url,
                                 project_path_slug=self.pm_info.project_path_slug,
                                 commit_sha=self.pm_info.commit_sha,
                                 commit_message=self.pm_info.commit_message,
                                 gitlab_user_email=self.pm_info.gitlab_user_email,
                                 git_branch=self.pm_info.git_branch,
                                 pipeline_id=self.pm_info.pipeline_id,
                                 job_id=self.pm_info.job_id,
                                 commit_date=self.pm_info.commit_date)
        self.set_info(info)
