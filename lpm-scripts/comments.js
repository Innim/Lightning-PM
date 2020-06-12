$(document).ready(function ($) {
	if ("onhashchange" in window) window.onhashchange = highlightComment;

	let mrStateIcons = {
		merged: 'fa-check-circle',
		opened: 'fa-clock',
		closed: 'fa-times-circle',
	};

	$('.comments-list .comments-list-item .comment-text').each(function (index, val) {
		let urls = parser.findLinks($(val).text());
		if (!urls) return;

		let mrs = [];

		for (var i = 0; i < urls.length; i++) {
			let url = urls[i];
			if (parser.isMRUrl(url)) {
				mrs.push(url);
			} else {
				// TODO: обрабатываем видео 
			}
		}

		if (mrs.length > 0) {
			let ul = $('.merge-requests', $(val).parent('.formatted-desc'));
			mrs.forEach(function (url, i, arr) {
				let li = $(document.createElement("li"));
				li.append(preloader.getNewIndicatorSmall());
				ul.append(li);
				srv.attachments.getMRInfo(url, function (res) {
					if (res.success) {
						if (res.data) {
							let mr = res.data;
							let icon = mrStateIcons[mr.state];
							li.attr('class', mr.state)
								.empty()
								.append('<i class="state-icon fas ' + icon + '"></i>')
								.append('MR <a href="' + mr.url + '">!' + mr.internalId + '</a>');
						} else {
							li.remove();
						}
					} else {
						li.empty().text(typeof res.error != 'undefined' ?
							res.error : 'Не удалось получить данные MR.');
					}
				});
			});
		}
	});

	function highlightComment() {
		let hash = window.location.hash;
		if (hash.substr(0, 9) === '#comment-') {
			$(".comments-list .comments-list-item").has("a.anchor[id=" + hash.substr(1) + "]")
				.find(".text").css("backgroundColor", "#868686")
				.animate({ backgroundColor: "#eeeeee" }, 1200);
		}
	}

	highlightComment();
});