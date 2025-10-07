$(document).ready(function ($) {
	if ("onhashchange" in window) window.onhashchange = highlightComment;

	$('.comments-list .comments-list-item .comment-text').each(function (index, val) {
		comments.updateAttachments($(val));
	});

	function highlightComment() {
		let hash = window.location.hash;
		if (hash.substr(0, 9) === '#comment-') {
			$(".comments-list .comments-list-item").has("a.anchor[id=" + hash.substr(1) + "]")
				.find(".card").css("backgroundColor", "#e0cffc")
				.animate({ backgroundColor: "#ffffff" }, 1200);
		}
	}

	highlightComment();
	comments.init();
});

const comments = {
    saveableForm: null,
    mrStateIcons: {
        merged: 'fa-check-circle',
        opened: 'fa-clock',
        closed: 'fa-times-circle',
    },
    pipelineStateIcons: {
        success: 'fa-check-circle',
        failed: 'fa-times-circle',
        running: 'fa-spinner fa-spin',
        pending: 'fa-clock',
        canceled: 'fa-ban',
        skipped: 'fa-forward',
        manual: 'fa-hand-paper',
        preparing: 'fa-cog fa-spin',
        created: 'fa-clock',
        scheduled: 'fa-calendar'
    },
	init: function () {
		const storeKey = typeof issuePage !== 'undefined' ? 'comment-' + issuePage.getIssueId() : 'comment';
		comments.saveableForm = new SaveableCommentForm(
			'#addCommentForm .comment-text-field',
			'#comments form.add-comment input[name=requestChanges]',
			storeKey,
			storeKey + '_type'
		);

		comments.invalidateLinks();
		comments.initAddForm();
	},
	initAddForm: function () {
		comments.saveableForm.init((_, requestChanges) => {
			comments.showCommentForm(requestChanges);
		});
	},
	clearForm: function () {
		comments.saveableForm.clear();
	},
	showCommentForm: function (requestChanges = false) {
		$('#comments form.add-comment').show();
		$('#comments .links-bar').hide();
		$('#comments form.add-comment textarea[name=commentText]').trigger('focus');
		$('#comments form.add-comment input[name=requestChanges]').prop('checked', requestChanges);
	},
	hideCommentForm: function (clear = true) {
		if (clear) comments.clearForm();
		$('#comments form.add-comment').hide();
		$('#comments .links-bar').show();
		$('#addCommentTabs').tabs({
			active: 0
		});
		$('#addCommentForm .preview-comment').empty();

		comments.invalidateLinks();
	},
	toggleCommentForm: function () {
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
        let pipelines = [];

        for (var i = 0; i < urls.length; i++) {
            let url = urls[i];
            if (parser.isMRUrl(url)) {
                mrs.push(url);
            } else if (parser.isPipelineUrl(url)) {
                pipelines.push(url);
            }
        }

        if (mrs.length > 0) {
            const $ul = $('.merge-requests', $item.parent('.formatted-desc'));
			mrs.forEach(function (url, i, arr) {
				const $el = $(document.createElement('div'));
				$ul.append($(document.createElement("li")).addClass('mt-2').append($el));

				$el.append(preloader.getNewIndicatorSmall());
				srv.attachments.getMRInfo(url, function (res) {
					if (res.success) {
						if (res.data) {
							const mr = res.data;
							const icon = comments.mrStateIcons[mr.state];
							$el.attr('class', `merge-request ${mr.state}`)
								.empty()
								.append('<i class="state-icon fas ' + icon + '"></i>')
								.append('MR <a href="' + mr.url + '">!' + mr.internalId + '</a>');
							if (mr.mergedAt) {
								$el.append(' <span class="merged-at small" title="Дата влития">(<i class="fas fa-code-pull-request" ></i> ' + lpm.format.date(mr.mergedAt) + ')</span>');
							}
						} else {
							$el.remove();
						}
					} else {
						$el.empty().text(typeof res.error != 'undefined' ?
							res.error : 'Не удалось получить данные MR.');
					}
                });
            });
        }

        if (pipelines.length > 0) {
            const $ul = $('.pipelines', $item.parent('.formatted-desc'));
            pipelines.forEach(function (url) {
                const $li = $(document.createElement('li')).addClass('list-group-item py-1 px-1 mt-2 rounded-2 d-flex align-items-center');
                $ul.append($li);

                $li.append(preloader.getNewIndicatorSmall());
                srv.attachments.getPipelineInfo(url, function (res) {
                    if (res.success) {
                        if (res.data) {
                            const p = res.data;
                            const icon = comments.pipelineStateIcons[p.status] || 'fa-question-circle';

                            // map status to contextual classes
                            const ctxMap = {
                                success: { item: 'list-group-item-success', icon: 'text-success', badge: 'badge bg-success' },
                                failed: { item: 'list-group-item-danger', icon: 'text-danger', badge: 'badge bg-danger' },
                                canceled: { item: 'list-group-item-secondary', icon: 'text-secondary', badge: 'badge bg-secondary' },
                                skipped: { item: 'list-group-item-secondary', icon: 'text-secondary', badge: 'badge bg-secondary' },
                                running: { item: 'list-group-item-info', icon: 'text-info', badge: 'badge bg-info text-dark' },
                                pending: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
                                preparing: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
                                created: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
                                scheduled: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
                                manual: { item: 'list-group-item-primary', icon: 'text-primary', badge: 'badge bg-primary' },
                            };
                            const ctx = ctxMap[p.status] || { item: '', icon: 'text-muted', badge: 'badge bg-light text-dark' };
                            const statusText = (p.status || '').replace(/_/g, ' ');

                            $li.addClass(ctx.item)
                                .empty()
                                .append('<i class="fas ' + icon + ' me-2 ' + ctx.icon + '"></i>')
                                .append('Pipeline <a href="' + p.url + '" class="ms-1">#' + p.id + '</a> ')
                                .append('<span class="' + ctx.badge + ' ms-2">' + statusText + '</span>');
                            if (p.ref) {
                                $li.append(' <span class="small text-muted ms-2" title="Ветка/тег"><i class="fas fa-code-branch"></i> ' + p.ref + '</span>');
                            }
                            if (p.finishedAt) {
                                $li.append(' <span class="small text-muted ms-2 fw-bold" title="Дата завершения">(<i class="far fa-calendar-check"></i> ' + lpm.format.date(p.finishedAt) + ')</span>');
                            }
                        } else {
                            $li.remove();
                        }
                    } else {
                        $li.empty().text(typeof res.error != 'undefined' ?
                            res.error : 'Не удалось получить данные Pipeline.');
                    }
                });
            });
        }
    }
}

function SaveableCommentForm(inputSelector, checkboxSelector, storeKey, checkboxStoreKey) {
	this.storeKey = storeKey;
	this.checkboxStoreKey = checkboxStoreKey;

	this.init = function (onRestore) {
		const commentTextField = $(inputSelector);
		if (commentTextField.length == 0) return;

		const checkboxField = $(checkboxSelector)

		const storeKey = this.storeKey;
		const checkboxStoreKey = this.checkboxStoreKey;

		const savedText = window.localStorage.getItem(storeKey);
		if (savedText) {
			const checkboxValue = window.localStorage.getItem(checkboxStoreKey) == 1;
			commentTextField.val(savedText);
			checkboxField.prop('checked', checkboxValue);

			onRestore(savedText, checkboxValue);
		}

		commentTextField.on('input', (e) => {
			let text = e.target.value;
			window.localStorage.setItem(storeKey, text);
			window.localStorage.setItem(checkboxStoreKey, checkboxField.is(':checked') ? 1 : 0);
		});

		checkboxField.on('click', (e) => {
			window.localStorage.setItem(checkboxStoreKey, checkboxField.is(':checked') ? 1 : 0);
		});
	}

	this.clear = function () {
		$(inputSelector).val('');
		window.localStorage.removeItem(this.storeKey);
		window.localStorage.removeItem(this.checkboxStoreKey);
	}

}
