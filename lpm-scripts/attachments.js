$(document).ready(function ($) {
    $('.block-with-attachments').each(function (index, el) {
        let textEl = $('.text-with-attachments', el);
        if (textEl.length == 0) return;

        let urls = parser.findLinks($(textEl).text());
        if (!urls) return;
        console.log(urls);

        let video = [];

        for (var i = 0; i < urls.length; i++) {
            let url = urls[i];
            if (parser.isVideoUrl(url)) {
                video.push(url);
            }
        }

        let attachments = $('.attachments', el);

        if (video.length > 0) {
            let ul = $('.video-line', attachments).length
                ? $('.video-line', attachments)
                : $('<ul class="video-line"></ul>').appendTo(attachments);
            video.forEach((url, i, a) => addVideo(ul, url));
        }
    });

    function addVideo(ul, url) {
        let li = $(document.createElement("li"));
        li.append(preloader.getNewIndicatorMedium());
        ul.append(li);

        srv.attachments.getVideoInfo(url, function (res) {
            if (res.success) {
                if (res.type != 'none') {
                    li.html(res.html);
                } else {
                    li.remove();
                }
            } else {
                li.text(typeof res.error != 'undefined' ?
                    res.error : 'Не удалось получить данные видео.');
            }
        });
    }
});