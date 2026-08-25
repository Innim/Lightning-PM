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
        var dd = new DropDown($('#dropdown'));

        $(document).on('click', '#issuesSortMenu ~ .dropdown-menu [data-sort]', function () {
            states.setState($(this).data('sort'));
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

        // Insert standard description template
        $('#issueForm .apply-desc-template').on('click', function () {
            const $field = $('#issueForm textarea[name=desc]');
            const el = $field[0];
            const tmplStart = "### Проблема\n\n";
            const tmplEndSection = "### Что сделать\n\n";

            const current = $field.val() || '';
            const hasTemplate = current.indexOf(tmplStart.trim()) !== -1 || current.indexOf(tmplEndSection.trim()) !== -1;

            // Empty field: insert both parts and place caret after tmplStart
            if (!current.trim()) {
                const full = tmplStart + "\n\n" + tmplEndSection;
                $field.val(full);
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
            const newValueEnd = tmplEndSection + after;
            const newValue = newValueStart + "\n\n" + newValueEnd;
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
                hideElementAfterDelay(elementId, startTime);
            });
        }

        $('div.copy-tooltip').hover(
            function () {
                $(this).find('div').clearQueue().show();
            },
            function () {
                $(this).find('div')
                    .animate({ width: 'width' + 20, height: 'height' + 20 }, 150)
                    .animate({ width: 'hide', height: 'hide' }, 1);
            }
        )

        bindFormattingHotkeys('#issueForm form textarea[name=desc]');
        bindFormattingHotkeys('form.add-comment textarea[name=commentText]');
        bindFormattingHotkeys('form.pass-test #passTestComment textarea.comment-text-field');
    }
);

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
                    if (!hasSelection) return; // requires selection
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

function DropDown(el) {
    this.dd = el;
    //this.placeholder = this.dd.children('span');
    this.opts = this.dd.find('ul#priority-values > li');
    this.val = '';
    this.initEvents();
}
DropDown.prototype = {
    initEvents: function () {
        var obj = this;

        obj.opts.click(function () {
            var opt = $(this);
            obj.val = opt.text();
            issuePage.setPriorityVal(obj.val.match(/\d+/) - 1);
        });
    }
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
            const url = targetProject.url + '#' + (typeof hash === 'function' ? hash(issueId) : hash + ':' + issueId);
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

issuePage.updatePriorityVals = function () {
    issuePage.setPriorityVal($('input[type=range]#priority').val());
    //issuePage.setPriorityVal( $('input[type=range]#priority').val() );
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

issuePage.setPriorityVal = function (value) {
    let valueInt = parseInt(value);
    let title = Issue.getPriorityStr(valueInt);
    let displayVal = Issue.getPriorityDisplayVal(valueInt);
    $('#priority').val(valueInt);

    $('#priorityVal').html('<i class="fa-solid fa-angles-up" aria-hidden="true"></i> '
        + title + ' (' + displayVal + ')');
    $('#priorityVal').css('backgroundColor', issuePage.getPriorityColor(valueInt));
};

issuePage.upPriorityVal = function () {
    var value = $('#priority').val();
    if (value < 99) {
        value++;
        issuePage.setPriorityVal(value);
    };
}

issuePage.downPriorityVal = function () {
    var value = $('#priority').val();
    if (value > 0) {
        value--;
        issuePage.setPriorityVal(value);
    };
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

issuePage.updateStat = function () {
    if ($("#projectView").length == 0) return;

    $(".project-stat .issues-opened").text($("#issuesList > tbody > tr.active-issue,tr.verify-issue").size());
    $(".project-stat .issues-completed").text($("#issuesList > tbody > tr.completed-issue").size());

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
    const html = `<a href="${url}">${text}</a>`;

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
    // Special handling for blockquote: prefix every selected line with "> "
    if (single && marker === '> ') {
        const $input = $(input);
        const el = $input[0];
        const start = el.selectionStart;
        const end = el.selectionEnd;

        // Selected text only; do not auto-expand to full lines to keep behavior predictable
        const selected = el.value.substring(start, end);

        // Prefix every line (including empty) with marker
        const transformed = selected.split('\n').map(function (line) { return marker + line; }).join('\n');

        const newValue = el.value.substring(0, start) + transformed + el.value.substring(end);

        $input.val(newValue).trigger('input');

        // Place caret at the end of the inserted block
        setCaretPosition(el, start + transformed.length);
        return;
    } else {
        insertFormatting(input, marker, single ? "" : marker)
    }
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
                            $("#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])").remove();
                            showMain();
                        } else if ($('#issueView').length > 0) {
                            setIssueInfo(new Issue(res.issue));
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

issuePage.changePriority = function (e) {
    var $control = $(e.currentTarget);
    var $row = $control.parents('tr');
    var issueId = $('input[name=issueId]', $row).val();
    var delta = $control.hasClass('priority-up') ? 1 : -1;

    if (issueId > 0) {
        srv.issue.changePriority(issueId, delta, function (res) {
            if (res.success) {
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

                var status = $row.data("status");
                var date = $row.data("completeDate");
                var compare = function ($r) {
                    if ($r.data("status") != status)
                        return 0;
                    var p = parseInt($(".priority-val", $r).data("value"));
                    if (p != priority)
                        return priority - p;
                    else if ($r.data("completeDate") != date)
                        return $r.data("completeDate") - date;
                    else
                        return $r.data("id") - issueId;
                }

                if (delta < 0) {
                    var $next = $row;
                    var $last = null;
                    while ($next) {
                        var $next = $next.next();

                        if (compare($next) < 0) {
                            $last = $next;
                        }
                        else {
                            if ($last) {
                                $last.after($row);
                                highlightIssueRow($row);
                            }
                            break;
                        }
                    }
                } else {
                    var $prev = $row;
                    var $first = null;
                    while ($prev) {
                        var $prev = $prev.prev();
                        if (compare($prev) > 0) {
                            $first = $prev;
                        }
                        else {
                            if ($first) {
                                $first.before($row);
                                highlightIssueRow($row);
                            }
                            break;
                        }
                    }
                }
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
                    $("#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])").remove();
                    showMain();
                } else if ($('#issueView').length > 0) {
                    setIssueInfo(new Issue(res.issue));
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
                    setIssueInfo(new Issue(res.issue));
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
    const issueId = $('#issueInfo').data('issueId');
    srv.issue.putStickerOnBoard(issueId, function (res) {
        preloader.hide();
        if (!res.success) {
            srv.err(res);
            return;
        }

        $('#issueInfo .scrum-put-sticker').remove();
        $('#issueInfo').data('isOnBoard', true);
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
                setIssueInfo(new Issue(res.issue));
            } else {
                srv.err(res);
            }
        }
    );
};

issuePage.showAddForm = function (type) {
    states.setState('add-issue');

    if (typeof type != 'undefined') {
        $('form input:radio[name=type]:checked', "#issueForm").prop('checked', true);
        $('form input:radio[value=1]', "#issueForm").prop('checked', true);

        const bugTemplate = `### Описание

📝 Описание проблемы

### Предусловие

📝 Начальные условия, при которых воспроизводится проблема

### Шаги воспроизведения

1. 📝  Шаги для воспроизведения
2. 

*ФР*: 📝  Фактический полученный результат

*ОР*: 📝  Ожидаемый результат

### Окружение

📝 Укажите устройство, ОС, окружение и тп

### Видео

🎥 Приложите ссылку на видео, где показана проблема
        `;
        
        $('form textarea[name=desc]', '#issueForm').html(bugTemplate).css('height', '500px');
    } else {
        $('form input:radio[name=type]:checked', "#issueForm").prop('checked', true);
        $('form input:radio[value=0]', "#issueForm").prop('checked', true);
        $('form textarea[name=desc]', '#issueForm').html('').css('height', '');
    }
};

issuePage.showEditForm = function () {
    issueForm.acquireLock(issuePage.getIssueId(), issuePage.getRevision(), false, function () {
        // переключаем вид
        states.setState('edit');
    });
};

/**
 * 
 * @param {Issue} issue
 */
function setIssueInfo(issue) {
    const $issueInfo = $("#issueInfo");

    // Разметка сама сообщает, какой вид открыт, — флаг настроек в JS не нужен
    if ($issueInfo.hasClass('issue-card')) {
        setIssueInfoCard(issue, $issueInfo);
    } else {
        setIssueInfoLegacy(issue, $issueInfo);
    }

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
 * Обновляет обновлённый вид задачи (шаблон issue.html).
 * @param {Issue} issue
 * @param {jQuery} $issueInfo
 */
function setIssueInfoCard(issue, $issueInfo) {
    $(".issue-name", $issueInfo).text(issue.name);

    // Каждое поле помечено в разметке своим data-field, поэтому порядок блоков
    // на странице можно менять, не трогая обновление
    const values = {
        status: issue.getStatus(),
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

    $(".issue-status-badge", $issueInfo)
        .removeClass(Issue.STATUS_BADGE_CLASSES)
        .addClass(Issue.getStatusBadgeClass(issue.status));

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

    $issueInfo.data('status', issue.status);
};

/* ======== СТАРЫЙ ВИД СТРАНИЦЫ ЗАДАЧИ (шаблон issue-legacy.html) ========
   Показывается, пока выключен экспериментальный флаг newIssueView.
   Удаляется целиком вместе с шаблоном и одноимённым блоком в main.css. */

/**
 * Обновляет прежний вид задачи (шаблон issue-legacy.html): значения полей
 * подставляются по их порядку в разметке, а состояние задачи задаётся
 * классами на .info-list и .buttons-bar.
 * @param {Issue} issue
 * @param {jQuery} $issueInfo
 */
function setIssueInfoLegacy(issue, $issueInfo) {
    $(".issue-name", $issueInfo).text(issue.name);

    // В строках участников имена лежат во вложенном блоке,
    // чтобы ссылка быстрого добавления себя не затиралась при обновлении
    const fields = $("> .info-list > div > .value", $issueInfo).map(function () {
        return $(this).children('.participants')[0] || this;
    }).get();

    $("#issueView").removeClass('issue-testing');

    $(".info-list, .buttons-bar", $issueInfo)
        .removeClass('active-issue verify-issue completed-issue');

    if (issue.isCompleted()) {
        $(".info-list, .buttons-bar", $issueInfo).addClass('completed-issue');
    } else if (issue.isOpened()) {
        $(".info-list, .buttons-bar", $issueInfo).addClass('active-issue');
    } else if (issue.isVerify()) {
        $(".info-list, .buttons-bar", $issueInfo).addClass('verify-issue');
        $("#issueView").addClass('issue-testing');
    }

    const values = [
        issue.getStatus(),
        issue.getType(),
        issue.getPriority(),
        issue.getCreateDate(),
        issue.getCompleteDate(),
        issue.getCompletedDate(),
        issue.getAuthor(),
        issue.getMembers(),
        issue.getTesters(),
        issue.getMasters(),
        issue.getDesc(true)
    ];

    for (var i = 0; i < values.length; i++) {
        fields[i].innerHTML = values[i];
    }

    const $completeDate = $('.issue-complete-date-row', $issueInfo);
    if (issue.hasCompleteDate()) {
        $completeDate.show();
    } else {
        $completeDate.hide();
    }

    issuePage.updateAddMeLinks(issue);

    issuePage.updatePriorityVals();

    $issueInfo.data('status', issue.status);
};

/* ======== конец старого вида страницы задачи ======== */

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
                    setIssueInfo(new Issue(res.issue));
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
    let elementId = 'comment_' + comment.id;
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

    hideElementAfterDelay(elementId, commentTime);
};

issuePage.handleLastCreatedSort = function () {
    issuePage.sortIssues('last-created');
}

issuePage.handleTestPrioritySort = function () {
    issuePage.sortIssues('test-priority');
}

issuePage.handleTestStaleSort = function () {
    issuePage.sortIssues('test-stale');
}

/**
 * Компараторы строк списка задач по ключу сортировки.
 * Режимы «в тесте» переставляют только задачи в тесте, порядок остальных
 * задач при этом сохраняется (компаратор возвращает для них 0).
 */
issuePage.sortComparators = {
    'last-created': function (a, b) {
        return $(b).data('createDate') - $(a).data('createDate');
    },
    'test-priority': function (a, b) {
        return issuePage.compareTestIssues(a, b, function ($a, $b) {
            return $b.data('priority') - $a.data('priority')
                || issuePage.testActivity($a) - issuePage.testActivity($b);
        });
    },
    'test-stale': function (a, b) {
        return issuePage.compareTestIssues(a, b, function ($a, $b) {
            return issuePage.testActivity($a) - issuePage.testActivity($b)
                || $b.data('priority') - $a.data('priority');
        });
    }
};

/**
 * Сравнивает две строки списка для режимов сортировки задач в тесте:
 * задачи в тесте поднимаются выше остальных и упорядочиваются переданной
 * функцией, порядок остальных задач не меняется.
 */
issuePage.compareTestIssues = function (a, b, compare) {
    var $a = $(a);
    var $b = $(b);
    var aInTest = $a.data('status') === lpmOptions.issueStatuses.test;
    var bInTest = $b.data('status') === lpmOptions.issueStatuses.test;

    if (!aInTest && !bInTest) return 0;
    if (aInTest != bInTest) return aInTest ? -1 : 1;

    return compare($a, $b);
};

// Дата последней активности по задаче в тесте (для задачи с багом - дата бага).
// Если активность неизвестна - считаем ей дату создания задачи.
issuePage.testActivity = function ($row) {
    return $row.data('testActivity') || $row.data('createDate');
};

/**
 * Возвращает строки списка задач в порядке по умолчанию, запоминая его
 * при первом обращении, чтобы к нему можно было вернуться и чтобы любая
 * сортировка выполнялась именно от него.
 * Запоминаются сами строки, а не их разметка: состояние строки меняется
 * на месте (цвет кружка приоритета, скрытие фильтром), и снимок разметки
 * это состояние терял бы. Удалённые со страницы строки отбрасываются.
 * @param {jQuery} $body тело таблицы списка задач
 * @returns {Element[]}
 */
issuePage.getDefaultIssues = function ($body) {
    if (window.defaultIssues === undefined) {
        window.defaultIssues = $body.children('tr').get();
    } else {
        window.defaultIssues = window.defaultIssues.filter(function (row) {
            return $.contains(document.documentElement, row);
        });
    }

    return window.defaultIssues;
};

/**
 * Сортирует список задач заданным режимом.
 */
issuePage.sortIssues = function (sortKey) {
    var $body = $('#issuesList > tbody');
    $body.append(issuePage.getDefaultIssues($body).slice()
        .sort(issuePage.sortComparators[sortKey]));
    issuePage.applySortView(sortKey);
};

issuePage.sortDefault = function () {
    if (window.defaultIssues !== undefined) {
        var $body = $('#issuesList > tbody');
        $body.append(issuePage.getDefaultIssues($body));
        // Порядок по умолчанию меняется прямо на странице (изменение приоритета
        // переставляет строку), поэтому он запоминается заново при следующей сортировке.
        window.defaultIssues = undefined;
    }
    issuePage.applySortView('');
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
    if (!/^[a-z-]+$/.test(sortKey)
            || !Object.prototype.hasOwnProperty.call(issuePage.sortComparators, sortKey)
            || !$('#issuesSortMenu ~ .dropdown-menu [data-sort="' + sortKey + '"]').length) {
        return;
    }

    issuePage.sortIssues(sortKey);
};

issuePage.handleFilterState = function (value) {
    const filters = value.trim() == '' ? [] : value.split(';');
    const tags = [];
    const userIds = [];

    filters.forEach(filter => {
        const [key, value] = filter.split('=');
        if (key === 'tags') {
            tags.push(...decodeURI(value).split(','));
        } else if (key === 'users') {
            userIds.push(...decodeURI(value).split(',').map(userId => parseInt(userId)));
        }
    });

    const filterVm = issuePage.filterVm;
    filterVm.selectedTags = tags;
    filterVm.selectUsers(userIds)
}

issuePage.onFilterChanged = function (filter)  {
    const tags = filter.tags
    const users = filter.users
    issuePage.scrumColUpdateInfo(tags);
    if (tags.length || users.length)  {
        let filters = [];
        if (tags.length) {
            filters.push(`tags=${encodeURI(tags.join(','))}`);
        }

        if (users.length) {
            filters.push(`users=${encodeURI(users.map(user => user.userId).join(','))}`);
        }

        states.setState('filter:' + filters.join(';'), true);
    } else {
        states.setState('', true);
    }
}

issuePage.showIssuesByUser = function (memberId) {
    issuePage.filterVm.selectUsers([memberId]);
};

issuePage.scrumColUpdateInfo = function () {
    // Группы, в которых не осталось видимых стикеров (например, после фильтрации),
    // скрываем целиком, чтобы не оставлять пустой заголовок группы.
    // Делаем это до подсчёта, иначе скрытая группа спрячет и свои стикеры.
    $('#scrumBoard .scrum-board-priority-group').each(function (i, el) {
        el.hidden = !$('.scrum-board-sticker', el).get().some((sticker) => !sticker.hidden);
    });

    const cols = ['col-todo', 'col-in_progress', 'col-testing', 'col-done'];
    const getColStickersSelector = (col) =>
        '#scrumBoard .scrum-board-table .scrum-board-col.' + col + ' .scrum-board-sticker:visible';

    let totalSP = 0;
    let totalNum = 0;
    for (let i = 0; i < cols.length; ++i) {
        const col = cols[i];
        const colStickers = $(getColStickersSelector(col));

        let sp = 0;
        colStickers.each((i, el) => {
            sp += parseFloat($(el).data('stickerSp'));
        });

        let num = colStickers.size();

        let selector = '#scrumBoard .scrum-board-table .' + col + ' .scrum-col-info';

        if (num > 0) {
            $(selector + ' .scrum-col-count .value').html(num);

            let spSelector = selector + ' .scrum-col-sp';
            if (sp > 0)
                $(spSelector).show();
            else
                $(spSelector).hide();

            let spScr = parseInt(sp) == sp ? sp : sp.toFixed(1);
            $(spSelector + ' .value').html(spScr);

            totalSP += sp;
            totalNum += num;

            $(selector).show();
        } else {
            $(selector).hide();
        }
    }

    if (totalNum) {
        $('#scrumBoard .scrum-board-info').show();
        $('#scrumBoard .scrum-board-info .scrum-board-count .value').html(totalNum);
        if (totalSP > 0) {
            let totalSpScr = parseInt(totalSP) == totalSP ? totalSP : totalSP.toFixed(1);
            $('#scrumBoard .scrum-board-sp').show().find('.value').html(totalSpScr);
        }
        else
            $('#scrumBoard .scrum-board-sp').hide();
    } else {
        $('#scrumBoard .scrum-board-info').hide();
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

    // Списки участников простым текстом — нужны старому виду задачи
    // (issue-legacy.html), в обновлённом виде выводятся плашки с аватарами
    const getUsersStr = (list) => {
        if (!list) {
            return '';
        }
        return list.map(user => user.linkedName).join(', ');
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

    /* ==== СТАРЫЙ ВИД СТРАНИЦЫ ЗАДАЧИ: значения полей простым текстом ==== */

    this.getAuthor = function () {
        return this.author ? this.author.linkedName : '';
    };

    this.getMembers = function () {
        if (!this.members || !this.members.length) {
            return 'Не назначены';
        }
        return this.members
            .map(member => member.linkedName + (member.sp ? ' (' + member.sp + ' SP)' : ''))
            .join(', ');
    };

    this.getTesters = () => getUsersStr(this.testers) || 'Не назначены';

    this.getMasters = () => getUsersStr(this.masters) || 'Не назначены';

    /* ==== конец блока старого вида ==== */

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

    this.getStatus = function () {
        switch (this.status) {
            case 1: return 'Ожидает проверки';
            case 2: return 'Завершена';
            default: return 'В работе';
        }
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

    this.isOpened = function () {
        return this.status == 0;
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
 * Все классы бейджа статуса — снимаются перед тем, как поставить актуальный.
 */
Issue.STATUS_BADGE_CLASSES = 'bg-primary bg-warning bg-success text-dark';

/**
 * Оформление бейджа статуса. Те же соответствия задаёт `IssueViewHelper` на сервере.
 * @param {Number} status
 */
Issue.getStatusBadgeClass = function (status) {
    switch (status) {
        case 1: return 'bg-warning text-dark';
        case 2: return 'bg-success';
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

// Всплывающее окно скопировать commit сообщение

jQuery(function ($) {

    $('.issues-list > tbody > tr > td:first-of-type a').mouseenter(
        function () {
            $(this).next('.issue_copy.popup-menu').slideDown(180);
        }
    );

    $('.issues-list > tbody > tr > td:first-of-type').mouseleave(
        function () {
            $('.issue_copy.popup-menu').slideUp(180);
        }
    );

    $('.issue_copy.popup-menu').hover(
        function () {
            $(this).show();
        },
        function () {
            $(this).slideUp(180);
        }
    );

});

issuePage.deleteComment = (id, deleteBranch, callback) => {
    srv.issue.deleteComment(
        id,
        deleteBranch,
        function (res) {
            if (res.success) {
                callback(true);
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
                callback(res);
            } else {
                srv.err(res);
            }
        }
    )
};

function hideElementAfterDelay(elementId, startTimeInSeconds, delayTimeInSeconds = 600) {
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
