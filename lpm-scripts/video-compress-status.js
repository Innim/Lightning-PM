$(document).ready(function ($) {
    videoCompressStatus.start();
});

/**
 * Опрос статуса фонового сжатия видео.
 *
 * Пока на странице есть видео-заглушки в состоянии сжатия
 * (в т.ч. добавленные динамически после отправки комментария),
 * периодически спрашивает сервер и подменяет заглушку на плеер,
 * как только сжатие завершилось.
 */
let videoCompressStatus = {
    // Интервал опроса, мс
    pollInterval: 5000,
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

    apply: function (file) {
        // Ещё в обработке — ждём следующего опроса
        if (file.compressStatus === 1) {
            return;
        }

        let placeholder = $('[data-video-compress][data-file-uid="' + file.uid + '"]');
        if (placeholder.length === 0) {
            return;
        }

        let item = placeholder.closest('.comment-video-item');

        let mimeType = file.mimeType || placeholder.data('mime-type') || '';
        let video = $('<video controls preload="metadata" class="comment-file-video rounded-2 border bg-dark"></video>');
        $('<source>').attr('src', file.url).attr('type', mimeType).appendTo(video);
        placeholder.replaceWith(video);

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
};
