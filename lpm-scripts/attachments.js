$(document).ready(function ($) {
    $('.block-with-attachments').each(function (index, el) {
        let textEl = $('.text-with-attachments', el);
        if (textEl.length == 0) return;

        let urls = parser.findLinks($(textEl).text());
        if (!urls) return;
        console.log(urls);

        let video = [];
        let images = [];

        for (var i = 0; i < urls.length; i++) {
            let url = urls[i];
            if (parser.isVideoUrl(url)) {
                video.push(url);
            } else if (parser.isImageUrl(url)) {
                images.push(url);
            }
        }

        appendAttachments(el, 'video-line', video, addVideo);
        appendAttachments(el, 'image-line', images, addImage);
    });

    function addVideo(el, ul, url) {
        addAttachment(ul, url,
            (url, onResult) => {
                srv.attachments.getVideoInfo(url, (r) => {
                    onResult(r);

                    if (r.success && !r.html) {
                        if (parser.isImageUrl(url))
                            appendAttachments(el, 'image-line', [url], addImage);
                    }
                });
            },
            'Не удалось получить данные видео.');
    }

    function addImage(el, ul, url) {
        addAttachment(ul, url,
            (url, onResult) => srv.attachments.getImageInfo(url, onResult),
            'Не удалось получить данные изображения.');
    }

    function addAttachment(ul, url, getInfo, defaultError) {
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
    }

    function appendAttachments(element, className, list, addAttachment) {
        let attachments = $('.attachments', element);
        if (list.length > 0) {
            let ul = $('.' + className, attachments).length
                ? $('.' + className, attachments)
                : $('<ul class="' + className + '"></ul>').appendTo(attachments);
            list.forEach((url, i, a) => addAttachment(element, ul, url));
        }
    }
});