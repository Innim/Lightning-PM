$(document).ready(function ($) {
	if ("onhashchange" in window) window.onhashchange = highlightComment;

	$('.comments-list .comments-list-item .comment-text').each(function (index, val) {
		comments.updateAttachments($(val));
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
	comments.init();
});

let comments = {
	mrStateIcons: {
		merged: 'fa-check-circle',
		opened: 'fa-clock',
		closed: 'fa-times-circle',
	},
	init: function () {
		comments.invalidateLinks();
	},
	showCommentForm: function () {
		$('#comments form.add-comment').show();
		$('#comments .links-bar a').hide();
		$('#comments form.add-comment textarea[name=commentText]').focus();
	},
	hideCommentForm: function (clear = true) {
		$('#comments form.add-comment').hide();
		$('#comments .links-bar a').show();

		comments.invalidateLinks();
	},
	toogleCommentForm: function () {
		const $comments = $('#comments .comments-list');
		comments.invalidateLinks(!$comments.is(':visible'));
		$comments.slideToggle('normal');
	},
	invalidateLinks: function (isCommentsVisible) {
		const $link = $('#comments .links-bar a.toggle-comments');
		const $comments = $('#comments .comments-list');
		const commentsCount = $('.comments-list-item', $comments).size();
		if (isCommentsVisible === undefined) isCommentsVisible = $comments.is(':visible');
		if (commentsCount == 0) {
			$link.hide();
		} else {
			if (isCommentsVisible) {
				$link.html('Свернуть комментарии');
			} else {
				$link.html('Показать комментарии (' + commentsCount + ')');
			}
			$link.show();
		}
	},
	updateAttachments: function ($item) {
		let urls = parser.findLinks($item.text());
		if (!urls) return;

		let mrs = [];

		for (var i = 0; i < urls.length; i++) {
			let url = urls[i];
			if (parser.isMRUrl(url)) {
				mrs.push(url);
			}
		}

		if (mrs.length > 0) {
			let ul = $('.merge-requests', $item.parent('.formatted-desc'));
			mrs.forEach(function (url, i, arr) {
				let li = $(document.createElement("li"));
				li.append(preloader.getNewIndicatorSmall());
				ul.append(li);
				srv.attachments.getMRInfo(url, function (res) {
					if (res.success) {
						if (res.data) {
							let mr = res.data;
							let icon = comments.mrStateIcons[mr.state];
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
	}
}