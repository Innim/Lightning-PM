$(document).ready(function ($) {
	if ("onhashchange" in window) window.onhashchange = highlightComment;

	$('.comments-list .comments-list-item .comment-text').each(function (index, val) {
		comments.updateAttachments($(val));
	});

	function highlightComment() {
		let hash = window.location.hash;
		if (hash.substr(0, 9) === '#comment-') {
			let $card = $(".comments-list .comments-list-item")
				.has("a.anchor[id=" + hash.substr(1) + "]").find(".card");
			$card.removeClass('highlight-fade');
			// Форсируем reflow, чтобы повторное добавление класса перезапускало анимацию.
			if ($card[0]) void $card[0].offsetWidth;
			$card.addClass('highlight-fade').one('animationend', function () {
				$(this).removeClass('highlight-fade');
			});
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
    // Интервал автообновления незавершенных статусов pipeline/job, мс.
    gitlabStatusPollMs: 10000,
    // Базовый набор классов элемента статуса pipeline/job.
    gitlabStatusItemClass: 'list-group-item py-1 px-1 mt-2 rounded-2 d-flex align-items-center',
    // Финальные статусы: по их достижении опрос прекращается.
    finalGitlabStatuses: ['success', 'failed', 'canceled', 'skipped'],
    // Иконки статусов pipeline/job (наборы статусов у них совпадают).
    gitlabStateIcons: {
        success: 'fa-check-circle',
        failed: 'fa-times-circle',
        running: 'fa-spinner fa-spin',
        pending: 'fa-clock',
        waiting_for_resource: 'fa-hourglass-half',
        canceled: 'fa-ban',
        skipped: 'fa-forward',
        manual: 'fa-hand-paper',
        preparing: 'fa-cog fa-spin',
        created: 'fa-clock',
        scheduled: 'fa-calendar'
    },
    // Контекстные классы (цвет фона, иконки и бейджа) по статусу pipeline/job.
    gitlabStatusContexts: {
        success: { item: 'list-group-item-success', icon: 'text-success', badge: 'badge bg-success' },
        failed: { item: 'list-group-item-danger', icon: 'text-danger', badge: 'badge bg-danger' },
        canceled: { item: 'list-group-item-secondary', icon: 'text-secondary', badge: 'badge bg-secondary' },
        skipped: { item: 'list-group-item-secondary', icon: 'text-secondary', badge: 'badge bg-secondary' },
        running: { item: 'list-group-item-info', icon: 'text-info', badge: 'badge bg-info text-dark' },
        pending: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
        waiting_for_resource: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
        preparing: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
        created: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
        scheduled: { item: 'list-group-item-warning', icon: 'text-warning', badge: 'badge bg-warning text-dark' },
        manual: { item: 'list-group-item-primary', icon: 'text-primary', badge: 'badge bg-primary' },
    },
    isFinalGitlabStatus: function (status) {
        return comments.finalGitlabStatuses.indexOf(status) !== -1;
    },
    // Возвращает иконку, контекстные классы и текст для статуса pipeline/job.
    gitlabStatusView: function (status) {
        return {
            icon: comments.gitlabStateIcons[status] || 'fa-question-circle',
            ctx: comments.gitlabStatusContexts[status] || { item: '', icon: 'text-muted', badge: 'badge bg-light text-dark' },
            text: (status || '').replace(/_/g, ' ')
        };
    },
    // Отрисовывает статус Pipeline в элемент $li (перерисовка на месте безопасна).
    renderPipeline: function ($li, p) {
        const view = comments.gitlabStatusView(p.status);
        $li.attr('class', comments.gitlabStatusItemClass).addClass(view.ctx.item)
            .empty()
            .append('<i class="fas ' + view.icon + ' me-2 ' + view.ctx.icon + '"></i>')
            .append('Pipeline <a href="' + p.url + '" class="ms-1">#' + p.id + '</a> ')
            .append('<span class="' + view.ctx.badge + ' ms-2">' + view.text + '</span>');
        if (p.ref) {
            $li.append(' <span class="small text-muted ms-2" title="Ветка/тег"><i class="fas fa-code-branch"></i> ' + p.ref + '</span>');
        }
        if (p.finishedAt) {
            $li.append(' <span class="small text-muted ms-2 fw-bold" title="Дата завершения">(<i class="far fa-calendar-check"></i> ' + lpm.format.date(p.finishedAt) + ')</span>');
        }
    },
    // Отрисовывает статус Job в элемент $li (перерисовка на месте безопасна).
    renderJob: function ($li, j) {
        const view = comments.gitlabStatusView(j.status);
        const name = j.name ? ' <strong>' + $('<span>').text(j.name).html() + '</strong>' : '';
        // Ведущая иконка-«кубик» отличает джобу от пайплайна с первого взгляда.
        $li.attr('class', comments.gitlabStatusItemClass).addClass(view.ctx.item)
            .empty()
            .append('<i class="fas fa-cube me-2 text-muted" title="Job"></i>')
            .append('<i class="fas ' + view.icon + ' me-2 ' + view.ctx.icon + '"></i>')
            .append('<span>Job' + name + '</span>')
            .append('<a href="' + j.url + '" class="ms-1">#' + j.id + '</a> ')
            .append('<span class="' + view.ctx.badge + ' ms-2">' + view.text + '</span>');
        if (j.stage) {
            $li.append(' <span class="small text-muted ms-2" title="Стадия"><i class="fas fa-layer-group"></i> ' + $('<span>').text(j.stage).html() + '</span>');
        }
        if (j.finishedAt) {
            $li.append(' <span class="small text-muted ms-2 fw-bold" title="Дата завершения">(<i class="far fa-calendar-check"></i> ' + lpm.format.date(j.finishedAt) + ')</span>');
        }
    },
    // Загружает статус pipeline/job в $li и, пока он не финальный, периодически
    // обновляет его на месте (без перезагрузки страницы). fetch(onResult) выполняет
    // запрос, render($li, data) отрисовывает результат.
    watchGitlabStatus: function ($li, fetch, render, notFoundText) {
        let rendered = false;
        const poll = function () {
            fetch(function (res) {
                // Блок удален из DOM (комментарии перерисованы) — прекращаем опрос.
                if (!$li.closest('body').length) return;

                if (res.success) {
                    if (res.data) {
                        render($li, res.data);
                        rendered = true;
                        if (!comments.isFinalGitlabStatus(res.data.status)) {
                            setTimeout(poll, comments.gitlabStatusPollMs);
                        }
                    } else {
                        $li.remove();
                    }
                } else if (rendered) {
                    // Временная ошибка при обновлении — оставляем прошлые данные, пробуем снова.
                    setTimeout(poll, comments.gitlabStatusPollMs);
                } else {
                    $li.empty().text(typeof res.error != 'undefined' ? res.error : notFoundText);
                }
            });
        };
        poll();
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
		comments.initFileInputs();
		comments.initAddForm();
	},
	initFileInputs: function () {
		$(document).on('change', '.comment-files-list input[name="commentFiles[]"]', function () {
			comments.ensureFileInput($(this).closest('.comment-files-list'));
		});
		$(document).on('click', '.remove-comment-files', function (e) {
			e.preventDefault();
			const $list = $(this).closest('.comment-files-list');
			const $item = $(this).closest('.comment-file-input');
			if ($('.comment-file-input', $list).length > 1) {
				$item.remove();
			} else {
				$('input[name="commentFiles[]"]', $item).val('');
			}
			comments.ensureFileInput($list);
		});

		$(document).on('paste', '.comment-input-text-tabs', function (e) {
			comments.handlePaste(e);
		});

		$('.comment-files-list').each(function () {
			comments.ensureFileInput($(this));
		});
	},
	handlePaste: function (event) {
		const orig = event.originalEvent || event;
		const clipboard = orig.clipboardData;
		if (!clipboard || !clipboard.items) return;

		const files = [];
		for (let i = 0; i < clipboard.items.length; i++) {
			const item = clipboard.items[i];
			if (item.kind === 'file' && item.type.indexOf('image/') === 0) {
				const f = item.getAsFile();
				if (f) files.push(f);
			}
		}
		if (!files.length) return;

		const $tabs = $(event.target).closest('.comment-input-text-tabs');
		if (!$tabs.length) return;

		const $list = $tabs.parent().find('.comment-files-list').first();
		if (!$list.length) return;

		event.preventDefault();
		comments.addFiles($list, files);
	},
	addFiles: function ($list, files) {
		if (!$list || !$list.length || !files || !files.length) return;
		if (typeof DataTransfer === 'undefined') return;

		const maxFiles = parseInt($list.data('maxFiles'), 10) || 0;

		let filesCount = 0;
		$('input[name="commentFiles[]"]', $list).each(function () {
			filesCount += this.files ? this.files.length : 0;
		});

		const allowed = maxFiles ? Math.max(0, maxFiles - filesCount) : files.length;
		if (allowed === 0) return;
		const toAdd = files.slice(0, allowed);

		let $targetInput = $('.comment-file-input input[name="commentFiles[]"]', $list).filter(function () {
			return !this.files || this.files.length === 0;
		}).first();

		if (!$targetInput.length) {
			const $newItem = $('.comment-file-input', $list).first().clone();
			$('input[name="commentFiles[]"]', $newItem).val('').removeAttr('id');
			$('.remove-comment-files', $newItem).addClass('d-none');
			$list.append($newItem);
			$targetInput = $('input[name="commentFiles[]"]', $newItem);
		}

		const input = $targetInput[0];
		const dt = new DataTransfer();
		toAdd.forEach(f => dt.items.add(f));
		input.files = dt.files;

		$targetInput.trigger('change');
	},
	ensureFileInput: function ($list) {
		if (!$list || !$list.length) return;

		let filesCount = 0;
		$('input[name="commentFiles[]"]', $list).each(function () {
			const count = this.files ? this.files.length : 0;
			filesCount += count;
			$(this).siblings('.remove-comment-files').toggleClass('d-none', count === 0);
		});

		const $emptyItems = $('.comment-file-input', $list).filter(function () {
			const input = $('input[name="commentFiles[]"]', this)[0];
			return !input || !input.files || input.files.length === 0;
		});
		const maxFiles = parseInt($list.data('maxFiles'), 10) || 0;

		if (maxFiles && filesCount >= maxFiles) {
			$emptyItems.remove();
			return;
		}

		if ($emptyItems.length === 0) {
			const $newItem = $('.comment-file-input', $list).first().clone();
			$('input[name="commentFiles[]"]', $newItem).val('').removeAttr('id');
			$('.remove-comment-files', $newItem).addClass('d-none');
			$list.append($newItem);
		} else if ($emptyItems.length > 1) {
			$emptyItems.slice(1).remove();
		}
	},
	getFiles: function ($container) {
		const files = [];
		$('input[name="commentFiles[]"]', $container).each(function () {
			Array.prototype.forEach.call(this.files || [], function (file) {
				files.push(file);
			});
		});
		return files;
	},
	clearFiles: function ($container) {
		$('.comment-files-list', $container).each(function () {
			const $list = $(this);
			$('.comment-file-input', $list).slice(1).remove();
			$('input[name="commentFiles[]"]', $list).val('');
			comments.ensureFileInput($list);
		});
	},
	initAddForm: function () {
		comments.saveableForm.init((_, requestChanges) => {
			comments.showCommentForm(requestChanges);
		});
	},
	clearForm: function () {
		comments.saveableForm.clear();
		comments.clearFiles($('#addCommentForm'));
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
		const firstCommentTab = document.querySelector('#addCommentTabs .nav-link');
		if (firstCommentTab) bootstrap.Tab.getOrCreateInstance(firstCommentTab).show();
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
        let jobs = [];

        for (var i = 0; i < urls.length; i++) {
            let url = urls[i];
            if (parser.isMRUrl(url)) {
                mrs.push(url);
            } else if (parser.isPipelineUrl(url)) {
                pipelines.push(url);
            } else if (parser.isJobUrl(url)) {
                jobs.push(url);
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
							const isDraft = mr.draft && mr.state === 'opened';
							const icon = isDraft ? 'fa-pencil' : comments.mrStateIcons[mr.state];
							$el.attr('class', `merge-request ${mr.state}` + (isDraft ? ' draft' : ''))
								.empty()
								.append('<i class="state-icon fas ' + icon + '"></i>')
								.append('MR <a href="' + mr.url + '">!' + mr.internalId + '</a>');
							if (isDraft) {
								$el.append(' <span class="badge bg-secondary text-white ms-1">Draft</span>');
							}
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
                const $li = $(document.createElement('li')).addClass(comments.gitlabStatusItemClass);
                $ul.append($li);

                $li.append(preloader.getNewIndicatorSmall());
                comments.watchGitlabStatus($li, function (onResult) {
                    srv.attachments.getPipelineInfo(url, onResult);
                }, comments.renderPipeline, 'Не удалось получить данные Pipeline.');
            });
        }

        if (jobs.length > 0) {
            const $ul = $('.jobs', $item.parent('.formatted-desc'));
            jobs.forEach(function (url) {
                const $li = $(document.createElement('li')).addClass(comments.gitlabStatusItemClass);
                $ul.append($li);

                $li.append(preloader.getNewIndicatorSmall());
                comments.watchGitlabStatus($li, function (onResult) {
                    srv.attachments.getJobInfo(url, onResult);
                }, comments.renderJob, 'Не удалось получить данные Job.');
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
