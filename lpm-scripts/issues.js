// Меню копирования ссылок поднимается отдельным обработчиком готовности и первым:
// оно есть на страницах, где часть остальной инициализации неприменима.
$(document).ready(function () {
    initIssueCopyMenus();
});

$(document).ready(
    function () {
        //$( '#issueView .comments form.add-comment' ).hide();
        issuePage.projectId = parseInt($('#issueProjectID').val());
        if ($('#issueInfo').length) {
            issuePage.idInProject = $('#issueInfo').data('idInProject');
            issuePage.labels = $('#issueInfo').data('labels').split(',');
        }
        issuePage.updatePriorityVals();
        issuePage.scrumColUpdateInfo();

        $(document).on('click', '#issuesSortMenu ~ .dropdown-menu [data-sort]', function () {
            states.setState($(this).data('sort'));
        });

        // Поиск идёт по всей базе задач проекта, а не по показанным строкам,
        // поэтому смена области поиска перезапрашивает список с сервера
        $(document).on('change', '#issuesSearchForm select[name=scope]', function () {
            $(this).closest('form').submit();
        });

        // Раскрытие по иконке экономит клик только если курсор сразу оказывается
        // в поле. Событие срабатывает лишь на раскрытие пользователем: форму,
        // раскрытую сервером при активном поиске, фокус не перехватывает.
        $(document).on('shown.bs.collapse', '#issuesSearchPanel', function () {
            const input = this.querySelector('input[name=search]');
            if (input) {
                input.focus();
                input.select();
            }
        });

        // По кнопке «назад» браузер возвращает страницу такой, какой её оставили:
        // в полях стоит то, что пользователь выбрал перед уходом, а список пришёл
        // с сервера по адресу страницы. reset() возвращает полям значения из
        // разметки, то есть ровно те, по которым список и отобран.
        $(window).on('pageshow', function () {
            const form = document.getElementById('issuesSearchForm');
            if (form) form.reset();
        });

        $(document).on('click', '#issuesList .member-list a', function (e) {
            const memberId = $(e.currentTarget).data('memberId');
            issuePage.showIssuesByUser(memberId);
        });

        $(document).on('shown.bs.tab', '.comment-input-text-tabs [data-bs-toggle="tab"]', function (e) {
            const $panel = $(e.target.getAttribute('data-bs-target'));
            if ($panel.hasClass('preview-tab')) {
                issuePage.previewComment($panel.closest('.comment-input-text-tabs'));
            }
        });

        // BEGIN -- Настройка формы 

        $('#issueForm .tags-line a.tag, #issueForm .desc-toolbar .tag').on('click', function (e) {
            let a = $(e.currentTarget);
            let input = $('#issueForm textarea[name=desc]');
            let type = a.data('type');

            if (type) {
                switch (type) {
                    case 'link':
                        insertFormattingLink(input);
                        break;
                }
            } else {
                // Extended: allow custom before/after wrappers
                const before = a.data('before');
                const after = a.data('after');
                if (before !== undefined || after !== undefined) {
                    const beforeStr = before !== undefined ? before.replace('\\n', '\n') : '';
                    const afterStr = after !== undefined ? after.replace('\\n', '\n') : '';
                    insertFormatting(input, beforeStr, afterStr, 0);
                } else {
                    let marker = a.data('marker');
                    if (marker) insertFormattingMarker(input, marker, a.data('single'));
                }
            }

            // Programmatic edits don't fire a native input event — notify listeners.
            input.trigger('input');
        });

        // Insert the description template configured in the app settings
        $('#issueForm .apply-desc-template').on('click', function () {
            const $field = $('#issueForm textarea[name=desc]');
            const el = $field[0];
            const template = String($('#issueForm').data('descTemplate') || '');
            if (!template.trim()) {
                return;
            }

            // The template is split at its first blank line: text already typed
            // in the field goes between the two halves, i.e. into the first section.
            // The tail is re-indented with one blank line so it stays separated from
            // that text whatever spacing the configured template itself uses.
            const splitAt = template.indexOf("\n\n");
            const tmplStart = splitAt === -1 ? template : template.slice(0, splitAt + 2);
            const tmplTail = splitAt === -1 ? '' : template.slice(splitAt + 2);
            const tmplEndSection = tmplTail === '' ? '' : "\n\n" + tmplTail.replace(/^\n+/, '');

            const current = $field.val() || '';

            // An already inserted template is recognised by its Markdown headings:
            // matching any template line instead would let a common one ("TODO:")
            // occur in ordinary text and silently turn the button into a no-op.
            // A template without headings has no such line and is matched as a whole.
            const headings = template.split("\n")
                .map(function (line) { return line.trim(); })
                .filter(function (line) { return /^#{1,6}\s+\S/.test(line); });
            const markers = headings.length ? headings : [template.trim()];
            const hasTemplate = markers.some(function (marker) {
                return current.indexOf(marker) !== -1;
            });

            // Empty field: insert the whole template and place caret after tmplStart
            if (!current.trim()) {
                $field.val(template);
                try {
                    const caret = tmplStart.length;
                    el.focus();
                    if (typeof el.selectionStart === 'number') {
                        el.selectionStart = el.selectionEnd = caret;
                    }
                } catch (_) { /* ignore caret errors */ }
                $field.trigger('input');
                return;
            }

            // If already has template anywhere, do not insert a second one
            if (hasTemplate) {
                el.focus();
                return;
            }

            // Determine selection; if none, wrap whole content
            let selStart = 0, selEnd = current.length;
            if (typeof el.selectionStart === 'number') {
                selStart = el.selectionStart;
                selEnd = el.selectionEnd;
                if (selEnd === selStart) {
                    selStart = 0;
                    selEnd = current.length;
                }
            }

            const before = current.slice(0, selStart).trimEnd();
            const middle = current.slice(selStart, selEnd).trim();
            const after = current.slice(selEnd).trimStart();

            const newValueStart = before + (before ? "\n\n" : "") + tmplStart + middle;
            const newValue = newValueStart + tmplEndSection + after;
            const caretPos = newValueStart.length;

            $field.val(newValue);
            try {
                el.focus();
                if (typeof el.selectionStart === 'number') {
                    el.selectionStart = el.selectionEnd = caretPos;
                }
            } catch (_) { /* ignore caret errors */ }
            $field.trigger('input');
        });

        // Toggle between description editor and rendered Markdown preview
        $('#issueForm .toggle-desc-preview').on('click', function () {
            issuePage.toggleDescPreview($('#issueForm'));
            this.blur();
        });

        // Live character counter in the editor status bar
        $('#issueForm').on('input', 'textarea[name=desc]', function () {
            issuePage.updateDescCounter($('#issueForm'));
        });
        issuePage.updateDescCounter($('#issueForm'));

        // Keyboard shortcut: Ctrl/Cmd + Shift + M
        $('#issueForm textarea[name=desc]').on('keydown', function (e) {
            const key = (e.key || '').toLowerCase();
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && key === 'm') {
                e.preventDefault();
                $('#issueForm .apply-desc-template').trigger('click');
            }
        });

        $('#issueForm input[name=hours]').on('focus', function (e) {
            let field = $(e.currentTarget);
            if (!field.val()) {
                var sum = 0;
                $('#issueMembers input.member-sp').each(function (i) {
                    if (sum === -1)
                        return;

                    let val = $(this).val();
                    if (val === '') {
                        sum = -1;
                        return;
                    }

                    let memberSp = val === '1/2' ? .5 : parseFloat(val);
                    sum += memberSp;
                });

                if (sum > 0) {
                    field.val(sum);
                    setTimeout(function () {
                        field.select();
                    }, 50);
                }
            }
        });

        const textInputs = [
            '#issueForm textarea[name=desc]',
            'form.add-comment textarea[name=commentText]',
            'form.pass-test #passTestComment textarea.comment-text-field'
        ];

        setupAutoComplete(textInputs);
        setupPasteTransformer(textInputs);

        // Настройка формы -- END

        // BEGIN -- Комментарии

        $(document).on('click', '.delete-comment', function () {
            const id = $(this).data('commentId');
            const el = $(this);
            const branchName = $(this).data('branchName');
            const doDelete = (alsoDeleteBranch) => {
                preloader.show();
                issuePage.deleteComment(id, alsoDeleteBranch, function (res) {
                    preloader.hide();
                    if (res) {
                        el.parents('div.comments-list-item').remove();
                    }
                });
            };

            lpm.dialog.confirm({
                text: 'Удалить комментарий?',
                yesLabel: 'Удалить',
                onYes: function () {
                    // Если у комментария есть ветка — отдельным окном уточняем, удалять
                    // ли и её. Окно откроется после закрытия предыдущего.
                    if (branchName) {
                        lpm.dialog.confirm({
                            title: 'Удаление ветки',
                            text: `Также удалить ветку <code>${branchName}</code> в репозитории?`,
                            yesLabel: 'Да',
                            noLabel: 'Нет',
                            onYes: function () { doDelete(true); },
                            onNo: function () { doDelete(false); }
                        });
                    } else {
                        doDelete(false);
                    }
                }
            });
        });

        $(document).on('click', '.resolve-comment', function () {
            const id = $(this).data('commentId');
            const item = $(this).parents('div.comments-list-item');
            lpm.dialog.confirm({
                text: 'Отметить баг решённым? Задача перестанет считаться содержащей баг.',
                yesLabel: 'Отметить',
                onYes: function () {
                    preloader.show();
                    issuePage.resolveComment(id, function (res) {
                        preloader.hide();
                        if (res) {
                            item.html(res.html);
                            comments.updateAttachments($('.comment-text', item));
                            attachments.update($('.block-with-attachments', item));
                            initIssueLinkPreviews(item);
                            highlightCodeBlocks(item);
                        }
                    });
                }
            });
        });

        // Комментарии -- END

        if (!$('#is-moderator').val()) {
            $('.delete-comment').each(function (index) {
                const elementId = $(this).attr('id');
                const startTime = $(this).data('time');
                hideElementAfterDelay(elementId, startTime, lpmOptions.commentDeleteWindow);
            });
        }

        bindFormattingHotkeys('#issueForm form textarea[name=desc]');
        bindFormattingHotkeys('form.add-comment textarea[name=commentText]');
        bindFormattingHotkeys('form.pass-test #passTestComment textarea.comment-text-field');
    }
);

/**
 * Инициализирует меню копирования ссылок на задачу (список задач, Scrum доска).
 *
 * Экземпляр Dropdown создаётся заранее только ради popperConfig: его нельзя задать
 * data-атрибутом, а с настройками по умолчанию у нижней кромки окна Popper развернёт
 * меню вверх и накроет им строку или стикер. Запасные позиции уводят меню вбок.
 */
function initIssueCopyMenus() {
    document.querySelectorAll('.issue-copy > [data-bs-toggle="dropdown"]').forEach(function (toggle) {
        // Функцию зовут заново после каждой подгруженной порции списка, а Bootstrap
        // допускает только один компонент на элемент: уже поднятые меню пропускаем
        if (bootstrap.Dropdown.getInstance(toggle)) return;

        new bootstrap.Dropdown(toggle, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, {
                    modifiers: defaultConfig.modifiers.concat([{
                        name: 'flip',
                        options: { fallbackPlacements: ['right-end', 'left-end', 'top-start'] }
                    }])
                });
            }
        });
    });
}

function bindFormattingHotkeys(selector) {
    $(selector).keydown(function (e) {
        if (e.ctrlKey || e.metaKey) {
            var code = e.originalEvent.code;
            const hasSelection = !(typeof this.selectionStart === 'undefined' || this.selectionStart == this.selectionEnd);
            switch (code) {
                case 'KeyB':
                    if (!hasSelection) return; // requires selection
                    insertFormattingMarker(this, '*');
                    break;
                case 'KeyI':
                    if (!hasSelection) return; // requires selection
                    insertFormattingMarker(this, '_');
                    break;
                case 'KeyU':
                    if (!hasSelection) return; // requires selection
                    insertFormattingMarker(this, '__');
                    break;
                case 'KeyG':
                    insertFormattingMarker(this, '> ', true);
                    break;
                case 'KeyH':
                    if (hasSelection) {
                        insertFormattingMarker(this, '### ', true);
                    } else {
                        insertHeaderAtLineStart(this, '### ');
                    }
                    break;
                case 'KeyK':
                    if (!hasSelection) return; // requires selection
                    insertFormattingLink(this);
                    break;
                default:
                    return;
            }

            e.stopImmediatePropagation();
            e.preventDefault();
        }
    });
}

function setupAutoComplete(selectors) {
    let tribute = new Tribute({
        collection: [
            createMembersAutoComplete(),
            createIssuesAutoComplete(),
        ]
    });

    for (var i = 0; i < selectors.length; i++) {
        tribute.attach($(selectors[i]).get());
    }
}


function createMembersAutoComplete() {
    var members = null;
    return {
        trigger: '@',
        selectTemplate: function (item) {
            let data = item.original;
            return '[@' + data.key + '](user:' + data.id + ')';
        },
        values: function (text, cb) {
            if (members !== null) {
                cb(members);
                return;
            }

            issuePage.loadMembers(function (list) {
                if (!list) {
                    cb([])
                } else {
                    members = [];
                    for (var i = 0; i < list.length; i++) {
                        let user = list[i];
                        let name = user.nick ? user.nick : user.firstName;

                        members[i] = { key: name, value: name, id: user.userId };
                    }
                    cb(members);
                }
            });
        },
    }
}

function createIssuesAutoComplete() {
    var cache = {};
    return {
        trigger: '#',
        searchOpts: {
            skip: true,
        },
        selectTemplate: function (item) {
            let data = item.original;
            return '[#' + data.key + '](' + data.url + ')';
        },
        menuItemTemplate: function (item) {
            let data = item.original;
            return '#' + data.key + ' ' + data.value;
        },
        noMatchTemplate: function () {
            return '<li>Задач не найдено.</li>';
        },
        values: function (text, cb) {
            if (!text) return;

            if (cache[text]) {
                cb(cache[text]);
                return;
            }

            srv.project.searchIssueNames(issuePage.projectId, text,
                function (res) {
                    if (res.success) {
                        let list = res.list.map((e) => {
                            return {
                                key: String(e.idInProject),
                                value: e.name,
                                url: e.url
                            };
                        });
                        cache[text] = list;
                        cb(list);
                    } else {
                        cb([]);
                        srv.err(res);
                    }
                });
        },
    };
}

function setupPasteTransformer(inputSelectors) {
    document.addEventListener('paste', function (event) {
        const target = event.target;

        if (!inputSelectors.some(sel => target.matches(sel))) return;

        // Only for input or textarea for now (do not support contenteditable)
        if (target.selectionStart == null || target.selectionEnd == null) return;

        const value = target.value;
        const start = target.selectionStart;
        const end = target.selectionEnd;

        const textBefore = value.slice(0, start);
        const textAfter = value.slice(end);

        // ignore if paste in link markdown URL part
        const isInsideMarkdownLink = textBefore.endsWith('](') && textAfter.startsWith(')');
        if (isInsideMarkdownLink) return;

        const clipboardData = event.clipboardData || window.clipboardData;
        const pastedText = clipboardData.getData('text');
        if (pastedText.length === 0) return;


        const trimmed = pastedText.trim();
        if (trimmed.length === 0) return;

        const selectedText = value.substring(start, end);
        
        const issueUrlPattern = `^${lpmOptions.issueUrlPattern}$`;
        const urlRegex = /^(https?:\/\/\S+)$/i;

        // Heuristic: determine if selection is appropriate to turn into a link text
        function selectionIsAppropriate() {
            if (!selectedText || selectedText.trim().length === 0) return false;
            // avoid if selection itself looks like a URL
            if (urlRegex.test(selectedText.trim())) return false;
            // avoid if selection contains markdown link special tokens
            if (/[\[\]\(\)]/.test(selectedText)) return false;
            // avoid if selection appears inside existing markdown link label or url
            const leftCtx = textBefore.slice(-120);
            const rightCtx = textAfter.slice(0, 120);
            const insideLabel = /\[[^\]]*$/.test(leftCtx) && /^\][^\)]*\)/.test(rightCtx);
            const insideUrl = /\]\([^\)]*$/.test(leftCtx) && /^\)/.test(rightCtx);
            return !(insideLabel || insideUrl);
        }

        const issueUrlMatch = trimmed.match(issueUrlPattern);

        // Special handling for issue URLs: auto-label with [#id] unless selection can be used
        if (issueUrlMatch) {
            event.preventDefault();

            const s = pastedText.indexOf(trimmed);
            const preSpace = pastedText.substring(0, s);
            const postSpace = pastedText.substring(s + trimmed.length);
            const label = selectionIsAppropriate() ? selectedText : `#${issueUrlMatch[2]}`;
            const markdownLink = `[${label}](${trimmed})`;

            const text = preSpace + markdownLink + postSpace;
            target.value = textBefore + text + textAfter;
            target.selectionStart = target.selectionEnd = start + text.length;
        } else {
             // If a URL is pasted and there is an appropriate selection, wrap the selection as link text
            const isGenericUrl = urlRegex.test(trimmed);
            if (isGenericUrl && selectionIsAppropriate()) {
                event.preventDefault();
                const s = pastedText.indexOf(trimmed);
                const preSpace = pastedText.substring(0, s);
                const postSpace = pastedText.substring(s + trimmed.length);
                const markdownLink = `[${selectedText}](${trimmed})`;
                const text = preSpace + markdownLink + postSpace;
                target.value = textBefore + text + textAfter;
                // caret after the inserted link
                const newCaret = (textBefore + text).length;
                target.selectionStart = target.selectionEnd = newCaret;
                return;
            }
        }
    });
}

const issuePage = {
    projectId: null,
    idInProject: null,
    labels: null,
    members: null,
    filterVm: null, 
    getStatus: () => $('#issueInfo').data('status'),
    isCompleted: () => issuePage.getStatus() == 2,
    getIssueId: () => $('#issueView input[name=issueId]').val(),
    getRevision: () => $('#issueView input[name=revision]').val(),
    copyIssue: () => {
        const $copyLinkedField = $("#createFromIssueCopyLinks", createFromIssue.element);
        issuePage.createIssueBy(
            (issueId) => 'copy-issue:' + issueId + ':' + ($copyLinkedField.prop("checked") ? 1 : 0),
            'copy'
        );
    },
    finishedIssue: () => { 
        const $kindField = $('#createFromIssueTargetKind', createFromIssue.element);
        issuePage.createIssueBy(
            (issueId) => 'finished-issue:' + issueId + ':' + $kindField.val(), 
            'finished',
            (projectId) => {
                const isCurrent = projectId == issuePage.projectId;
                let needResetVal = false;
                $('option', $kindField).each((_, item) => {
                    let visible = true;
                    $option = $(item);
                    switch ($option.val()) {
                        case 'apply':
                            visible = !isCurrent;
                            break;
                        case 'finished':
                            visible = isCurrent;
                            break;
                    }

                    if (visible) {
                        $option.show();
                    } else {
                        $option.hide();
                        needResetVal = needResetVal || $option.prop('selected');
                    }
                });

                if (needResetVal) {
                    $('option', $kindField).each((_, item) => {
                        $option = $(item);
                        if ($option.css('display') !== 'none') {
                            $option.prop('selected', true);
                            return false;
                        }
                    })
                }
            },
        );
    },
    createIssueBy: function (hash, mode, onProjectChanged) {
        const issueId = this.getIssueId();
        createFromIssue.show(this.projectId, issueId, (targetProject) => {
            // Форма создания задачи - отдельная страница проекта
            // (ProjectPage::PUID_ISSUE_ADD), а задача-источник передаётся ей хэшем.
            const url = targetProject.url + '/add-issue#'
                + (typeof hash === 'function' ? hash(issueId) : hash + ':' + issueId);
            window.open(url, '_blank');
        }, mode, onProjectChanged);
    },
};

issuePage.loadMembers = function (handler) {
    if (issuePage.members != null) {
        handler(issuePage.members);
    } else {
        srv.project.getMembers(issuePage.projectId, function (res) {
            if (res.success) {
                issuePage.members = res.members;
                handler(issuePage.members);
            } else {
                handler(null);
                srv.err(res);
            }
        });
    }
}

/**
 * Роли, в которые можно быстро добавить себя на странице задачи:
 * row - строка с составом участников,
 * field - скрытое поле с идентификаторами участников (оно же поле в данных задачи).
 */
issuePage.addMeRoles = {
    member: { row: '.members-row', field: 'members' },
    tester: { row: '.testers-row', field: 'testers' },
    master: { row: '.masters-row', field: 'masters' },
};

/**
 * Дописывает участника в состав задачи на странице.
 * @param {string} role member|tester|master
 * @param {Object} res Ответ сервиса с полями userId, memberHtml, avatarUrl.
 */
issuePage.appendParticipant = function (role, res) {
    const roleInfo = issuePage.addMeRoles[role];
    if (!roleInfo) return;

    const $row = $('#issueInfo ' + roleInfo.row);
    const $input = $('input[name=' + roleInfo.field + ']', $row);
    const ids = $input.val();
    const hasParticipants = ids.length > 0;

    // Сервер отдаёт голую ссылку на пользователя, вид сам решает, как её показать:
    // обновлённый оборачивает в плашку с аватаром, прежний склеивает через запятую
    const $participants = $('.participants', $row);
    if ($('#issueInfo').hasClass('issue-card')) {
        $participants.append(Issue.renderUser({
            linkedName: res.memberHtml,
            avatarUrl: res.avatarUrl,
        }));
        if (!hasParticipants) {
            $('.text-muted', $participants).remove();
        }
    } else if (hasParticipants) {
        // Отступы разметки схлопнулись бы в пробел перед запятой,
        // поэтому обрезаем хвостовые пробелы
        $participants.html($participants.html().replace(/\s+$/, '') + ', ' + res.memberHtml);
    } else {
        $participants.html(res.memberHtml);
    }

    // Скрытые поля используются для заполнения формы редактирования,
    // поэтому их состав должен соответствовать отображаемому
    $input.val(hasParticipants ? ids + ',' + res.userId : String(res.userId));
    if (role === 'member') {
        const $spInput = $('input[name=membersSp]', $row);
        const sp = $spInput.val();
        $spInput.val(sp.length > 0 ? sp + ',0' : '0');
    }

    $('.add-me-to-issue', $row).hide();
};

/**
 * Добавляет текущего пользователя к участникам задачи в указанной роли.
 * @param {string} role member|tester|master
 */
issuePage.addMeToIssue = function (role) {
    const roleInfo = issuePage.addMeRoles[role];
    if (!roleInfo) return;

    preloader.show();
    srv.issue.addMeToIssue(issuePage.getIssueId(), role, function (res) {
        preloader.hide();

        if (!res.success) {
            srv.err(res);
            return;
        }

        issuePage.appendParticipant(role, res);
    });
};

/**
 * Обновляет видимость ссылок быстрого добавления себя в участники задачи.
 * @param {Issue} issue
 */
issuePage.updateAddMeLinks = function (issue) {
    Object.keys(issuePage.addMeRoles).forEach(function (role) {
        const list = issue[issuePage.addMeRoles[role].field];
        if (!list) return;

        const $link = $('#issueInfo ' + issuePage.addMeRoles[role].row + ' .add-me-to-issue');
        if (list.some(user => user.userId == lpInfo.userId)) {
            $link.hide();
        } else {
            $link.show();
        }
    });
};

/**
 * Перекрашивает кружки приоритета в списке задач - все, что есть на странице.
 * Приоритет в форме задачи к списку не относится и живёт отдельно
 * ({@see issueForm.setPriorityVal} в issue-form.js).
 */
issuePage.updatePriorityVals = function () {
    $('.priority-val.circle').each(function (i) {
        issuePage.updatePriorityVal($(this), parseInt($(this).data('value')));
        $(this).text('');
    });
};

/**
 * Обновляет кружок приоритета: цвет фона и значение, которое показывается
 * внутри кружка в режиме сортировки по приоритету.
 * @param {jQuery} $el
 * @param {Number} priority приоритет задачи (0..99)
 */
issuePage.updatePriorityVal = function ($el, priority) {
    $el.css({
        backgroundColor: issuePage.getPriorityColor(priority),
        color: issuePage.getPriorityTextColor(priority)
    });
    // Значение выводится из атрибута средствами CSS: внутри кружка оно должно
    // появляться только в режиме сортировки по приоритету.
    $el.attr('data-value-label', Issue.getPriorityDisplayVal(priority));
}

/**
 * Составляющие цвета приоритета по шкале синий - голубой - зелёный - жёлтый - красный.
 * @param {Number} val
 * @returns {Number[]} [r, g, b]
 */
issuePage.getPriorityRgb = function (val) {
    var v = Math.floor(val % 25 / 25 * 255);
    var r = 0;
    var g = 0;
    var b = 0;
    if (val < 25) {
        g = v;
        b = 255;
    } else if (val < 50) {
        g = 255;
        b = 255 - v;
    } else if (val < 75) {
        g = 255;
        r = v;
    } else {
        r = 255;
        g = 255 - v;
    }
    return [r, g, b];
};

issuePage.getPriorityColor = function (val) {
    var rgb = issuePage.getPriorityRgb(val);
    return 'rgba( ' + rgb[0] + ', ' + rgb[1] + ', ' + rgb[2] + ', 0.8 )';
};

/**
 * Цвет значения приоритета, читаемый на кружке этого приоритета:
 * на тёмном фоне (низкий приоритет и самый высокий) - белый, иначе чёрный.
 * @param {Number} val
 * @returns {String}
 */
issuePage.getPriorityTextColor = function (val) {
    var rgb = issuePage.getPriorityRgb(val);
    // Фон полупрозрачный, поэтому яркость считается для цвета, смешанного с белым фоном.
    var luma = 0.299 * rgb[0] + 0.587 * rgb[1] + 0.114 * rgb[2];
    return luma * 0.8 + 255 * 0.2 < 140 ? '#ffffff' : '#000000';
};

/**
 * Постраничный вывод списка задач проекта.
 *
 * Список бывает на тысячи задач, поэтому показывается порциями. Весь отбор -
 * поиск, область по статусу, фильтр по тегам и людям, сортировка - выполняется
 * на сервере, и счётчики берутся оттуда же: по показанным строкам ни отобрать,
 * ни посчитать правильно нельзя, пока показана лишь часть списка.
 */
issuePage.issuesList = {
    /** Блок подгрузки; его отсутствие означает страницу без списка задач. */
    el: null,
    projectId: 0,
    scope: '',
    search: '',
    sort: '',
    /** Статусы задач области поиска; пустой список - любые. */
    statuses: [],
    pageSize: 0,
    maxPageSize: 0,
    filter: { tags: [], memberIds: [], testerIds: [], multiMemberOnly: false },
    /** Сколько задач выборки уже запрошено у сервера. */
    offset: 0,
    /** Сколько задач показано на странице. Меньше offset, если порции пересеклись. */
    loaded: 0,
    total: 0,
    opened: 0,
    /** Отобран ли список сервером (поиск или область по статусу). */
    serverSelection: false,
    /** Выборка кончилась: сервер вернул пустую порцию. */
    exhausted: false,
    /**
     * Порядок выборки на сервере изменился после того, как список был показан.
     * Смещение порции по такому списку больше не годится - см. loadMore().
     */
    reordered: false,
    /**
     * Строка, оставленная на месте после смены статуса, выбивает окно
     * из порядка выборки отдельно от перестановок по приоритету: та, применив
     * место, снимает только свою пометку. Снимается перечитыванием списка.
     */
    statusReordered: false,
    /** Идёт ли сейчас запрос порции. */
    loading: false,
    /** Номер последнего запроса: ответы на устаревшие запросы отбрасываются. */
    requestId: 0,
    /**
     * Счётчик изменений порядка выборки: приоритет или статус любой задачи.
     * Место, посчитанное до очередного изменения, к новому порядку уже
     * не относится.
     */
    orderRevision: 0,
    reloadTimer: null,
    /**
     * Незаконченные перестановки строк: задача -> состояние запроса её места.
     * `timer` - отложенный запрос, `sent` - номер последнего отправленного
     * запроса, `applied` - номер последнего отработанного ответа,
     * `retries` - сколько раз запрос уже откладывался.
     */
    positions: {},
    /** Присланные места, которые ещё не применены: задача -> место в выборке. */
    positionResults: {},
    /** Задержка запроса места после смены приоритета, мс. */
    reorderDelay: 400,
    /**
     * Сколько раз запрос места можно отложить, пока список занят.
     * Предел нужен, чтобы запрос не откладывался бесконечно, если список
     * меняют чаще, чем считается место.
     */
    maxPositionRetries: 3,
    initialized: false,

    /**
     * Поднимает состояние выборки из разметки страницы при первом обращении.
     *
     * Состояние берётся лениво, а не по готовности документа: обработчики
     * готовности страниц списка зарегистрированы раньше этого файла и уже
     * спрашивают счётчики.
     * @returns {boolean} Есть ли на странице список задач.
     */
    ensureInit: function () {
        if (this.initialized) return this.el !== null;
        this.initialized = true;

        const el = document.getElementById('issuesListPaging');
        if (!el) return false;

        this.el = el;
        this.projectId = parseInt($('#projectView').data('projectId'));
        this.scope = el.getAttribute('data-scope') || '';
        this.search = el.getAttribute('data-search') || '';
        this.statuses = (el.getAttribute('data-statuses') || '')
            .split(',').filter((v) => v !== '').map((v) => parseInt(v));
        this.pageSize = parseInt(el.getAttribute('data-page-size')) || 0;
        this.maxPageSize = parseInt(el.getAttribute('data-max-page-size')) || 0;
        this.loaded = parseInt(el.getAttribute('data-loaded')) || 0;
        this.offset = this.loaded;
        this.total = parseInt(el.getAttribute('data-total')) || 0;
        this.opened = parseInt(el.getAttribute('data-opened')) || 0;
        this.serverSelection = !$('.project-stat .issues-selection').hasClass('d-none');

        return true;
    },

    /** Отобран ли список - тогда в статистике стоит размер выборки. */
    isSelection: function () {
        if (!this.ensureInit()) return false;

        return this.serverSelection || this.hasFilter();
    },

    hasFilter: function () {
        const filter = this.filter;
        return filter.tags.length > 0 || filter.memberIds.length > 0
            || filter.testerIds.length > 0 || filter.multiMemberOnly;
    },

    /** Применяет выбор фильтров: выборка меняется, поэтому список берётся заново. */
    applyFilter: function (state) {
        if (!this.ensureInit()) return;

        this.filter = {
            tags: state.tags.slice(),
            memberIds: state.memberIds.slice(),
            testerIds: state.testerIds.slice(),
            multiMemberOnly: state.multiMemberOnly
        };
        this.reload();
    },

    /** Включает режим сортировки. Повторный выбор того же режима запрос не делает. */
    setSort: function (sort) {
        if (!this.ensureInit() || sort === this.sort) return;

        this.sort = sort;
        this.reload();
    },

    /**
     * Запрашивает список заново, с первой порции.
     *
     * Запрос откладывается до конца такта: восстановление фильтра из адреса
     * задаёт теги и людей по отдельности, и это должно дать один запрос.
     */
    reload: function () {
        const self = this;
        // Список придёт от сервера уже в нужном порядке, и переставлять в нём
        // что-либо по прежним ответам незачем
        this.cancelPositions();
        clearTimeout(this.reloadTimer);
        this.reloadTimer = setTimeout(function () {
            self.request(0, 0, false);
        }, 0);
    },

    /** Догружает следующую порцию к показанному списку. */
    loadMore: function () {
        if (!this.ensureInit() || this.loading) return;

        // Если порядок выборки успели изменить (приоритет или статус задачи),
        // смещение уже не указывает на нужное место: задачи могли переставиться
        // и выше показанного окна, и тогда часть из них запрос бы проскочил.
        // Поэтому выборка запрашивается с начала - вместе с новой порцией, - но
        // добавлением: уже показанные строки отсеет дедупликация и останутся
        // стоять там, где стоят, а смещение снова станет верным.
        // Так можно, только пока показанное умещается в предельный размер порции.
        // Выше потолка сервер обрежет ответ, смещение откатилось бы к потолку,
        // и следующий запрос вернул бы уже показанное - кнопка перестала бы
        // добавлять что-либо вовсе. Поэтому там просто идём дальше по смещению:
        // редкий пропуск одной задачи лучше, чем застрявшая догрузка
        const whole = this.loaded + this.pageSize;
        if (this.reordered && whole <= this.maxPageSize) {
            this.request(0, whole, true);
            return;
        }

        this.request(this.offset, 0, true);
    },

    /**
     * Отменяет незавершённый запрос порции.
     *
     * Ответ на него придёт с устаревшим номером и будет отброшен, поэтому
     * список освобождается здесь, а не в его обработчике.
     */
    cancelPending: function () {
        if (!this.loading) return;

        this.requestId++;
        this.loading = false;
        $('.issues-load-more', this.el).prop('disabled', false);
    },

    /**
     * Запрашивает порцию выборки у сервера.
     * @param {number}  offset Смещение порции в выборке.
     * @param {number}  limit  Размер порции; 0 - размер по умолчанию.
     * @param {boolean} append Добавить порцию к списку, а не заменить список ею.
     * @param {boolean} silent Не показывать общий индикатор загрузки: он
     *        затемняет страницу и перехватывает нажатия, а запрос сделан вслед
     *        за другим действием, а не по нажатию кнопки подгрузки.
     */
    request: function (offset, limit, append, silent) {
        const self = this;
        const requestId = ++this.requestId;

        this.loading = true;
        $('.issues-load-more', this.el).prop('disabled', true);
        if (!silent) preloader.show();

        srv.issue.loadProjectIssues(
            this.projectId,
            this.scope,
            this.search,
            {
                tags: this.filter.tags,
                members: this.filter.memberIds,
                testers: this.filter.testerIds,
                multiMemberOnly: this.filter.multiMemberOnly
            },
            this.sort,
            offset,
            limit,
            function (res) {
                // Прячем всегда: показы preloader считаются, и пропущенный hide
                // оставил бы его висеть навсегда
                if (!silent) preloader.hide();

                // Ответ на запрос, который успели отменить более новым. Такой ответ
                // не только не рисует свою порцию, но и не объявляет список свободным:
                // иначе кнопка подгрузки ожила бы посреди ещё идущего запроса
                // и догрузила порцию поверх списка, который вот-вот заменят
                if (requestId !== self.requestId) return;

                $('.issues-load-more', self.el).prop('disabled', false);
                self.loading = false;

                if (!res.success) {
                    srv.err(res);
                    return;
                }

                self.render(res, offset, append);
            }
        );
    },

    render: function (res, offset, append) {
        const tbody = document.querySelector('#issuesList > tbody');
        if (!tbody) return;

        if (append) {
            const added = this.appendRows(tbody, res.rows);
            // Пришедшая задача уже показана, только на другом месте - значит,
            // выборка переставилась, и смещение опять устареет
            if (added < res.count && offset > 0) {
                this.reordered = true;
            } else if (offset === 0) {
                // Выборку перечитали с начала: смещение снова верное
                this.reordered = false;
                this.statusReordered = false;
            }

            this.loaded += added;
            this.exhausted = res.count === 0;
        } else {
            this.disposeTooltips(tbody);
            tbody.innerHTML = res.rows;
            this.loaded = res.count;
            this.exhausted = false;
            this.reordered = false;
            this.statusReordered = false;
        }

        this.offset = offset + res.count;

        this.total = res.total;
        this.opened = res.opened;

        // Разметка строк пришла с сервера: кружки приоритета и меню копирования
        // в ней ещё не подняты
        issuePage.updatePriorityVals();
        initIssueCopyMenus();
        this.updateView();
    },

    /**
     * Снимает подсказки со строк, которые сейчас будут убраны.
     *
     * Подсказка, открытая над удалённой строкой, скрывать себя уже не по чему
     * и осталась бы висеть над списком.
     */
    disposeTooltips: function (tbody) {
        $(tbody).find('[title], [data-bs-original-title]').each(function () {
            const tooltip = bootstrap.Tooltip.getInstance(this);
            if (tooltip) tooltip.dispose();
        });
    },

    /**
     * Добавляет строки порции к списку, пропуская уже показанные задачи.
     *
     * Задача приходит повторно, если между запросами порций у неё изменился
     * приоритет и она переехала через границу окна выборки.
     * @returns {number} Сколько строк добавлено.
     */
    appendRows: function (tbody, html) {
        const shown = {};
        [...tbody.children].forEach(function (row) {
            shown[row.getAttribute('data-id')] = true;
        });

        const holder = document.createElement('tbody');
        holder.innerHTML = html;

        let added = 0;
        [...holder.children].forEach(function (row) {
            if (shown[row.getAttribute('data-id')]) return;
            tbody.appendChild(row);
            added++;
        });

        return added;
    },

    /**
     * Входит ли задача с таким статусом в выборку области поиска.
     *
     * Список одной области - только открытые или только завершённые - отбирает
     * задачи по статусу, и сменившая статус задача под это условие больше
     * не подходит. В смешанной выборке (область «Все») условия на статус нет,
     * и подходит любая задача.
     */
    matchesScope: function (status) {
        if (!this.ensureInit()) return true;

        return this.statuses.length === 0 || this.statuses.indexOf(status) !== -1;
    },

    /**
     * Ставит задачу на её новое место после изменения приоритета.
     *
     * Порядок выборки задаёт сервер, и по загруженной части его не повторить:
     * место задачи зависит и от того, чего на странице нет. Но и перечитывать
     * ради этого весь показанный список незачем - у сервера спрашивается только
     * место одной задачи, а переставляет строку уже клиент. Ответ не зависит
     * от длины списка, а порядок остаётся целиком за сервером.
     *
     * Запрос отложен: приоритет меняют щелчками подряд, и строка не должна
     * уезжать из-под курсора после каждого.
     * @param {number|string} issueId Задача, у которой изменён приоритет.
     */
    notePriorityChanged: function (issueId) {
        if (!this.ensureInit()) return;

        // Идущий запрос отобран по прежнему порядку: его ответ принесёт
        // и чужие строки, и счётчики, снятые до изменения
        this.cancelPending();

        // Пока место не применено, показанное окно может уже не быть началом
        // выборки: задача, уехавшая за его край, оставляет в окне себя вместо
        // той, что въехала на её место. Пометку снимает перестановка последней
        // из тронутых задач (см. applyIssuePosition()) либо замена списка
        // целиком (см. render())
        this.reordered = true;
        this.orderRevision++;

        this.schedulePosition(issueId);
    },

    /**
     * Откладывает запрос места задачи.
     *
     * Отсрочка своя у каждой задачи: щелчки подряд по одной задаче дают один
     * запрос, а тронутая до этого соседняя строка всё равно встаёт на место.
     * @param {number|string} issueId Задача, у которой изменён приоритет.
     */
    schedulePosition: function (issueId) {
        const self = this;
        const key = String(issueId);
        const state = this.positions[key]
            || (this.positions[key] = { timer: null, sent: 0, applied: 0, retries: 0 });

        clearTimeout(state.timer);
        state.timer = setTimeout(function () {
            state.timer = null;
            self.requestPosition(issueId);
        }, this.reorderDelay);
    },

    /**
     * Отменяет ждущую перестановку строки одной задачи.
     *
     * Зовётся при смене статуса: место задачи теперь определяет не приоритет,
     * а новое состояние, и в смешанной выборке строка должна остаться там, где
     * стоит (см. refreshIssueRow()).
     * @param {number|string} issueId Задача, чью перестановку отменяем.
     */
    forgetIssuePosition: function (issueId) {
        const key = String(issueId);
        delete this.positionResults[key];

        const state = this.positions[key];
        if (!state) return;

        // Ответ на уже отправленный запрос отбросит проверка состояния
        clearTimeout(state.timer);
        delete this.positions[key];
    },

    /** Отменяет незаконченные перестановки строк. */
    cancelPositions: function () {
        for (const key in this.positions) {
            clearTimeout(this.positions[key].timer);
        }

        this.positions = {};
        this.positionResults = {};
    },

    /** Остались ли задачи, которые ещё не встали на своё место. */
    hasPendingPositions: function () {
        return Object.keys(this.positions).length > 0;
    },

    /**
     * Забывает задачу, по которой больше нечего ждать.
     *
     * Состояние живёт, пока по задаче есть отложенный или неотвеченный запрос:
     * по нему отличается ответ, пришедший после более нового.
     * @param {string} key Идентификатор задачи строкой.
     */
    forgetPosition: function (key) {
        const state = this.positions[key];
        if (state && state.timer === null && state.sent === state.applied) {
            delete this.positions[key];
        }
    },

    /**
     * Спрашивает у сервера место задачи в выборке и применяет его к списку.
     * @param {number|string} issueId Задача, у которой изменён приоритет.
     */
    requestPosition: function (issueId) {
        const self = this;
        const key = String(issueId);
        const state = this.positions[key];
        if (!state) return;

        // Список сейчас заменят ответом на идущий запрос, и место, посчитанное
        // для прежнего окна, к нему не подойдёт: ждём, пока список освободится
        if (this.loading) {
            this.deferPosition(issueId, state);
            return;
        }

        // Номер запроса списка не увеличиваем: запрос места ничего не отменяет.
        // Он нужен, чтобы заметить, что список с тех пор запросили заново,
        // а счётчик изменений порядка - что выборка успела переставиться
        const listRequestId = this.requestId;
        const orderRevision = this.orderRevision;
        const sent = ++state.sent;

        srv.issue.loadIssuePosition(
            this.projectId,
            this.scope,
            this.search,
            {
                tags: this.filter.tags,
                members: this.filter.memberIds,
                testers: this.filter.testerIds,
                multiMemberOnly: this.filter.multiMemberOnly
            },
            this.sort,
            issueId,
            function (res) {
                // Перестановки отменили совсем либо по этой задаче уже
                // отработан более новый ответ: этот - не о том месте
                const state = self.positions[key];
                if (!state || sent <= state.applied) return;

                state.applied = sent;

                if (!res.success) {
                    self.forgetPosition(key);
                    self.drainPositions();
                    srv.err(res);
                    return;
                }

                // Пока считалось место, список запросили заново или порядок
                // выборки успели изменить ещё раз - значит, оно посчитано
                // не для того списка. Спрашиваем ещё раз
                if (listRequestId !== self.requestId || orderRevision !== self.orderRevision) {
                    self.deferPosition(issueId, state);
                    return;
                }

                // Этот ответ - последний и посчитан для нынешнего порядка,
                // поэтому отложенные попытки по той же задаче уже ни к чему
                clearTimeout(state.timer);
                state.timer = null;
                self.positionResults[key] = res.position;
                self.forgetPosition(key);
                self.drainPositions();
            }
        );
    },

    /**
     * Откладывает запрос места ещё раз, пока список не успокоится.
     *
     * Откладывать бесконечно нельзя: если список меняют чаще, чем считается
     * место, строка остаётся там, где стоит, а порядок выборки так и остаётся
     * помеченным изменившимся - подгрузка это учтёт.
     * @param {number|string} issueId Задача, у которой изменён приоритет.
     * @param {Object}        state   Состояние запроса места этой задачи.
     */
    deferPosition: function (issueId, state) {
        if (state.retries >= this.maxPositionRetries) {
            delete this.positions[String(issueId)];
            this.drainPositions();
            return;
        }

        state.retries++;
        this.schedulePosition(issueId);
    },

    /**
     * Применяет полученные места, когда ждать больше нечего.
     *
     * Зовётся на любом исходе запроса, а не только на удачном: иначе место,
     * присланное по одной задаче, осталось бы неприменённым из-за сбоя запроса
     * по соседней.
     */
    drainPositions: function () {
        if (this.hasPendingPositions()) return;

        this.applyPositions();
    },

    /**
     * Ставит строки на присланные сервером места.
     *
     * Одну строку переставляем сами. Если же мест пришло несколько, новый
     * порядок строк - это одна перестановка, и собрать её независимыми
     * переносами нельзя: каждый следующий сдвигает уже расставленные строки,
     * и результат зависит от того, в каком порядке пришли ответы. Такой список
     * перечитывается у сервера - это редкий случай, когда приоритет успели
     * поменять сразу у нескольких задач.
     */
    applyPositions: function () {
        const results = this.positionResults;
        this.positionResults = {};

        const ids = Object.keys(results);
        if (ids.length === 0) return;

        if (ids.length === 1) {
            this.applyIssuePosition(ids[0], results[ids[0]]);
            return;
        }

        // Выше потолка порции перечитать показанное нечем: строки остаются
        // там, где стоят, а порядок выборки - помеченным изменившимся
        if (this.loaded <= 0 || this.loaded > this.maxPageSize) return;

        this.request(0, this.loaded, false, true);
    },

    /**
     * Ставит строку задачи на её место в списке.
     *
     * Показанная часть - это начало выборки, поэтому место за её концом значит,
     * что задачи в списке больше нет: на её место въехала задача, стоявшая сразу
     * за ним, а показано стало на строку меньше. Место внутри окна набор строк
     * не меняет, и смещение подгрузки остаётся верным само собой.
     * @param {number|string} issueId  Задача, у которой изменён приоритет.
     * @param {number}        position Место задачи в выборке, считая с единицы;
     *        0 - задачи в выборке нет.
     */
    applyIssuePosition: function (issueId, position) {
        const tbody = document.querySelector('#issuesList > tbody');
        if (!tbody) return;

        const row = tbody.querySelector('tr[data-id="' + issueId + '"]');
        if (!row) return;

        // Пометку снимаем, только если окно больше ничто не выбивает из порядка
        // выборки: ни задачи, которые ещё не встали на место, ни строка,
        // оставленная на месте после смены статуса
        if (!this.hasPendingPositions() && !this.statusReordered) {
            this.reordered = false;
        }

        if (position < 1 || position > this.loaded) {
            this.disposeTooltips(row);
            row.remove();
            this.loaded = Math.max(0, this.loaded - 1);
            this.offset = Math.max(0, this.offset - 1);
            this.updateView();
            return;
        }

        const index = position - 1;
        if ([...tbody.children].indexOf(row) !== index) {
            // Строку сначала вынимаем: пока она в списке, она же сдвигает
            // нумерацию соседей, и вставка встала бы на позицию мимо
            row.remove();
            tbody.insertBefore(row, tbody.children[index] || null);
        }

        highlightIssueRow($(row));
    },

    /**
     * Приводит состояние выборки в соответствие со сменой статуса задачи.
     *
     * Задача, выпавшая из выборки, укорачивает её, и всё, что стояло за задачей,
     * сдвигается на позицию; смену статуса внутри выборки видно только в её
     * порядке. Строку из списка убирает вызывающий - см. refreshIssueRow().
     * @param {number} wasStatus Статус задачи до изменения.
     * @param {number} nowStatus Статус задачи после изменения.
     */
    noteStatusChanged: function (wasStatus, nowStatus) {
        if (!this.ensureInit()) return;

        // Статус - первый терм порядка выборки: места, посчитанные до этой
        // смены, к новому порядку уже не относятся. Неотвеченные запросы
        // заметят это по счётчику и спросят заново
        this.orderRevision++;
        this.positionResults = {};

        // Идущий запрос отобран до смены статуса: его ответ принёс бы
        // счётчики прежней выборки
        this.cancelPending();

        const completed = lpmOptions.issueStatuses.completed;
        const wasIn = this.matchesScope(wasStatus);
        const nowIn = this.matchesScope(nowStatus);
        const wasOpened = wasIn && wasStatus != completed;
        const nowOpened = nowIn && nowStatus != completed;

        if (wasIn === nowIn) {
            // Задача из выборки не выпала, но встала в ней на другое место -
            // значит, смещение следующей порции устарело
            if (nowIn) {
                this.reordered = true;
                this.statusReordered = true;
            }
        } else {
            // Задача вошла в выборку или выпала из неё: выборка стала длиннее
            // или короче, и всё, что было за этой задачей, сдвинулось на позицию
            const step = nowIn ? 1 : -1;
            this.loaded = Math.max(0, this.loaded + step);
            this.offset = Math.max(0, this.offset + step);
            this.total = Math.max(0, this.total + step);
        }

        this.opened = Math.max(0, this.opened + (nowOpened ? 1 : 0) - (wasOpened ? 1 : 0));

        this.updateView();
    },

    /** Приводит кнопку подгрузки и счётчики в соответствие с состоянием выборки. */
    updateView: function () {
        if (!this.ensureInit()) return;

        // Считаем по показанному, а не по смещению: из-за отброшенных повторов
        // смещение может дойти до размера выборки раньше, чем список показан весь
        const hasMore = !this.exhausted && this.loaded < this.total;
        this.el.classList.toggle('d-none', !hasMore);
        // В кнопке - сколько добавит одно нажатие, а не весь остаток выборки:
        // остаток и так виден в подписи рядом
        $('.issues-remaining-count', this.el)
            .text(Math.min(this.pageSize, Math.max(0, this.total - this.loaded)));
        $('.issues-loaded-count', this.el).text(this.loaded);
        $('.issues-selection-count', this.el).text(this.total);
        // Считаем по строкам на экране, а не по размеру выборки: задача, выпавшая
        // из выборки после смены статуса, со страницы никуда не девается
        const shownRows = $('#issuesList > tbody > tr').length;
        $('.issues-list-empty').toggleClass('d-none', shownRows > 0);

        const selection = this.isSelection();
        $('.project-stat .issues-selection').toggleClass('d-none', !selection);
        $('.project-stat .issues-summary').toggleClass('d-none', selection);
        $('.project-stat .issues-shown').text(this.total);
        $('.project-stat .issues-opened').text(this.opened);
    }
};

issuePage.loadMoreIssues = function () {
    issuePage.issuesList.loadMore();
};

issuePage.updateStat = function () {
    if ($("#projectView").length == 0) return;

    issuePage.issuesList.updateView();

    // В отобранном списке показан его размер, а часов проекта рядом нет
    if (issuePage.issuesList.isSelection()) return;

    // Перезапрашиваем сумму часов
    const isScrum = $("#projectView").data('scrum') == 1;
    srv.project.getSumOpenedIssuesHours($("#projectView").data('projectId'), function (r) {
        if (r.success) {
            if (r.count > 0) {
                $(".project-stat .project-opened-issue-hours").show();
                $(".project-stat .issue-hours.value").text(r.count);
                $(".project-stat .issue-hours-label").text(normHoursLabel(r.count, isScrum));
            }
            else {
                $(".project-stat .project-opened-issue-hours").hide();
            }
        }
    });
};

// Склонение (порт DeclensionHelper): variants = [1, 2-4, 5+].
function declension(variants, count) {
    count = Math.abs(count);
    if (count < 1) return variants[1];
    if (count > 10 && count < 15) return variants[2];
    switch (Math.floor(count) % 10) {
        case 1: return variants[0];
        case 2:
        case 3:
        case 4: return variants[1];
        default: return variants[2];
    }
}

// Подпись к сумме оценок: SP для scrum-проекта, иначе склонение «час».
function normHoursLabel(count, isScrum) {
    if (isScrum) return count > 1 ? 'story points' : 'story point';
    return declension(['час', 'часа', 'часов'], count);
}

issuePage.onClickCopyIssueUrl = function (event) {
    const link = event.target.closest('a');
    const url = link.getAttribute('data-issue-url');

    lpm.utils.copyToClipboard(url).then(() => {
       lpm.toast.show('Ссылка скопирована в буфер обмена'); 
    });
};

issuePage.onClickCopyIssueId = function (event) {
    const link = event.target.closest('a');
    const id = link.getAttribute('data-issue-id');
    lpm.utils.copyToClipboard(String(id)).then(() => {
        lpm.toast.show('Внутренний ID скопирован');
    });
};

issuePage.onClickCopyMarkdownIssueLink = function (event) {
    const link = event.target.closest('a');
    const url = link.getAttribute('data-issue-url');
    const idInProject = link.getAttribute('data-issue-id-in-project');

    const text = '[#' + idInProject + '](' + url + ')';

    lpm.utils.copyToClipboard(text).then(() => {
       lpm.toast.show('Markdown ссылка скопирована в буфер'); 
    });
};

issuePage.onClickCopyCommitMessage = function (event) {
    const link = event.target.closest('a');
    const idInProject = link.getAttribute('data-issue-id-in-project');
    const issueName = link.getAttribute('data-issue-name');

    const text = 'Issue #' + idInProject + ': ' + issueName;

    lpm.utils.copyToClipboard(text).then(() => {
       lpm.toast.show('Commit сообщение скопировано'); 
    });
};

issuePage.onClickCopyIssueName = function (event) {
    const link = event.target.closest('a');
    const issueName = link.getAttribute('data-issue-name');
    lpm.utils.copyToClipboard(issueName).then(() => {
        lpm.toast.show('Название скопировано');
    });
};

issuePage.onClickCopyIssueNameWithoutTags = function (event) {
    const link = event.target.closest('a');
    const issueName = link.getAttribute('data-issue-name');
    const clearedName = removeLabelsFromIssueName(issueName);
    lpm.utils.copyToClipboard(clearedName).then(() => {
        lpm.toast.show('Название без тегов скопировано ');
    });
};

issuePage.onClickCopyIssueTitle = function (event) {
    const link = event.target.closest('a');
    const idInProject = link.getAttribute('data-issue-id-in-project');
    const issueName = link.getAttribute('data-issue-name');
    const text = issueTitle(idInProject, issueName);
    lpm.utils.copyToClipboard(text).then(() => {
        lpm.toast.show('Заголовок скопирован');
    });
};

issuePage.onClickCopyLinkedIssueTitle = function (event) {
    const link = event.target.closest('a');
    const url = link.getAttribute('data-issue-url');
    const idInProject = link.getAttribute('data-issue-id-in-project');
    const issueName = link.getAttribute('data-issue-name');
    
    const text = issueTitle(idInProject, issueName);

    const plain = `${text} (${url})`;
    // Название задачи — произвольный текст: в html-вариант оно попадает экранированным,
    // иначе угловые скобки в названии стали бы разметкой
    const html = `<a href="${lpm.utils.escapeHtml(url)}">${lpm.utils.escapeHtml(text)}</a>`;

    lpm.utils.copyRichToClipboard(html, plain).then(() => {
        lpm.toast.show('Кликабельная ссылка скопирована');
    });
};

issuePage.onClickCopyChangelogRecord = function (event) {
    const link = event.target.closest('a');
    const url = link.getAttribute('data-issue-url');
    const idInProject = link.getAttribute('data-issue-id-in-project');
    const issueName = link.getAttribute('data-issue-name');
    const clearedName = removeLabelsFromIssueName(issueName);

    const text = clearedName + ' ([#' + idInProject + '](' + url + '))';

    lpm.utils.copyToClipboard(text).then(() => {
        lpm.toast.show('Запись для changelog скопирована');
    });
};

issuePage.onClickCopyIssueForAI = function (event) {
    const link = event.target.closest('a');
    const url = link.getAttribute('data-issue-url') || window.location.href;
    const idInProject = link.getAttribute('data-issue-id-in-project');
    const issueName = link.getAttribute('data-issue-name');
    const clearedName = removeLabelsFromIssueName(issueName);

    // Labels from data attribute (optional)
    const labels = (issuePage.labels || [])
        .filter(x => x && String(x).trim().length > 0)
        .join(', ');

    // Raw markdown description
    const desc = $("#issueInfo .desc .raw-desc").val() || '';

    let lines = [];
    lines.push(`Issue #${idInProject}: ${clearedName}`);
    lines.push(`URL: ${url}`);
    if (labels) lines.push(`Метки: ${labels}`);
    lines.push('');
    lines.push('Описание (Markdown):');
    lines.push(desc.trim());

    const text = lines.join('\n');

    lpm.utils.copyToClipboard(text).then(() => {
        lpm.toast.show('Текст для AI скопирован');
    });
};

function issueTitle(idInProject, issueName) {
    return idInProject + '. ' + issueName;
}

function removeLabelsFromIssueName(name) {
    let s = name.trim();
    while (s.charAt(0) === '[') {
        const idx = s.indexOf(']');
        if (idx < 0) break;
        s = s.substring(idx + 1).trim();
    }
    return s;
}

function insertFormattingLink(input) {
    const text = getSelectedText(input);
    if (parser.findLinks(text)) {
        insertFormatting(input, '[](', ')', 1);
    }
    else {
        insertFormatting(input, '[', ']()', -2);
    }
}

function insertFormattingMarker(input, marker, single) {
    // For headers: insert marker at the start of the current line
    if (single && typeof marker === 'string' && marker.indexOf('#') === 0) {
        insertHeaderAtLineStart(input, marker);
        return;
    }
    // Blockquote, like the header, is a line-level marker
    if (single && marker === '> ') {
        toggleBlockquoteAtLineStarts(input, marker);
        return;
    }

    insertFormatting(input, marker, single ? "" : marker)
}

/**
 * Toggle the blockquote marker on every line touched by the selection.
 *
 * Markdown recognises the marker only at the start of a line, so the affected
 * range is expanded to whole lines: a selection that begins or ends mid-line
 * still covers the lines it touches, and with no selection the caret's own line
 * is used.
 *
 * The direction is decided for the range as a whole: while at least one line is
 * unquoted the marker is added to the lines that lack it, so repeating the
 * action never builds up ">>"; once every line is quoted it is stripped from
 * all of them instead. Stripping removes one marker and the single space that
 * may follow it, so a deeper nesting level survives.
 *
 * @param {jQuery|HTMLTextAreaElement} input field being edited
 * @param {string} marker blockquote marker, trailing space included
 */
function toggleBlockquoteAtLineStarts(input, marker) {
    const $input = $(input);
    const el = $input[0];
    const value = el.value;
    const start = el.selectionStart;
    const end = el.selectionEnd;

    const blockStart = start === 0 ? 0 : value.lastIndexOf('\n', start - 1) + 1;
    // A selection ending right after a line break stops before the next line,
    // so that line is not part of the quote.
    const searchFrom = end > start && value.charAt(end - 1) === '\n' ? end - 1 : end;
    const lineBreak = value.indexOf('\n', searchFrom);
    const blockEnd = lineBreak === -1 ? value.length : lineBreak;

    // One level of quoting: the marker at the start of the line plus the single
    // space that may follow it. An empty line matches nothing, so a blank line
    // inside the range always counts as unquoted.
    const quoteLevel = /^(\s*)>( ?)/;
    const lines = value.substring(blockStart, blockEnd).split('\n');
    const strip = lines.every(function (line) { return quoteLevel.test(line); });

    // An empty line caught inside a selection gets the marker without its
    // trailing space, so the quote stays a single block and no trailing
    // whitespace is left behind. Without a selection the caret's line is where
    // typing continues, so there the marker keeps its space.
    const emptyLineMarker = start === end ? marker : marker.replace(/\s+$/, '');

    let firstLineShift = 0;
    const transformed = lines.map(function (line, i) {
        let result;
        if (strip) {
            result = line.replace(quoteLevel, '$1');
        } else if (quoteLevel.test(line)) {
            result = line;
        } else {
            result = (line === '' ? emptyLineMarker : marker) + line;
        }

        if (i === 0) {
            firstLineShift = result.length - line.length;
        }

        return result;
    }).join('\n');

    $input.val(value.substring(0, blockStart) + transformed + value.substring(blockEnd)).trigger('input');

    // Without a selection the caret keeps its place in the line, so typing can
    // continue right away; otherwise it goes after the changed block.
    setCaretPosition(el, start === end
        ? Math.max(blockStart, start + firstLineShift)
        : blockStart + transformed.length);
}

function getSelectedText(input) {
    const text = $(input)[0];
    return text.value.substring(text.selectionStart, text.selectionEnd);
}

function insertFormatting(input, before, after, cursorShift) {
    let $input = $(input);
    let text = $input[0];
    let selectionStart = text.selectionStart;
    let subtext = text.value.substring(selectionStart, text.selectionEnd);

    let res = text.value.substring(0, selectionStart) +
        before + subtext + after +
        text.value.substring(text.selectionEnd, text.value.length);

    var caretPos = selectionStart;
    let fullLength = before.length + subtext.length + after.length;
    if (cursorShift) {
        // если отрицательный, то считаем с конца
        // -1 соответствует концу выражения
        if (cursorShift >= 0)
            caretPos += cursorShift;
        else
            caretPos += fullLength + cursorShift + 1;
    } else {
        // если нет выделенного текста, то ставим курсор внутри,
        // чтобы написали текст, а если есть - то за закрывающим тегом,
        // чтобы продолжали писать
        if (subtext == "")
            caretPos += before.length;
        else
            caretPos += fullLength;
    }

    $input.val(res).trigger('input');

    //устанавливаем курсор на полученную позицию
    setCaretPosition(text, caretPos);
}

function insertHeaderAtLineStart(input, marker) {
    const $input = $(input);
    const el = $input[0];
    const value = el.value;
    const caret = el.selectionStart || 0;
    const lineStart = value.lastIndexOf('\n', Math.max(0, caret - 1)) + 1; // 0 if not found

    const before = value.substring(0, lineStart);
    const after = value.substring(lineStart);
    const newValue = before + marker + after;

    $input.val(newValue).trigger('input');

    // Move caret forward to keep it at the same logical position within the line
    const newCaret = caret + marker.length;
    setCaretPosition(el, newCaret);
}

function setCaretPosition(elem, pos) {
    elem.setSelectionRange(pos, pos);
    elem.focus();
}

function completeIssue(e) {
    var parent = e.currentTarget.parentElement;
    var issueId = $('input[name=issueId]', parent).val();
    if (issueId <= 0) return

    lpm.dialog.confirm({
        text: 'Отметить задачу как завершённую?',
        yesLabel: 'Завершить',
        onYes: function () {
            preloader.show();
            srv.issue.complete(
                issueId,
                function (res) {
                    //btn.disabled = false;
                    preloader.hide();
                    if (res.success) {
                        if ($('#issuesList').length > 0) {
                            refreshIssueRow(issueId, lpmOptions.issueStatuses.completed);
                            showMain();
                        } else if ($('#issueView').length > 0) {
                            setIssueInfo(new Issue(res.issue), res.substatus);
                        }
                        issuePage.updateStat();
                    } else {
                        srv.err(res);
                    }
                }
            );
        }
    });
}

/**
 * Показывает задачу в новом состоянии: либо убирает её строку из списка, либо
 * перерисовывает на месте.
 *
 * Задачу, которую список больше не отбирает, он и не показывает - строка уходит
 * (см. issuesList.matchesScope()). В смешанной выборке задача остаётся на своём
 * месте и только меняет вид: пользователь должен видеть, что именно он изменил,
 * и мочь сразу отменить. Строка перерисовывается разметкой с сервера - её вид
 * зависит от статуса целиком: цвет, кнопки, стрелки приоритета, дата завершения.
 * @param {number} issueId   Идентификатор задачи.
 * @param {number} newStatus Статус, в который задача перешла.
 */
function refreshIssueRow(issueId, newStatus) {
    const $row = $("#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])");
    if ($row.length === 0) return;

    const list = issuePage.issuesList;
    const wasStatus = $row.data('status');
    const stays = list.matchesScope(newStatus);

    list.forgetIssuePosition(issueId);

    // Строка убирается до пересчёта: подпись «Ничего не найдено» показывается
    // по строкам на экране
    if (!stays) {
        list.disposeTooltips($row[0]);
        $row.remove();
    }

    list.noteStatusChanged(wasStatus, newStatus);

    if (!stays) return;

    srv.issue.loadIssueRow(issueId, function (res) {
        if (!res.success) {
            srv.err(res);
            return;
        }

        // Разбираем через tbody: строка таблицы вне таблицы разбирается
        // по-разному, а так разметка попадает в тот же контекст, что и порция
        const holder = document.createElement('tbody');
        holder.innerHTML = res.row;
        const fresh = holder.querySelector('tr');
        if (!fresh) return;

        issuePage.issuesList.disposeTooltips($row[0]);
        $row[0].replaceWith(fresh);
        issuePage.updatePriorityVals();
        initIssueCopyMenus();
        highlightIssueRow($(fresh));
    });
}

issuePage.changePriority = function (e) {
    var $control = $(e.currentTarget);
    var $row = $control.parents('tr');
    var issueId = $('input[name=issueId]', $row).val();
    var delta = $control.hasClass('priority-up') ? 1 : -1;

    if (issueId > 0) {
        srv.issue.changePriority(issueId, delta, function (res) {
            if (res.success) {
                // Задача переехала по списку: он перестроится, а смещение
                // подгрузки по прежнему порядку выборки больше не годится
                issuePage.issuesList.notePriorityChanged(issueId);

                let priority = res.priority;
                let priorityStr = Issue.getPriorityStr(priority);
                let priorityVal = Issue.getPriorityDisplayVal(priority);
                let tooltipHost = $('.priority-title-owner', $row)[0];
                if (tooltipHost) {
                    let newTitle = 'Приоритет: ' + priorityStr + ' (' + priorityVal + ')';
                    // Drop the per-element tooltip instance (and any tip shown for the old value);
                    // the delegated body tooltip rebuilds it from the fresh title on next hover.
                    let tooltipInstance = bootstrap.Tooltip.getInstance(tooltipHost);
                    if (tooltipInstance) {
                        tooltipInstance.dispose();
                    }
                    // Bootstrap caches the title in data-bs-original-title after the first hover,
                    // so reset both attributes to the new value.
                    tooltipHost.setAttribute('title', newTitle);
                    tooltipHost.removeAttribute('data-bs-original-title');
                }

                // Строка остаётся прежним узлом, только переезжает по списку,
                // поэтому её приоритет обновляем в разметке сами
                $row.attr('data-priority', priority);
                $('.priority-val', $row).data("value", priority);
                issuePage.updatePriorityVal($('.priority-val', $row), priority);

                var hintY = e.pageY - 13;
                $("<span></span>").text(priorityVal).addClass("priority-change-animation").
                    appendTo($('body')).offset({ top: hintY, left: e.pageX - 10 }).
                    animate(
                        {
                            opacity: '0',
                            top: '-=20px'
                        }, 500, function () {
                            $(this).remove();
                        });
            } else {
                srv.err(res);
            }
        });
    }
}

function restoreIssue(e) {
    var parent = e.currentTarget.parentElement;
    var issueId = $('input[name=issueId]', parent).val();
    preloader.show();

    srv.issue.restore(
        issueId,
        function (res) {
            preloader.hide();
            if (res.success) {
                if ($('#issuesList').length > 0) {
                    refreshIssueRow(issueId, lpmOptions.issueStatuses.inWork);
                    showMain();
                } else if ($('#issueView').length > 0) {
                    setIssueInfo(new Issue(res.issue), res.substatus);
                }
                issuePage.updateStat();
            } else {
                srv.err(res);
            }
        }
    );
};

function verifyIssue(e) {
    var parent = e.currentTarget.parentElement;

    var issueId = $('input[name=issueId]', parent).val();
    preloader.show();

    srv.issue.verify(
        issueId,
        function (res) {
            preloader.hide();
            if (res.success) {
                if ($('#issueView').length > 0) {
                    setIssueInfo(new Issue(res.issue), res.substatus);
                }
                issuePage.updateStat();

                // Чек-лист подключается флагом проекта, а issues.js грузится
                // и на списках задач, и на scrum доске.
                if (typeof aiTestChecklist !== 'undefined' && aiTestChecklist.isAvailable()) {
                    aiTestChecklist.show();
                }
            } else {
                srv.err(res);
            }
        }
    );
};

issuePage.removeIssue = function (e) {
    var btn = e.currentTarget;
    lpm.dialog.confirm({
        text: 'Вы действительно хотите удалить эту задачу?',
        yesLabel: 'Удалить',
        onYes: function () {
            var issueId = $('input[type=hidden][name=issueId]', btn.parentElement).val();

            preloader.show();

            srv.issue.remove(
                issueId,
                function (res) {
                    preloader.hide();
                    if (res.success) {
                        //window.location.hash = '';
                        window.location.href = $("#issueView a.back-link").attr('href');
                        //window.location.reload();
                    } else {
                        srv.err(res);
                    }
                }
            );
        }
    });
};

issuePage.putStickerOnBoard = function () {
    preloader.show();
    const $issueInfo = $('#issueInfo');
    const issueId = $issueInfo.data('issueId');
    srv.issue.putStickerOnBoard(issueId, function (res) {
        preloader.hide();
        if (!res.success) {
            srv.err(res);
            return;
        }

        $('.scrum-put-sticker', $issueInfo).remove();
        $issueInfo.data('isOnBoard', true);
        applyIssueSubstatus(res);

        issuePage.scrumColUpdateInfo();
    });
};

function showIssue(issueId) {
    srv.issue.load(
        issueId,
        false,
        function (res) {
            if (res.success) {
                states.setState('issue-view');
                setIssueInfo(new Issue(res.issue), res.substatus);
            } else {
                srv.err(res);
            }
        }
    );
};

issuePage.showEditForm = function () {
    issueForm.acquireLock(issuePage.getIssueId(), issuePage.getRevision(), false, function () {
        // переключаем вид
        states.setState('edit');
    });
};

/**
 * @param {Issue} issue
 * @param {Number} substatus Уточнение статуса задачи, присланное сервером
 * (Issue.SUBSTATUS_*).
 */
function setIssueInfo(issue, substatus) {
    applyUnderTesting(substatus);

    const $issueInfo = $("#issueInfo");

    setIssueInfoCard(issue, $issueInfo, substatus);

    setIssueFormState(issue, $issueInfo);
};

/**
 * Обновляет скрытые поля задачи: из них заполняется форма редактирования,
 * поэтому они должны соответствовать показанным значениям.
 * @param {Issue} issue
 * @param {jQuery} $issueInfo
 */
function setIssueFormState(issue, $issueInfo) {
    const values = {
        issueId: issue.id,
        revision: issue.revision,
        type: issue.type,
        priority: issue.priority,
        completeDate: issue.getCompleteDateInput(),
        members: issue.getMemberIds().join(','),
        membersSp: issue.getMembersSp().join(','),
        testers: issue.getTesterIds().join(','),
        masters: issue.getMasterIds().join(','),
    };

    Object.keys(values).forEach(function (field) {
        const value = values[field];
        if (value === undefined) return;

        $('input[name=' + field + ']', $issueInfo).val(value);
    });
}

/**
 * Обновляет карточку задачи (шаблон issue.html).
 * @param {Issue} issue
 * @param {jQuery} $issueInfo
 * @param {Number} substatus Уточнение статуса, присланное сервером
 * (Issue.SUBSTATUS_*). Без него бейдж статуса остаётся как есть.
 */
function setIssueInfoCard(issue, $issueInfo, substatus) {
    $(".issue-name", $issueInfo).text(issue.name);

    // Каждое поле помечено в разметке своим data-field, поэтому порядок блоков
    // на странице можно менять, не трогая обновление
    // (статус живёт в бейдже и обновляется отдельно — вместе с оформлением)
    const values = {
        type: issue.getType(),
        priority: issue.getPriority(),
        createDate: issue.getCreateDate(),
        completeDate: issue.getCompleteDate(),
        completedDate: issue.getCompletedDate(),
        author: issue.getAuthorHtml(),
        members: issue.getMembersHtml(),
        testers: issue.getTestersHtml(),
        masters: issue.getMastersHtml(),
        desc: issue.getDesc(true),
    };

    Object.keys(values).forEach(function (field) {
        $('[data-field="' + field + '"]', $issueInfo).html(values[field]);
    });

    // У задачи без описания вместо него показывается заглушка
    const hasDesc = (issue.desc || '').trim() !== '';
    $('.desc .formatted-desc', $issueInfo).toggleClass('d-none', !hasDesc);
    $('.desc .desc-placeholder', $issueInfo).toggleClass('d-none', hasDesc);

    // Подстатус знает только сервер, поэтому без него бейдж не трогаем:
    // иначе показанное уточнение подменилось бы названием самого статуса
    if (substatus !== undefined) {
        setIssueStatusBadge($issueInfo, issue.status, substatus);
    }

    $(".issue-type-badge", $issueInfo)
        .removeClass(Issue.TYPE_BADGE_CLASSES)
        .addClass(Issue.getTypeBadgeClass(issue.type));
    $(".issue-type-icon", $issueInfo)
        .removeClass(Issue.TYPE_ICON_CLASSES)
        .addClass(Issue.getTypeIconClass(issue.type));

    const deadlineLevel = Issue.getDeadlineLevel(issue);
    $(".issue-deadline-badge", $issueInfo)
        .removeClass(Issue.DEADLINE_BADGE_CLASSES)
        .addClass(Issue.getDeadlineBadgeClass(deadlineLevel));
    $(".issue-deadline-icon", $issueInfo)
        .removeClass(Issue.DEADLINE_ICON_CLASSES)
        .addClass(Issue.getDeadlineIconClass(deadlineLevel));

    $("#issueView").toggleClass('issue-testing', issue.isVerify());

    $issueInfo
        .removeClass('active-issue verify-issue completed-issue')
        .addClass(Issue.getStatusStateClass(issue.status));

    $('.issue-complete-date-row', $issueInfo).toggleClass('no-date', !issue.hasCompleteDate());

    issuePage.updateAddMeLinks(issue);

    issuePage.updatePriorityVals();

    // Атрибут держим в паре с jQuery-хранилищем: .data() его больше не читает,
    // и без этого разметка сохраняет статус, с которым страница загрузилась.
    $issueInfo.attr('data-status', issue.status).data('status', issue.status);
};

/**
 * Ставит бейджу статуса актуальные текст и оформление.
 * @param {jQuery} $issueInfo Карточка задачи (#issueInfo).
 * @param {Number} status
 * @param {Number} substatus Уточнение статуса (Issue.SUBSTATUS_*).
 */
function setIssueStatusBadge($issueInfo, status, substatus) {
    $(".issue-status-badge", $issueInfo)
        .removeClass(Issue.STATUS_BADGE_CLASSES)
        .addClass(Issue.getStatusBadgeClass(status, substatus))
        .text(Issue.getStatusLabel(status, substatus));
}

/**
 * Обновляет уточнение статуса в бейдже по ответу сервиса.
 *
 * Подстатус вычисляется только на сервере - он зависит от комментариев задачи
 * и её стикера на доске, которых в ответе нет. Поэтому каждое действие,
 * способное его изменить, присылает актуальное значение, а клиент его
 * не додумывает.
 * @param {Object} res Ответ сервиса.
 */
function applyIssueSubstatus(res) {
    if (res.substatus === undefined) return;

    applyUnderTesting(res.substatus);

    const $issueInfo = $('#issueInfo');
    if ($issueInfo.length === 0) return;

    setIssueStatusBadge($issueInfo, $issueInfo.data('status'), res.substatus);
}

issuePage.createBranch = function () {
    createBranch.show(issuePage.projectId, issuePage.getIssueId(), issuePage.idInProject);
}

issuePage.showAddLinkForm = function () {
    addIssueLink.show(issuePage.projectId, issuePage.getIssueId(), function (res) {
        issuePage.updateLinkedIssues(res.html);
    });
};

issuePage.removeLink = function (linkedIssueId, linkedLabel) {
    const target = linkedLabel
        ? ('задачей «' + $('<span>').text(linkedLabel).html() + '»')
        : 'этой задачей';
    lpm.dialog.confirm({
        title: 'Удаление связи',
        text: 'Удалить связь с ' + target + '?',
        yesLabel: 'Удалить',
        onYes: function () {
            preloader.show();
            srv.issue.removeLink(issuePage.getIssueId(), linkedIssueId, function (res) {
                preloader.hide();
                if (res.success) {
                    issuePage.updateLinkedIssues(res.html);
                    lpm.toast.show('Связь удалена');
                } else {
                    srv.err(res);
                }
            });
        },
    });
};

issuePage.updateLinkedIssues = function (html) {
    $('#linkedIssues').html(html);
};

/**
 * Отмечает, что текущий пользователь взял задачу в тестирование.
 * @param {Boolean} [confirmed] Подтверждён ли перехват задачи у того,
 * кто проверяет её сейчас.
 */
issuePage.takeForTesting = function (confirmed) {
    issuePage.changeTestingMark(
        (issueId, handler) => srv.issue.takeForTesting(issueId, !!confirmed, handler),
        function (res) {
            if (res.needConfirm) {
                // holderName сервис отдаёт уже экранированным: диалог вставляет
                // text как HTML
                lpm.dialog.confirm({
                    text: 'Задачу тестирует ' + res.holderName + '. Взять на себя?',
                    yesLabel: 'Взять на себя',
                    onYes: () => issuePage.takeForTesting(true),
                });
                return;
            }

            if (res.testerAdded) issuePage.appendParticipant('tester', res);
        });
};

issuePage.releaseFromTesting = function () {
    issuePage.changeTestingMark(
        (issueId, handler) => srv.issue.releaseFromTesting(issueId, handler));
};

/**
 * Ставит или снимает отметку о взятии задачи в тестирование.
 *
 * Записи в ленте нет, когда сервис ничего не изменил: так отвечает
 * запрос подтверждения перехвата задачи у другого проверяющего.
 * @param {Function} srvCall Вызов сервиса: (issueId, handler).
 * @param {Function} [onSuccess] Дополнительная обработка успешного ответа.
 */
issuePage.changeTestingMark = function (srvCall, onSuccess) {
    preloader.show();
    srvCall(issuePage.getIssueId(), function (res) {
        preloader.hide();
        if (!res.success) {
            srv.err(res);
            return;
        }

        if (res.comment) issuePage.addComment(res.comment, res.html);
        applyIssueSubstatus(res);
        if (onSuccess) onSuccess(res);
    });
};

/**
 * Переключает ссылки взятия и снятия отметки о тестировании.
 *
 * Отметку снимает не только явное действие: её убирают и отметка о прохождении
 * теста, и найденные проблемы, и новый MR, и смена статуса задачи. Общий
 * признак у всех этих ответов один - подстатус, поэтому состояние ссылок
 * выводится из него: подстатус «Взята в тестирование» стоит тогда и только
 * тогда, когда стоит отметка (@see Issue::getSubstatus()).
 * @param {Number} substatus Уточнение статуса (Issue.SUBSTATUS_*).
 */
function applyUnderTesting(substatus) {
    if (substatus === undefined) return;

    issuePage.setUnderTesting(substatus === Issue.SUBSTATUS_UNDER_TESTING);
}

/**
 * Переключает ссылки взятия и снятия отметки о тестировании.
 * @param {Boolean} taken Стоит ли на задаче отметка о взятии.
 */
issuePage.setUnderTesting = function (taken) {
    const $bar = $('#issueView .scrum-comments-shortcut');
    $('.take-for-testing-icon', $bar).toggle(!taken);
    $('.release-from-testing-icon', $bar).toggle(!!taken);
};

issuePage.commentPassTesting = function () {
    issuePage.passTest();
};

issuePage.commentMergeInDevelop = function () {
    issuePage.merged();
};

issuePage.postComment = function () {
    const $form = $('#issueView .comments form.add-comment');
    const text = $('textarea[name=commentText]', $form).val();
    const requestChanges = $('input[name=requestChanges]', $form).is(':checked');
    const files = comments.getFiles($form);
    issuePage.postCommentForCurrentIssue(text, requestChanges, files);
    return false;
};

issuePage.previewComment = function (tabs) {
    let text = $('textarea[name=commentText]', tabs).val();

    let previewItem = $('.preview-comment', tabs);
    previewItem.empty().append(preloader.getNewIndicatorMedium());

    srv.issue.previewComment(text, (res) => {
        if (res.success) {
            previewItem.html(res.html);

            comments.updateAttachments($('.comment-text', previewItem));
            attachments.update($('.block-with-attachments', previewItem));
            initIssueLinkPreviews(previewItem);
            highlightCodeBlocks(previewItem);
        } else {
            srv.err(res);
        }
    });
};

// Switches the issue description field between the editor and a rendered
// Markdown preview, requesting the HTML from the server on each switch.
issuePage.toggleDescPreview = function ($form) {
    const $editor = $('.desc-editor', $form);
    const $preview = $('.preview-desc', $form);
    const $toggleBtn = $('.toggle-desc-preview', $form);
    // Formatting controls make no sense while previewing — hide them.
    const $editControls = $('.desc-toolbar .btn-group, .apply-desc-template', $form);

    if (!$preview.hasClass('d-none')) {
        issuePage.resetDescPreview($form);
        return;
    }

    const text = $('textarea[name=desc]', $editor).val();
    // Keep the card height stable across the swap so the page doesn't jump.
    const editorHeight = $editor.outerHeight();
    $editor.addClass('d-none');
    $editControls.addClass('d-none');
    $('.desc-preview-title', $form).removeClass('d-none');
    $preview.css('min-height', editorHeight + 'px').removeClass('d-none').empty().append(preloader.getNewIndicatorMedium());
    $toggleBtn.html('<i class="fas fa-pen me-1"></i>Редактор').attr('title', 'Вернуться к редактированию');

    srv.issue.previewIssueDesc(text, (res) => {
        if (res.success) {
            $preview.html(res.html);
            initIssueLinkPreviews($preview);
            highlightCodeBlocks($preview);
        } else {
            srv.err(res);
        }
    });
};

// Returns the description field to the editor state (used on toggle back and
// whenever the form is (re)populated).
issuePage.resetDescPreview = function ($form) {
    $('.preview-desc', $form).css('min-height', '').addClass('d-none').empty();
    $('.desc-editor', $form).removeClass('d-none');
    $('.desc-preview-title', $form).addClass('d-none');
    $('.desc-toolbar .btn-group, .apply-desc-template', $form).removeClass('d-none');
    $('.toggle-desc-preview', $form)
        .html('<i class="fas fa-eye me-1"></i>Предпросмотр')
        .attr('title', 'Предпросмотр');
};

// Refreshes the editor status bar: word count and character counter
// (used / total), tinting the latter as the description nears the limit.
issuePage.updateDescCounter = function ($form) {
    const $field = $('textarea[name=desc]', $form);
    if (!$field.length) {
        return;
    }

    const value = $field.val() || '';
    const max = parseInt($field.attr('maxlength'), 10) || 0;
    const used = value.length;
    const words = (value.match(/\S+/g) || []).length;
    // Rough silent-reading estimate at ~200 words per minute.
    const readMinutes = words === 0 ? 0 : Math.ceil(words / 200);

    $('.desc-words-counter .words', $form).text(words.toLocaleString('ru-RU'));
    $('.desc-read-time .value', $form).text(words === 0 ? '0 мин' : '~' + readMinutes + ' мин');

    const $counter = $('.desc-chars-counter', $form);
    $('.used', $counter).text(used.toLocaleString('ru-RU'));

    $counter.removeClass('text-warning text-danger');
    if (max && used >= max) {
        $counter.addClass('text-danger');
    } else if (max && max - used <= 1000) {
        $counter.addClass('text-warning');
    }
};

issuePage.doSomethingAndPostCommentForCurrentIssue = function (srvCall, onSuccess) {
    var issueId = $('#issueView .comments form.add-comment input[name=issueId]').val();

    // TODO проверку на пустоту
    if (issueId > 0) {
        preloader.show();
        srvCall(
            issueId,
            function (res) {
                preloader.hide();
                if (res.success) {
                    issuePage.addComment(res.comment, res.html);
                    applyIssueSubstatus(res);
                    if (res.linkedHtml) issuePage.updateLinkedIssues(res.linkedHtml);
                    if (onSuccess) onSuccess(res);
                } else {
                    srv.err(res);
                }
            }
        );
    }
}

issuePage.postCommentForCurrentIssue = function (text, requestChanges = false, files = []) {
    if (text.trim() == '' && files.length == 0) return;

    issuePage.doSomethingAndPostCommentForCurrentIssue(
        (issueId, handler) => srv.issue.comment(issueId, text, requestChanges, files, handler));
}

issuePage.merged = function () {
    let doMerge = function (complete) {
        issuePage.doSomethingAndPostCommentForCurrentIssue(
            (issueId, handler) => srv.issue.merged(issueId, complete, handler),
            res => {
                if (res.issue)
                    setIssueInfo(new Issue(res.issue), res.substatus);
                issuePage.updateStat();
            });
    }

    if (issuePage.isCompleted()) {
        doMerge(false);
    } else {
        const $modal = $('#mergeInDevelopConfirmModal');
        const modal = bootstrap.Modal.getOrCreateInstance($modal[0]);

        $modal.off('click.merge');
        $modal.on('click.merge', '[data-action="cancel"]', function () { modal.hide(); });
        $modal.on('click.merge', '[data-action="no"]', function () { doMerge(false); modal.hide(); });
        $modal.on('click.merge', '[data-action="yes"]', function () { doMerge(true); modal.hide(); });
        $modal.one('hidden.bs.modal', function () { $modal.off('click.merge'); });

        modal.show();
    }
}

issuePage.passTest = function () {
    passTest.show(issuePage.getIssueId());
}

issuePage.addComment = function (comment, html) {
    let elementId = 'delete_comment_' + comment.id;
    let commentTime = comment.date;
    $('#issueView .comments form.add-comment textarea[name=commentText]').val('');
    comments.clearFiles($('#issueView .comments form.add-comment'));
    $('#issueView .comments .comments-list').prepend(
        '<div class="comments-list-item">' + html + '</div>'
    );

    let newItem = $('#issueView .comments .comments-list .comments-list-item').first()
    comments.updateAttachments($('.comment-text', newItem));
    attachments.update($('.block-with-attachments', newItem));
    initIssueLinkPreviews(newItem);
    highlightCodeBlocks(newItem);

    comments.hideCommentForm();

    // Модератору удаление доступно всегда, остальным — только пока открыто окно.
    if (!$('#is-moderator').val()) {
        hideElementAfterDelay(elementId, commentTime, lpmOptions.commentDeleteWindow);
    }
};

/**
 * Режимы сортировки списка задач. Значения совпадают с константами Issue::SORT_*
 * и используются как состояние в адресе страницы, поэтому менять их нельзя -
 * сохранённые ссылки перестанут открывать нужный порядок.
 */
issuePage.sortKeys = ['last-created', 'test-priority', 'test-stale'];

issuePage.handleLastCreatedSort = function () {
    issuePage.setSort('last-created');
}

issuePage.handleTestPrioritySort = function () {
    issuePage.setSort('test-priority');
}

issuePage.handleTestStaleSort = function () {
    issuePage.setSort('test-stale');
}

issuePage.sortDefault = function () {
    issuePage.setSort('');
};

/**
 * Включает режим сортировки списка.
 *
 * Сортирует сервер: упорядочить можно только всю выборку, а на странице
 * показана лишь её часть - сортировка показанных строк переставляла бы задачи
 * внутри загруженной порции и выдавала бы это за порядок всего списка.
 */
issuePage.setSort = function (sortKey) {
    issuePage.applySortView(sortKey);
    issuePage.issuesList.setSort(sortKey);
};

/**
 * Настраивает вид списка под выбранный режим сортировки: отмечает его в меню
 * и при сортировке по приоритету показывает его значение в кружке каждой задачи.
 */
issuePage.applySortView = function (sortKey) {
    $('#issuesList').toggleClass('show-priority-values', sortKey === 'test-priority');
    issuePage.updateSortMenu(sortKey);
};

// Отмечает выбранный режим в меню сортировки. В заголовок кнопки режим
// выносится, только если он отличается от сортировки по умолчанию.
issuePage.updateSortMenu = function (sortKey) {
    var items = $('#issuesSortMenu').siblings('.dropdown-menu').find('[data-sort]');
    items.removeClass('fw-bold').find('.fa-check').addClass('invisible');

    var item = items.filter('[data-sort="' + sortKey + '"]').addClass('fw-bold');
    item.find('.fa-check').removeClass('invisible');

    $('#issuesSortMenu .issues-sort-title')
        .text(item.length && sortKey !== ''
            ? 'Сортировка: ' + item.text().trim().toLowerCase()
            : 'Сортировка');
};

/**
 * Применяет режим сортировки, заданный в адресе страницы,
 * чтобы ссылка на отсортированный список открывалась в том же порядке.
 */
issuePage.applySortFromHash = function () {
    var sortKey = window.location.hash.replace(/^#/, '');
    if (issuePage.sortKeys.indexOf(sortKey) === -1
            || !$('#issuesSortMenu ~ .dropdown-menu [data-sort="' + sortKey + '"]').length) {
        return;
    }

    issuePage.setSort(sortKey);
};

/**
 * Восстанавливает фильтр из адреса страницы.
 * Ключ `users` - исполнители, `testers` - тестировщики, `multi` - отбор задач
 * с несколькими исполнителями. Отсутствующий ключ означает выключённый фильтр,
 * поэтому ссылки, сохранённые до его появления, остаются рабочими.
 */
issuePage.handleFilterState = function (value) {
    const filters = value.trim() == '' ? [] : value.split(';');
    const tags = [];
    const memberIds = [];
    const testerIds = [];
    let multiMemberOnly = false;

    filters.forEach(filter => {
        const [key, value] = filter.split('=');
        if (key === 'tags') {
            tags.push(...decodeURI(value).split(','));
        } else if (key === 'users') {
            memberIds.push(...decodeURI(value).split(',').map(userId => parseInt(userId)));
        } else if (key === 'testers') {
            testerIds.push(...decodeURI(value).split(',').map(userId => parseInt(userId)));
        } else if (key === 'multi') {
            multiMemberOnly = value === '1';
        }
    });

    const filterVm = issuePage.filterVm;
    filterVm.selectedTags = tags;
    filterVm.setMultiMemberOnly(multiMemberOnly);
    filterVm.selectUsers(memberIds, testerIds);
}

issuePage.onFilterChanged = function (filter)  {
    const tags = filter.tags
    const memberIds = filter.memberIds
    const testerIds = filter.testerIds
    const multiMemberOnly = filter.multiMemberOnly
    issuePage.scrumColUpdateInfo(tags);
    if (tags.length || memberIds.length || testerIds.length || multiMemberOnly)  {
        let filters = [];
        if (tags.length) {
            filters.push(`tags=${encodeURI(tags.join(','))}`);
        }

        if (memberIds.length) {
            filters.push(`users=${encodeURI(memberIds.join(','))}`);
        }

        if (testerIds.length) {
            filters.push(`testers=${encodeURI(testerIds.join(','))}`);
        }

        if (multiMemberOnly) {
            filters.push('multi=1');
        }

        states.setState('filter:' + filters.join(';'), true);
    } else {
        states.setState('', true);
    }
}

issuePage.showIssuesByUser = function (memberId) {
    issuePage.filterVm.selectUsers([memberId], []);
};

/**
 * Переприменяет фильтры к задачам страницы.
 *
 * Нужно после изменений, из-за которых задача могла перестать подходить под
 * текущий отбор - например, сменился состав её исполнителей. Пересчёт счётчиков
 * доски входит в переприменение, поэтому звать его отдельно не надо.
 *
 * На страницах без компонента фильтров (личная Scrum доска) пересчитываются
 * только счётчики.
 */
issuePage.refreshFilteredIssues = function () {
    if (issuePage.filterVm) {
        issuePage.filterVm.applyFilters();
    } else {
        issuePage.scrumColUpdateInfo();
    }
};

issuePage.scrumColUpdateInfo = function () {
    // Группы, в которых не осталось видимых стикеров (например, после фильтрации),
    // скрываем целиком, чтобы не оставлять пустой заголовок группы.
    // Делаем это до подсчёта, иначе скрытая группа спрячет и свои стикеры.
    $('#scrumBoard .scrum-board-priority-group').each(function (i, el) {
        el.hidden = !$('.scrum-board-sticker', el).get().some((sticker) => !sticker.hidden);
    });

    const cols = ['col-todo', 'col-in_progress', 'col-testing', 'col-done'];
    // Свои и свободные задачи считаются раздельно: основное число - нагрузка
    // самого пользователя, свободные идут отдельной прибавкой к нему
    const colStickersSelector = (col) =>
        '#scrumBoard .scrum-board-table .scrum-board-col.' + col + ' .scrum-board-sticker';
    const getColStickersSelector = (col) => colStickersSelector(col) + ':not(.free):visible';
    const getColFreeStickersSelector = (col) => colStickersSelector(col) + '.free:visible';

    const sumSP = ($stickers) => {
        let sp = 0;
        $stickers.each((i, el) => {
            sp += parseFloat($(el).data('stickerSp'));
        });
        return sp;
    };
    const spStr = (sp) => parseInt(sp) == sp ? sp : sp.toFixed(1);

    let totalSP = 0;
    let totalNum = 0;
    let totalFreeSP = 0;
    let totalFreeNum = 0;
    for (let i = 0; i < cols.length; ++i) {
        const col = cols[i];
        const colStickers = $(getColStickersSelector(col));

        let sp = sumSP(colStickers);
        let num = colStickers.size();

        const freeStickers = $(getColFreeStickersSelector(col));
        const freeSP = sumSP(freeStickers);
        const freeNum = freeStickers.size();

        let selector = '#scrumBoard .scrum-board-table .' + col + ' .scrum-col-info';

        // Рядом со свободными своё число не прячем: ноль своих задач - это
        // ответ на вопрос "сколько у меня работы", а не отсутствие ответа
        if (num > 0 || freeNum > 0) {
            $(selector + ' .scrum-col-count .value').html(num);

            let spSelector = selector + ' .scrum-col-sp';
            if (sp > 0 || num == 0)
                $(spSelector).show();
            else
                $(spSelector).hide();

            $(spSelector + ' .value').html(spStr(sp));

            totalSP += sp;
            totalNum += num;

            $(selector).show();
        } else {
            $(selector).hide();
        }

        const freeSelector = '#scrumBoard .scrum-board-table .' + col + ' .scrum-col-free';

        if (freeNum > 0) {
            $(freeSelector + ' .scrum-col-free-count .value').html(freeNum);

            const freeSpSelector = freeSelector + ' .scrum-col-free-sp';
            if (freeSP > 0)
                $(freeSpSelector).show();
            else
                $(freeSpSelector).hide();

            $(freeSpSelector + ' .value').html(spStr(freeSP));

            totalFreeSP += freeSP;
            totalFreeNum += freeNum;

            $(freeSelector).show();
        } else {
            $(freeSelector).hide();
        }
    }

    if (totalNum || totalFreeNum) {
        $('#scrumBoard .scrum-board-info').show();
        $('#scrumBoard .scrum-board-info .scrum-board-count .value').html(totalNum);
        if (totalSP > 0 || totalNum == 0) {
            $('#scrumBoard .scrum-board-sp').show().find('.value').html(spStr(totalSP));
        }
        else
            $('#scrumBoard .scrum-board-sp').hide();
    } else {
        $('#scrumBoard .scrum-board-info').hide();
    }

    if (totalFreeNum) {
        $('#scrumBoard .scrum-board-free').show();
        $('#scrumBoard .scrum-board-free .scrum-board-free-count .value').html(totalFreeNum);
        if (totalFreeSP > 0) {
            $('#scrumBoard .scrum-board-free-sp').show().find('.value').html(spStr(totalFreeSP));
        }
        else
            $('#scrumBoard .scrum-board-free-sp').hide();
    } else {
        $('#scrumBoard .scrum-board-free').hide();
    }
}

issuePage.showExportXls = function () {
    issuesExport2Excel.openWindow(parseInt($("#projectView").data('projectId')));
}

function Issue(obj) {
    this._obj = obj;

    this.id = obj.id;
    this.author = obj.author;
    this.completeDate = obj.completeDate;
    this.completeDateInput = obj.completeDateInput;
    this.completedDate = obj.completedDate;
    this.createDate = obj.createDate;
    this.desc = obj.desc;
    this.formattedDesc = obj.formattedDesc;
    this.name = obj.name;
    this.status = obj.status;
    this.type = obj.type;
    this.revision = obj.revision;
    this.members = obj.members;
    this.priority = obj.priority;
    this.hours = obj.hours;
    this.testers = obj.testers;
    this.masters = obj.masters;
    this.images = obj.images;
    this.files = obj.files || [];
    this.isOnBoard = obj.isOnBoard;
    this.url = obj.url;
    this.linked = obj.linked;

    const getUsersHtml = (list, withSp) => {
        if (!list || !list.length) {
            return '<span class="text-muted">Не назначены</span>';
        }
        return list.map(user => Issue.renderUser(user, withSp)).join('');
    };

    this.getCompleteDate = function () {
        return this.getDate(this.completeDate);
    };

    this.hasCompleteDate = function () {
        return this.completeDate != 0;
    };

    this.getCompleteDateInput = function () {
        // Дата в ISO (YYYY-MM-DD), сформированная сервером: клиент не пересчитывает
        // её из таймстампа, чтобы не было сдвига на день из-за часового пояса.
        return this.completeDateInput || '';
    };

    this.getCompletedDate = function () {
        return this.getDate(this.completedDate);
    };

    this.getCreateDate = function () {
        return this.getDate(this.createDate);
    };

    this.getAuthorHtml = function () {
        return this.author ? this.author.linkedName : '';
    };

    this.getPriority = function () {
        var val = Issue.getPriorityDisplayVal(this.priority);
        // Текст кружка очищает updatePriorityVals(), оставляя цветную точку;
        // цвет и значение внутри кружка берутся из data-value
        return '<i class="fa-solid fa-angles-up me-1 align-middle" aria-hidden="true"></i>' +
            '<span class="priority-val circle" data-value="' + this.priority + '">' + val + '</span>' +
            Issue.getPriorityStr(this.priority) + ' (' + val + ')';
    };

    this.getMembersHtml = function () {
        return getUsersHtml(this.members, true);
    };

    this.getMemberIds = function () {
        return (this.members || []).map(member => member.userId);
    };

    this.getMembersSp = function () {
        return (this.members || []).map(member => member.sp);
    };

    this.getFiles = function () {
        return this.files;
    };

    this.getTestersHtml = () => getUsersHtml(this.testers);

    this.getTesterIds = function () {
        return (this.testers || []).map(tester => tester.userId);
    };

    this.getMastersHtml = () => getUsersHtml(this.masters);

    this.getMasterIds = function () {
        return (this.masters || []).map(master => master.userId);
    };

    this.getFilesForForm = function () {
        return (this.files || []).map(file => ({
            fileId: file.fileId,
            name: file.name || file.origName,
            url: file.url,
            size: file.size,
            sizeFormatted: file.sizeFormatted,
        }));
    };

    this.getLinkedBaseIds = function () {
        return this.linked?.filter(i => i.isBaseLinked)?.map(i => i.id) ?? [];
    };

    this.getLinkedChildrenIds = function () {
        return this.linked?.filter(i => !i.isBaseLinked)?.map(i => i.id) ?? [];
    };

    this.getDesc = function (formatted = false) {
        return formatted ? this.formattedDesc : this.desc;
    };

    this.getType = function () {
        switch (this.type) {
            case 1: return 'Ошибка';
            case 2: return 'Поддержка';
            default: return 'Разработка';
        }
    };

    this.isCompleted = function () {
        return this.status == 2;
    };

    this.isVerify = function () {
        return this.status == 1;
    };

    this.getDate = function (value) {
        if (!value) return '';

        const date = new Date((value + 3600) * 1000);
        // TODO разобраться что за хрень - почему на час разница?

        //return this._num2Str( date.getDate() ) + '-' + this._num2Str( date.getMonth() + 1 ) + '-' + date.getFullYear() + 
        //' ' + date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds() + ':' + date.getMilliseconds();

        return this._num2Str(date.getDate()) + '-' + this._num2Str(date.getMonth() + 1) + '-' + date.getFullYear();
    };

    this.getImagesUrl = function () {
        return this.images.map(img => img.source)
    };

    this._num2Str = function (val, dig) {
        if (!dig || dig < 1) dig = 1;
        else dig -= 1;

        var str = '';
        if (val < 0) str += '-';
        val = Math.abs(val);

        var i = dig - Math.floor(Math.log(val) / Math.log(10));
        while (i > 0) {
            str += '0';
            i--;
        }

        str += val;

        return str;
    };
};

/**
 * @param {Number} priority = 0..99
 */
Issue.getPriorityStr = function (priority) {
    if (priority < 33) return 'низкий';
    else if (priority < 66) return 'нормальный';
    else return 'высокий';
};

/**
 * @param {Number} priority = 0..99
 */
Issue.getPriorityDisplayVal = function (priority) {
    return priority + 1;
};

/**
 * Уточнения статуса задачи. Те же значения задаёт `IssueSubstatus` на сервере.
 */
Issue.SUBSTATUS_NONE = 0;
Issue.SUBSTATUS_BACKLOG = 1;
Issue.SUBSTATUS_TODO = 2;
Issue.SUBSTATUS_IN_PROGRESS = 3;
Issue.SUBSTATUS_PASS_TEST = 4;
Issue.SUBSTATUS_UNDER_TESTING = 5;

/**
 * Название статуса задачи: уточнение статуса показывается вместо него.
 * Те же названия задаёт `IssueViewHelper::statusLabel()` на сервере.
 * @param {Number} status
 * @param {Number} substatus Уточнение статуса (Issue.SUBSTATUS_*).
 */
Issue.getStatusLabel = function (status, substatus) {
    switch (substatus) {
        case Issue.SUBSTATUS_BACKLOG: return 'Бэклог';
        case Issue.SUBSTATUS_TODO: return 'К выполнению';
        case Issue.SUBSTATUS_IN_PROGRESS: return 'В работе';
        case Issue.SUBSTATUS_PASS_TEST: return 'Прошла тестирование';
        case Issue.SUBSTATUS_UNDER_TESTING: return 'Взята в тестирование';
    }

    switch (status) {
        case 1: return 'Ожидает проверки';
        case 2: return 'Завершена';
        default: return 'В работе';
    }
};

/**
 * Все классы бейджа статуса — снимаются перед тем, как поставить актуальный.
 */
Issue.STATUS_BADGE_CLASSES =
    'bg-primary bg-success bg-secondary badge-state-ready badge-state-testing badge-state-completed';

/**
 * Оформление бейджа статуса. Те же соответствия задаёт `IssueViewHelper` на сервере.
 * @param {Number} status
 * @param {Number} substatus Уточнение статуса (Issue.SUBSTATUS_*).
 */
Issue.getStatusBadgeClass = function (status, substatus) {
    switch (substatus) {
        case Issue.SUBSTATUS_BACKLOG: return 'bg-secondary';
        case Issue.SUBSTATUS_TODO: return 'badge-state-ready';
        case Issue.SUBSTATUS_IN_PROGRESS: return 'bg-primary';
        case Issue.SUBSTATUS_UNDER_TESTING: return 'badge-state-testing';
        case Issue.SUBSTATUS_PASS_TEST: return 'bg-success';
    }

    switch (status) {
        case 1: return 'badge-state-testing';
        case 2: return 'badge-state-completed';
        default: return 'bg-primary';
    }
};

/**
 * Все классы бейджа и иконки типа — снимаются перед тем, как поставить актуальные.
 */
Issue.TYPE_BADGE_CLASSES = 'bg-secondary bg-danger bg-info text-dark';
Issue.TYPE_ICON_CLASSES = 'fa-code fa-bug fa-life-ring';

/**
 * Оформление бейджа типа. Те же соответствия задаёт `IssueViewHelper` на сервере.
 * @param {Number} type
 */
Issue.getTypeBadgeClass = function (type) {
    switch (type) {
        case 1: return 'bg-danger';
        case 2: return 'bg-info text-dark';
        default: return 'bg-secondary';
    }
};

/**
 * Иконка типа задачи — только сам глиф: класс начертания (`fa-solid`)
 * задан в разметке и не меняется.
 * @param {Number} type
 */
Issue.getTypeIconClass = function (type) {
    switch (type) {
        case 1: return 'fa-bug';
        case 2: return 'fa-life-ring';
        default: return 'fa-code';
    }
};

/**
 * Класс состояния задачи: определяет, какие даты и кнопки видны.
 * @param {Number} status
 */
Issue.getStatusStateClass = function (status) {
    switch (status) {
        case 1: return 'verify-issue';
        case 2: return 'completed-issue';
        default: return 'active-issue';
    }
};

/**
 * Все классы бейджа и иконки срока — снимаются перед тем, как поставить актуальные.
 */
Issue.DEADLINE_BADGE_CLASSES = 'bg-danger bg-warning bg-white text-dark border';
Issue.DEADLINE_ICON_CLASSES = 'fa-solid fa-regular fa-calendar-xmark fa-fire fa-calendar-day fa-calendar-check';

/**
 * Насколько поджимает срок выполнения задачи. Те же пороги задаёт
 * `IssueViewHelper` на сервере.
 * @param {Issue} issue
 * @returns {String} outdated|urgent|medium|low; пустая строка, если срок не
 * задан или задача завершена — тогда подсвечивать нечего.
 */
Issue.getDeadlineLevel = function (issue) {
    if (issue.isCompleted() || !issue.hasCompleteDate()) {
        return '';
    }

    // Сравниваем с началом сегодняшнего дня, чтобы задача со сроком «сегодня»
    // не считалась просроченной
    const dayStart = new Date();
    dayStart.setHours(0, 0, 0, 0);
    const days = (issue.completeDate * 1000 - dayStart.getTime()) / 86400000;

    if (days < 0) return 'outdated';
    if (days < 2) return 'urgent';
    if (days < 7) return 'medium';
    return 'low';
};

/**
 * Оформление бейджа срока выполнения.
 * @param {String} level Уровень из getDeadlineLevel().
 */
Issue.getDeadlineBadgeClass = function (level) {
    switch (level) {
        case 'outdated': return 'bg-danger';
        case 'urgent':
        case 'medium': return 'bg-warning text-dark';
        default: return 'bg-white text-dark border';
    }
};

/**
 * Иконка срока выполнения.
 * @param {String} level Уровень из getDeadlineLevel().
 */
Issue.getDeadlineIconClass = function (level) {
    switch (level) {
        case 'outdated': return 'fa-solid fa-calendar-xmark';
        case 'urgent': return 'fa-solid fa-fire';
        case 'medium': return 'fa-solid fa-calendar-day';
        default: return 'fa-regular fa-calendar-check';
    }
};

/**
 * Разметка участника задачи — повторяет шаблон `components/issue-user`.
 * @param {Object} user Участник (с полями linkedName, avatarUrl и, возможно, sp).
 * @param {Boolean} withSp Выводить ли оценку участника в story points.
 */
Issue.renderUser = function (user, withSp) {
    const avatar = user.avatarUrl
        ? '<img class="rounded-circle" src="' + user.avatarUrl + '" alt="" width="22" height="22" loading="lazy" />'
        : '';
    const sp = withSp && user.sp > 0
        ? '<span class="text-muted x-small">' + user.sp + '&nbsp;SP</span>'
        : '';
    return '<span class="issue-user d-inline-flex align-items-start gap-1">'
        + avatar + user.linkedName + sp + '</span>';
};

Issue.getCommitMessage = function (num, title) {
    return 'Issue #' + num + ': ' + title;
}

/**
 * Возвращает название задачи "По доделкам"
 */
Issue.getCompletionName = function (issueName, prefix = 'Доделать задачу') {
    const lastTagIndex = issueName.lastIndexOf(']');
    return (~lastTagIndex) ?
        `${issueName.substring(0, lastTagIndex + 1)} ${prefix} ${issueName.substring(lastTagIndex + 1).trim()}`
        : `${prefix} ${issueName.trim()}`;
}

issuePage.deleteComment = (id, deleteBranch, callback) => {
    srv.issue.deleteComment(
        id,
        deleteBranch,
        function (res) {
            if (res.success) {
                applyIssueSubstatus(res);
                callback(res);
            } else {
                srv.err(res);
            }
        }
    )
};

issuePage.resolveComment = (id, callback) => {
    srv.issue.resolveComment(
        id,
        function (res) {
            if (res.success) {
                applyIssueSubstatus(res);
                callback(res);
            } else {
                srv.err(res);
            }
        }
    )
};

function hideElementAfterDelay(elementId, startTimeInSeconds, delayTimeInSeconds) {
    let delay = (Number(startTimeInSeconds) + Number(delayTimeInSeconds)) * 1000 - Date.now();

    if (delay >= 0) {
        const timerId = setTimeout(() => {
            $('#' + elementId).remove();
            clearTimeout(timerId);
        }, delay);
    } else {
        $('#' + elementId).remove();
    }
}


function highlightIssueRow($row) {
    $row.removeClass('highlight-fade');
    // Форсируем reflow, чтобы повторное добавление класса перезапускало анимацию.
    void $row[0].offsetWidth;
    $row.addClass('highlight-fade').one('animationend', function () {
        $(this).removeClass('highlight-fade');
    });
}
