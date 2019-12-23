# -*- coding: utf-8 -*-

import json
import requests
from .message_formater import MessageFormater, CIMessageData
from collections import namedtuple


UploadFileData = namedtuple('UploadFileData', 'channels filename filetype')


def send(url, data):
    """Отсылает сообщние в Slack.
    :param url: slack-webhook.
    :param data: данные для передачи в виде словаря.
    """
    data = json.dumps(data)
    response = requests.post(url, headers={'Content-Type': 'application/json'}, data=data)

    return response.status_code


def simple_message(url, username, channel, text):
    """Отправляет простое сообщение в Slack.

    :param url: slack-webhook.
    :param channel: slack-канал.
    :param username: пользователь от имени которого будет отправлено сообщение.
    :param text: текст сообщения.
    """
    data = {
        "username": username,
        "channel": channel,
        "text": text
    }

    send(url, data)


def format_message(url, username, channel, pretext, title, text, color='#cccccc', icon_url=''):
    """Отправляет форматированное сообщение в Slack."""
    data = {
        "channel": channel,
        "username": username,
        "pretext": pretext,
        "color": color,
        "mrkdwn": "true",
        "icon_url": icon_url,
        "author_name": "",
        "fields": [{"title": title, "value": text, "short": "false"}],
        "footer": ""
    }

    return send(url, data)


class SlackMessenger:
    """Класс для отправки сообщений в Slack."""

    MESSAGE_URL = 'https://slack.com/api/chat.postMessage'
    UPLOAD_URL = 'https://slack.com/api/files.upload'

    def __init__(self, slack_bot_token):
        self.slack_bot_token = slack_bot_token

    def send(self, slack_data):
        """
        Отправляет сообщение в Slack.
        info: https://api.slack.com/methods/chat.postMessage
        """
        res = requests.post(url=self.MESSAGE_URL,
                            headers=self._get_authorization_header(),
                            data=slack_data)
        return res.status_code, res.json()

    def upload_file(self, slack_data, file_path):
        """
        https://api.slack.com/methods/files.upload
        """
        files = {'file': open(file_path, 'r')}

        res = requests.post(url=self.UPLOAD_URL,
                            headers=self._get_authorization_header(),
                            files=files, data=slack_data)

        return res.status_code, res.json()

    def _get_authorization_header(self):
        header = {'Authorization': f'Bearer {self.slack_bot_token}'}
        return header


class GitlabMessageInfo:
    """Предоставляет инормацию из GitLab CI: пользователь, commit, job, pipeline"""
    def __init__(self, slack_bot_token, slack_channel, slack_username, slack_icon,
                 project_url, project_path_slug, commit_sha, commit_message,
                 gitlab_user_email, git_branch, pipeline_id, job_id, commit_date, env_name, env_url):
        self.env_name = env_name
        self.env_url = env_url
        self.project_path_slug = project_path_slug
        self.project_url = project_url
        self.slack_icon = slack_icon
        self.slack_username = slack_username
        self.slack_channel = slack_channel
        self.slack_bot_token = slack_bot_token
        self.commit_date = commit_date
        self.job_id = job_id
        self.pipeline_id = pipeline_id
        self.git_branch = git_branch
        self.gitlab_user_email = gitlab_user_email
        self.commit_sha = commit_sha
        self.commit_message = commit_message


class GitlabSlackNotifier(SlackMessenger):
    """Отправляет сообщения в Slack о результатах работы Gitlab CI."""

    def __init__(self, slack_bot_token, info: GitlabMessageInfo = None):
        super().__init__(slack_bot_token)

        self.info: GitlabMessageInfo = info

    def set_info(self, info: GitlabMessageInfo):
        self.info = info

    def message(self, title, text='', brief_text='', emoji='', border_color=None):
        """Отправляет сообщениею"""
        msg_data: CIMessageData = \
            self._generate_message_data(title=title, text=text, brief_text=brief_text, emoji=emoji)

        slack_data = MessageFormater.slack_gitlab_message(channel=self.info.slack_channel,
                                                          user=self.info.slack_username,
                                                          icon=self.info.slack_icon,
                                                          msg_data=msg_data,
                                                          attach_border_color=border_color)
        return self.send(slack_data)

    def compact_message(self, title, text='', brief_text='', emoji='',
                        border_color=None, btn_style=None, info_btn_style=None,
                        display_lines=30):
        """Отправляет сообщение обрезанное до определенной длины.
         Полное сообщение будет прикреплено в виде snippet в thread.
        """
        msg_data: CIMessageData = \
            self._generate_message_data(title=title, text=text, brief_text=brief_text, emoji=emoji)

        slack_data, file_slack_data, file_path = \
            MessageFormater.compact_slack_gitlab_message(channel=self.info.slack_channel,
                                                         user=self.info.slack_username,
                                                         icon=self.info.slack_icon,
                                                         msg_data=msg_data,
                                                         attach_border_color=border_color,
                                                         btn_style=btn_style, info_btn_style=info_btn_style,
                                                         display_lines=display_lines)

        status_code, res = self.send(slack_data)
        if file_slack_data:
            file_slack_data['thread_ts'] = res['message']['ts']
            self.upload_file(file_slack_data, file_path)

    def success_message(self, title, text='', brief_text=''):
        """Отправляет сообщение об успехе операции."""
        border_color = MessageFormater.get_attach_color(False)
        info_btn_style = MessageFormater.get_info_button_style(False)
        btn_style = MessageFormater.get_button_style(False)

        self.compact_message(title=title, text=text, brief_text=brief_text, emoji=':grinning:',
                             border_color=border_color, btn_style=btn_style,
                             info_btn_style=info_btn_style)

    def fail_message(self, title, text='', brief_text=''):
        """Сообщение о проваленой операции."""
        border_color = MessageFormater.get_attach_color(True)
        info_btn_style = MessageFormater.get_info_button_style(True)
        btn_style = MessageFormater.get_button_style(True)

        self.compact_message(title=title, text=text, brief_text=brief_text, emoji=':rage:',
                             border_color=border_color, btn_style=btn_style,
                             info_btn_style=info_btn_style)

    def notify_message(self, title, text='', brief_text=''):
        """Отсылает информационное сообщение."""
        self.compact_message(title=title, text=text, brief_text=brief_text, emoji=':hi:')

    def upload_file_to_channels(self, file_path, channels, filename, filetype='text'):
        """Отправляет файл в канал."""
        slack_data = MessageFormater.file_upload(channels, filename, filetype)
        return self.upload_file(slack_data, file_path)

    def message_with_snippet(self, title, text, brief_text, emoji, file_path, display_file_name, border_color=None):
        """Отправляет сообщение с прикрепленным файлом."""
        status_code, response = self.message(title, text, brief_text, emoji, border_color)

        ts = response['message']['ts']
        slack_data = MessageFormater.file_upload(self.info.slack_channel, filename=display_file_name,
                                                 filetype=None, thread_ts=ts)

        self.upload_file(slack_data, file_path)

    def _generate_message_data(self, title='', text='', brief_text='', emoji='') -> CIMessageData:
        title = title if title else 'Default title'
        main_text = text
        brief_text = brief_text
        emoji = emoji if emoji else ':soccer:'

        data: CIMessageData = CIMessageData()
        data.title = title
        data.title_emoji = emoji
        data.project = self._get_slack_project_link()
        data.developer = self.info.gitlab_user_email
        data.branch = self.info.git_branch
        data.commit_sha = self.info.commit_sha
        data.commit_message = self.info.commit_message
        data.brief_text = brief_text
        data.pipeline_caption = "Pipeline: " + self.info.pipeline_id
        data.pipeline_url = self._get_pipeline_url()
        data.job_caption = "Job: " + self.info.job_id
        data.job_url = self._get_job_url()
        data.artifacts_caption = 'artifacts'
        data.artifacts_url = self._get_artifacts_url()
        data.main_text = main_text
        data.commit_date = self.info.commit_date

        return data

    def _get_job_url(self):
        return "{project_url}/-/jobs/{job_id}".\
            format(project_url=self.info.project_url, job_id=self.info.job_id)

    def _get_pipeline_url(self):
        return "{project_url}/pipelines/{pipe_id}".\
            format(project_url=self.info.project_url, pipe_id=self.info.pipeline_id)

    def _get_artifacts_url(self):
        return "{job_url}/artifacts/download".format(job_url=self._get_job_url())

    def _get_slack_project_link(self):
        return "<{project_link}|{project_name}>".\
            format(project_link=self.info.project_url, project_name=self.info.project_path_slug)

    def get_slack_job_link(self):
        """Ссылка на job для Slack"""
        return "<{project_url}/-/jobs/{job_id}|{job_id}>".\
            format(project_url=self.info.project_url, job_id=self.info.job_id)

    def get_slack_pipeline_link(self):
        """Ссылка на pipeline для Slack"""
        return "<{project_url}/pipelines/{pipe_id}|{pipe_id}>".format(project_url=self.info.project_url, pipe_id=self.info.pipeline_id)

    def get_slack_env_link(self):
        return "<{env_url}|{env_name}>".format(env_url=self.info.env_url, env_name=self.info.env_name)

    def get_slack_project_link(self):
        return "<{project_link}|{project_name}>".format(project_link=self.info.project_url, project_name=self.info.project_path_slug)

    def get_slack_artifacts_link(self):
        return "<{project_url}/-/jobs/{job_id}/artifacts/download| download>".\
            format(project_url=self.info.project_url, job_id=self.info.job_id)

