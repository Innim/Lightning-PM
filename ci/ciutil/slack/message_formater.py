import json


class CIMessageData:
    """
    Данные для сообщения Slack.
    """
    def __init__(self):
        self.title = ''
        self.title_emoji = ''
        self.project = ''
        self.developer = ''
        self.branch = ''
        self.commit_sha = ''
        self.commit_message = ''
        self.brief_text = ''
        self.main_text = ''
        self.pipeline_caption = ''
        self.pipeline_url = ''
        self.job_caption = ''
        self.job_url = ''
        self.artifacts_caption = ''
        self.artifacts_url = ''
        self.commit_date = ''


class MessageFormater:
    FAILED_COLOR = '#FF0000'
    SUCCESS_COLOR = '#00FF00'
    INFO_COLOR = '#0000FF'

    DEFAULT_BTN_STYLE = 'default'
    DANGER_BTN_STYLE = 'danger'
    PRIMARY_BNT_STYLE = 'primary'

    @staticmethod
    def get_attach_color(is_failed: bool = False):
        """
        Возвращает цвет для attach-части сообщения.
        :param is_failed: Тип attach - успех/провал.
        :return: Цвет для attach-части сообщения.
        """

        return MessageFormater.FAILED_COLOR \
            if is_failed \
            else MessageFormater.SUCCESS_COLOR

    @staticmethod
    def get_info_button_style(is_failed: bool = False):
        """
        Возвращает стиль информативных кнопок.
        :param is_failed: Кнопка для успеха или провала.
        :return: Стиль кнопки.
        """

        return MessageFormater.DANGER_BTN_STYLE if is_failed else MessageFormater.PRIMARY_BNT_STYLE

    @staticmethod
    def get_button_style(is_failed: bool = False):
        """
        Возвращает стиль кнопок.
        :param is_failed: Копкая для успеха или проавала.
        :return: Стиль кнопки.
        """

        return MessageFormater.DEFAULT_BTN_STYLE if is_failed else MessageFormater.PRIMARY_BNT_STYLE

    # @staticmethod
    # def slack_failed_ci_message(channel: str, user: str, icon: str,
    #                             data: CIMessageData):
    #     return MessageFormater.slack_ci_message(channel, user, icon, data, True)
    #
    # @staticmethod
    # def slack_success_ci_message(channel: str, user: str, icon: str,
    #                             data: CIMessageData):
    #     return MessageFormater.slack_ci_message(channel, user, icon, data, False)

    # @staticmethod
    # def slack_ci_message(
    #         channel: str,
    #         user: str,
    #         icon: str,
    #         msg_data: CIMessageData,
    #         is_failed: bool = False):
    #     """Возвращает отформатированные данные сообщения Slack."""
    #
    #     title = msg_data.title_emoji + ' ' + msg_data.title
    #
    #     text = "```{text}```".format(text=msg_data.main_text) \
    #             if msg_data.main_text \
    #             else ''
    #
    #     text = "*{title}*\n\n" \
    #            "*project*: {project}\n" \
    #            "*developer*: {developer}\n" \
    #            "*branch*: {branch}\n" \
    #            "*commit*: {com_sha}\n" \
    #            "*date*: {com_date}\n" \
    #            "*message*: {com_message}\n\n" \
    #            "{brief}\n" \
    #            "{main_text}\n"\
    #             .format(
    #                 title=title,
    #                 project=msg_data.project,
    #                 developer=msg_data.developer,
    #                 branch=msg_data.branch,
    #                 com_sha=msg_data.commit_sha,
    #                 com_message=msg_data.commit_message,
    #                 brief=msg_data.brief_text,
    #                 main_text=text,
    #                 com_date=msg_data.commit_date
    #             )
    #
    #     attach_color = MessageFormater.get_attach_color(is_failed)
    #     info_btn_style = MessageFormater.get_info_button_style(is_failed)
    #     btn_style = MessageFormater.get_button_style(is_failed)
    #
    #     pipe_btn = {
    #         "type": "button",
    #         "text": msg_data.pipeline_caption,
    #         "url": msg_data.pipeline_url,
    #         "style": btn_style
    #     }
    #
    #     job_btn = {
    #         "type": "button",
    #         "text": msg_data.job_caption,
    #         "url": msg_data.job_url,
    #         "style": info_btn_style
    #     }
    #
    #     artifacts_btn = {
    #         "type": "button",
    #         "text": msg_data.artifacts_caption,
    #         "url": msg_data.artifacts_url,
    #         "style": btn_style
    #     }
    #
    #     ci_info = {
    #         "color": attach_color,
    #         "title": "CI info",
    #         "actions": [pipe_btn, job_btn, artifacts_btn]
    #     }
    #
    #     slack_data = {
    #         "channel": channel,
    #         "username": user,
    #         "icon": icon,
    #         "text": text,
    #         "attachments": [ci_info]
    #     }
    #
    #     return slack_data

    @staticmethod
    def slack_gitlab_success_message(
            channel: str,
            user: str,
            icon: str,
            msg_data: CIMessageData, success=True):

        attach_color = MessageFormater.get_attach_color(not success)
        info_btn_style = MessageFormater.get_info_button_style(not success)
        btn_style = MessageFormater.get_button_style(not success)

        return MessageFormater.slack_gitlab_message(channel, user, icon, msg_data,
                                                    attach_color, btn_style, info_btn_style)



    @staticmethod
    def slack_gitlab_message(
            channel: str,
            user: str,
            icon: str,
            msg_data: CIMessageData,
            attach_border_color=None, btn_style=None, info_btn_style=None):
        """Возвращает отформатированные данные сообщения Slack."""

        title = msg_data.title_emoji + ' ' + msg_data.title

        text = "```{text}```".format(text=msg_data.main_text) \
            if msg_data.main_text \
            else ''

        text = "*{title}*\n\n" \
               "*project*: {project}\n" \
               "*developer*: {developer}\n" \
               "*branch*: {branch}\n" \
               "*commit*: {com_sha}\n" \
               "*date*: {com_date}\n" \
               "*message*: {com_message}\n\n" \
               "{brief}\n" \
               "{main_text}\n" \
            .format(
            title=title,
            project=msg_data.project,
            developer=msg_data.developer,
            branch=msg_data.branch,
            com_sha=msg_data.commit_sha,
            com_message=msg_data.commit_message,
            brief=msg_data.brief_text,
            main_text=text,
            com_date=msg_data.commit_date
        )

        attach_border_color = attach_border_color if attach_border_color else MessageFormater.INFO_COLOR
        btn_style = btn_style if btn_style else MessageFormater.DEFAULT_BTN_STYLE
        info_btn_style = info_btn_style if info_btn_style else btn_style

        pipe_btn = {
            "type": "button",
            "text": msg_data.pipeline_caption,
            "url": msg_data.pipeline_url,
            "style": btn_style
        }

        job_btn = {
            "type": "button",
            "text": msg_data.job_caption,
            "url": msg_data.job_url,
            "style": info_btn_style
        }

        artifacts_btn = {
            "type": "button",
            "text": msg_data.artifacts_caption,
            "url": msg_data.artifacts_url,
            "style": btn_style
        }

        ci_info = [{
            "color": attach_border_color,
            "title": "CI info",
            "actions": [pipe_btn, job_btn, artifacts_btn]
        }]

        slack_data = {
            "channel": channel,
            "username": user,
            "icon_url": icon,
            "text": text,
            "attachments": json.dumps(ci_info)
        }

        return slack_data

    @staticmethod
    def file_upload(channels, filename, filetype, thread_ts=None):
        """
        info: https://api.slack.com/methods/files.upload
        :param thread_ts:
        :param filename:
        :param channels:
        :param filetype: https://api.slack.com/types/file#file_types
        """
        if filetype is None:
            filetype = 'text'

        data = {'channels': channels,
                'filename': filename,
                'filetype': filetype}

        if thread_ts:
            data['thread_ts'] = thread_ts

        return data

    @staticmethod
    def compact_slack_gitlab_message(channel: str,
                                     user: str,
                                     icon: str,
                                     msg_data: CIMessageData,
                                     attach_border_color=None,
                                     btn_style=None, info_btn_style=None,
                                     display_lines=30):
        """Укарачивает сообщение, присылая полную версию в виде snippet в thread"""
        main_list = msg_data.main_text.split('\n')
        file_text = ''
        if len(main_list) > display_lines:
            main_text = '\n'.join(main_list[:display_lines])
            msg_data.main_text = main_text+'\n....................'
            file_text = '\n'.join(main_list)

        msg_data = MessageFormater.slack_gitlab_message(channel=channel, user=user, icon=icon,
                                                        msg_data=msg_data,
                                                        attach_border_color=attach_border_color,
                                                        btn_style=btn_style, info_btn_style=info_btn_style)
        file_data = None
        file_name = ''
        if file_text:
            file_name = 'slack_message_full_text.txt'
            with open(file_name, 'w') as fp:
                fp.write(file_text)
            file_data = MessageFormater.file_upload(channels=channel, filename=file_name,
                                                    filetype='text', thread_ts=None)
        return msg_data, file_data, file_name




