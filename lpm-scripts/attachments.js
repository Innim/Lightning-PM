$(document).ready(function ($) {
    $('.block-with-attachments').each(function (index, el) {
        attachments.update($(el));
    });
});

let attachments = {
    update: function ($item) {
        let textEl = $('.text-with-attachments', $item);
        if (textEl.length == 0) return;

        let urls = parser.findLinks($(textEl).text());
        if (!urls) return;

        let video = [];
        let images = [];

        for (var i = 0; i < urls.length; i++) {
            let url = urls[i];
            if (parser.isVideoUrl(url)) {
                video.push(url);
            }

            if (parser.isImageUrl(url)) {
                images.push(url);
            }
        }

        attachments.appendAll($item, 'video-line', video, attachments.addVideo);
        attachments.appendAll($item, 'image-line', images, attachments.addImage);
    },
    addVideo: function (el, ul, url) {
        attachments.add(ul, url,
            (url, onResult) => srv.attachments.getVideoInfo(url, onResult),
            'Не удалось получить данные видео.');
    },
    addImage: function (el, ul, url) {
        attachments.add(ul, url,
            (url, onResult) => srv.attachments.getImageInfo(url, onResult),
            'Не удалось получить данные изображения.');
    },
    add: function (ul, url, getInfo, defaultError) {
        let li = $(document.createElement("li"));
        li.append(preloader.getNewIndicatorMedium());
        ul.append(li);

        getInfo(url, function (res) {
            if (res.success) {
                if (res.html) {
                    li.html(res.html);
                } else {
                    li.remove();
                }
            } else {
                li.text(typeof res.error != 'undefined' ?
                    res.error : defaultError);
            }
        });
    },
    appendAll: function (element, className, list, addAttachment) {
        let attachments = $('.attachments', element);
        if (list.length > 0) {
            let ul = $('.' + className, attachments).length
                ? $('.' + className, attachments)
                : $('<ul class="' + className + '"></ul>').appendTo(attachments);
            list.forEach((url, i, a) => addAttachment(element, ul, url));
        }
    },
}