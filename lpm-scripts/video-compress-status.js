$(document).ready(function ($) {
    videoCompressStatus.start();

    // Комментарии добавляются на страницу динамически, поэтому обработчик делегирован.
    $(document).on('click', '.video-compress-retry', function () {
        videoCompressStatus.retry($(this));
    });
});

/**
 * Состояние фонового сжатия видео на странице.
 *
 * Пока на странице есть видео-заглушки в состоянии сжатия
 * (в т.ч. добавленные динамически после отправки комментария),
 * периодически спрашивает сервер и подменяет заглушку на плеер,
 * как только сжатие завершилось. Здесь же запуск новой попытки для видео,
 * сжатие которого завершилось ошибкой.
 */
let videoCompressStatus = {
    // Интервал опроса, мс
    pollInterval: 5000,
    // Значения compressStatus, приходящие с сервера (VideoCompressor::STATUS_*)
    STATUS_PROCESSING: 1,
    STATUS_FAILED: 3,
    _timer: null,

    start: function () {
        if (this._timer) {
            return;
        }
        this._timer = setInterval(() => this.tick(), this.pollInterval);
    },

    tick: function () {
        let items = $('[data-video-compress]');
        if (items.length === 0) {
            return;
        }

        let uids = [];
        items.each(function () {
            let uid = $(this).data('file-uid');
            if (uid) {
                uids.push(uid);
            }
        });
        if (uids.length === 0) {
            return;
        }

        srv.files.getCompressStatus(uids, (res) => {
            if (!res || !res.success || !Array.isArray(res.files)) {
                return;
            }
            res.files.forEach((file) => this.apply(file));
        });
    },

    /**
     * Ставит видео в очередь на новую попытку сжатия.
     * Пока запрос идёт, кнопка заблокирована: повторный клик не должен
     * отправлять второй запрос.
     * @param {jQuery} button нажатая кнопка повтора
     */
    retry: function (button) {
        if (button.prop('disabled')) {
            return;
        }

        let uid = button.closest('.comment-video-item').data('file-uid');
        if (!uid) {
            return;
        }

        button.prop('disabled', true);
        srv.files.retryCompress(uid, (res) => {
            button.prop('disabled', false);

            if (!res || !res.success || !res.file) {
                showError(res && res.error ? res.error : 'Не удалось запустить сжатие');
                return;
            }

            this.apply(res.file);
        });
    },

    /**
     * Приводит блок файла к состоянию, которое пришло с сервера.
     * @param {Object} file данные файла из ответа сервиса
     */
    apply: function (file) {
        let item = $('.comment-video-item[data-file-uid="' + file.uid + '"]');
        if (item.length === 0) {
            return;
        }

        let placeholder = item.find('[data-video-compress]');
        let processing = file.compressStatus === this.STATUS_PROCESSING;

        if (processing && placeholder.length === 0) {
            item.find('.comment-file-video').first().replaceWith(this.buildPlaceholder(file));
        } else if (!processing && placeholder.length > 0) {
            placeholder.replaceWith(this.buildVideo(file, placeholder.data('mime-type')));
        }

        item.find('.video-compress-retry')
            .toggleClass('d-none', file.compressStatus !== this.STATUS_FAILED);

        // Имя файла и ссылка на скачивание могли измениться (напр. .mov -> .mp4)
        if (file.name) {
            item.find('.text-truncate').first().text(file.name).attr('title', file.name);
        }
        let download = item.find('a[download]').first();
        if (file.downloadUrl) {
            download.attr('href', file.downloadUrl);
        }
        if (file.name) {
            download.attr('download', file.name);
        }
    },

    /**
     * Заглушка идущего сжатия — та же, что рисует comment-files.html
     * для файла, который уже сжимался к моменту отрисовки страницы.
     * @param {Object} file данные файла из ответа сервиса
     * @return {jQuery}
     */
    buildPlaceholder: function (file) {
        let placeholder = $('<div class="comment-file-video comment-file-video-compressing rounded-2 border '
            + 'bg-dark d-flex flex-column align-items-center justify-content-center text-light"></div>');
        placeholder.attr('data-video-compress', '');
        placeholder.attr('data-file-uid', file.uid);
        placeholder.attr('data-mime-type', file.mimeType || '');
        $('<div class="spinner-border spinner-border-sm mb-2" role="status" aria-hidden="true"></div>')
            .appendTo(placeholder);
        $('<span class="small"></span>').text('Сжатие видео…').appendTo(placeholder);

        return placeholder;
    },

    /**
     * Плеер для файла, обработка которого завершилась.
     * @param {Object} file данные файла из ответа сервиса
     * @param {String} fallbackMimeType тип из заглушки, если сервер его не прислал
     * @return {jQuery}
     */
    buildVideo: function (file, fallbackMimeType) {
        let video = $('<video controls preload="metadata" class="comment-file-video rounded-2 border bg-dark"></video>');
        $('<source>').attr('src', file.url).attr('type', file.mimeType || fallbackMimeType || '').appendTo(video);

        return video;
    },
};
