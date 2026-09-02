$(function ($) {
    document.addEventListener('paste', pasteClipboardImage);
    $('.images-list').on('click', '.pasted-img .remove-img', function () {
        $(this).parent('.pasted-img').remove();
        issueForm.refreshImageSlots();
    });
    $('#issueForm').on('click', '.remove-upload-input', function (e) {
        e.preventDefault();
        issueForm.removeSelectedUploadInput(this);
    });
    $('.files-list').on('click', '.remove-file', function (e) {
        e.preventDefault();
        issueForm.removeFile(e);
    });
    $('#issueForm .files-list').on('change', "input[name='issueFiles[]']", function (e) {
        issueForm.onFileUploadInputChange(e);
        issueForm.toggleRemoveUploadBtn(this);
    });
    $('#issueForm .images-list').on('change', "input[name='images[]']", function (e) {
        imgUpload.onSelect(e, window.lpmOptions.issueImgsCount);
        
        $('#issueForm .images-list li').each(function () {
            var input = $('input[type=file]', this)[0];
            if (input) issueForm.toggleRemoveUploadBtn(input);
        });
    });

    // Диалог черновика показывается через lpm.dialog, т.е. его разметка
    // добавляется и удаляется на лету — обработчики только делегированные.
    $(document).on('change', '.modal.show #aiIssueDraftImages', function () {
        issueForm.addDraftImages(this.files);
        // Сброс значения позволяет выбрать тот же файл ещё раз после удаления.
        this.value = '';
    });
    $(document).on('click', '.modal.show .remove-draft-image', function (e) {
        e.preventDefault();

        // Перерисовка списка отцепляет от DOM элемент, по которому только что
        // кликнули. Глобальный обработчик iLoad (lpm-scripts/libs/iLoad.js)
        // поднимается от event.target вверх по parentNode и выходит из цикла
        // только на document.body — у отцепленного узла цепочка обрывается на
        // null, и он падает с TypeError. Поэтому DOM меняем следующей задачей,
        // когда событие уже разошлось по документу. По той же причине штатные
        // кнопки удаления в форме убирают элемент из колбэка диалога
        // подтверждения, а не прямо в обработчике клика.
        const image = $(this).data('image');
        setTimeout(function () {
            issueForm.removeDraftImage(image);
        }, 0);
    });
    document.addEventListener('paste', pasteDraftImage);

    function pasteDraftImage(event) {
        // Только для открытого диалога черновика. Вставка в саму форму задачи
        // обрабатывается отдельно (pasteClipboardImage ниже), а диалог живёт
        // вне #issueForm, поэтому обработчики не пересекаются.
        if (!$('.modal.show .ai-issue-draft-body').length) return;

        const clipboard = event.clipboardData;
        if (!clipboard || !clipboard.files || !clipboard.files.length) return;

        issueForm.addDraftImages(clipboard.files);
    }

    function pasteClipboardImage(event) {
        // Только для формы задачи. Вставка в комментарии обрабатывается отдельно (см. comments.js).
        if (!$(event.target).closest('#issueForm').length) return;

        var clipboard = event.clipboardData;

        if (clipboard && clipboard.items) {
            // В буфере обмена может быть только один элемент
            var item = clipboard.items[0];

            if (item && item.type.indexOf('image/') > -1) {
                // Получаем картинку в виде блога
                var blob = item.getAsFile();

                if (blob) {
                    // Читаем файл и вставляем его в data:uri
                    var reader = new FileReader();

                    reader.onload = function (event) {
                        issueForm.addPreparedImage(event.target.result);
                    }

                    reader.readAsDataURL(blob);
                }
            }
        }
    };

    function getMembers(selector) {
        let members = [];
        $(selector).each(function () {
            let userId = $(this).val();
            if (userId > 0) {
                members.push({ userId: userId, name: $(this).text() });
            }
        });
        return members;
    }

    issueForm.members = getMembers("#addIssueMembers option");
    issueForm.testers = getMembers("#addIssueTesters option");
    issueForm.masters = getMembers("#addIssueMasters option");
    issueForm.defaultMemberId = $('#addIssueMembers').data('defaultMemberId');

    issueForm.ensureFileUploadSlot();
    issueForm.refreshUploadRemoveButtons();
});

let issueForm = {
    inputForRestore: null,
    members: null,
    defaultMemberId: null,
    testers: null,
    masters: null,
    fileUploadTemplate: null,
    lockAcquired: false,
    /**
     * Отправка формы уже идёт: повторные отправки до её завершения запрещены.
     */
    submitting: false,
    acquireLock: function (issueId, revision, forced, onSuccess, onFail) {
        preloader.show();

        srv.issue.lockIssue(issueId, revision, forced, function (res) {
            preloader.hide();
            if (res.success) {
                issueForm.lockAcquired = true;
                onSuccess();
            } else {
                const errno = res.errno;
                switch (errno) {
                    case 201:
                        lpm.dialog.show({
                            title: 'Задача заблокирована',
                            text: 'Задача заблокирована Вами: возможно задача редактируется в другом окне.',
                            secondaryBtn: 'Переписать блокировку',
                            secondaryBtnClass: 'btn-warning',
                            onSecondary: function () {
                                issueForm.acquireLock(issueId, revision, true, onSuccess, onFail);
                            },
                            onCancel: onFail, 
                        });
                        break;
                    case 202:
                        lpm.dialog.show({
                            title: 'Задача заблокирована',
                            content: res.dialogHtml,
                            secondaryBtn: 'Принудительно перехватить',
                            secondaryBtnClass: 'btn-warning',
                            onSecondary: function () {
                                // Дополнительное подтверждение показываем отдельным окном —
                                // оно откроется после закрытия текущего (см. очередь в lpm.dialog).
                                // Любой отказ (кнопка «Отмена» или закрытие) вызывает onFail.
                                lpm.dialog.show({
                                    title: 'Принудительный перехват',
                                    text: 'Вы уверены, что хотите принудительно перехватить задачу? Это может привести к потере данных.',
                                    primaryBtn: 'Перехватить',
                                    onPrimary: function () {
                                        issueForm.acquireLock(issueId, revision, true, onSuccess, onFail);
                                    },
                                    secondaryBtn: 'Отмена',
                                    onSecondary: function () { if (onFail) onFail(); },
                                    onCancel: function () { if (onFail) onFail(); },
                                });
                            },
                            onCancel: onFail,
                        });
                        break;
                    default:
                        srv.err(res);
                        if (onFail) onFail();
                }
            }
        });
    },
    cancel: function () {
        const issueId = issueForm.getIssueId();
        const leave = function () {
            issueForm.onHide();
            showMain();
        };

        if (issueId > 0) {
            if (issueForm.lockAcquired) {
                preloader.show();
                const revision = issueForm.getRevision();
                srv.issue.unlockIssue(issueId, revision, function (_) {
                    issueForm.lockAcquired = false;
                    preloader.hide();
                    // ignore result
                    leave();
                });
            } else {
                leave();
            }
        } else {
            leave();
        }
    },
    getIssueId: () => parseInt($("#issueForm input[name=issueId]").val()),
    getRevision: () => $("#issueForm input[name=revision]").val(),
    getSprintNum: () => $('#issueForm').data('scrumSprintNum'),
    handleEditState: function () {
        issueForm.onShow();
        if (issueForm.restoreInput()) {
            // Отправка формы снимает блокировку задачи: после ошибки сохранения
            // забираем её обратно, пока пользователь продолжает редактирование.
            if (!issueForm.lockAcquired) {
                issueForm.acquireLock(
                    issueForm.getIssueId(),
                    issueForm.getRevision(),
                    false,
                    () => {},
                    () => {},
                );
            }
        } else {
            const getVal = (fieldName) => $("#issueInfo input[name=" + fieldName + "]").val();
            const getArrVal = (fieldName) => {
                let val = getVal(fieldName);
                return val.length > 0 ? val.split(',') : [];
            }

            const issueId = getVal("issueId");
            const revision = getVal("revision");

            // don't acquire lock when already have lock
            if (!issueForm.lockAcquired) {
                issueForm.acquireLock(
                    issueId, 
                    revision, 
                    false, 
                    () => {},
                    () => issueForm.cancel(), 
                );
            }

            issueForm.setIssueBy({
                name: $("#issueInfo .issue-name").text(),
                hours: $("#issueInfo .issue-hours").text(),
                desc: $("#issueInfo .desc .raw-desc").text(),
                priority: getVal("priority"),
                completeDate: getVal("completeDate"),
                type: getVal("type"),
                memberIds: getArrVal("members"),
                membersSp: getArrVal("membersSp"),
                testerIds: getArrVal("testers"),
                masterIds: getArrVal("masters"),
                issueId: issueId,
                revision: revision,
                imagesInfo: issueForm.getImagesFromPage(),
                filesInfo: issueForm.getFilesFromPage(),
                isOnBoard: $("#issueInfo").data('isOnBoard') == 1,
            }, true);
        }
    },
    handleAddState: function () {
        issueForm.onShow();  
        if (!issueForm.restoreInput()) {
            issueForm.updateHeader(false);

            if (issueForm.defaultMemberId) {
                issueForm.addIssueMemberById(issueForm.defaultMemberId);
            }
        }
    },
    onShow: function () {
        window.addEventListener('beforeunload', issueForm.blockClose);
        window.addEventListener('pageshow', issueForm.onPageShow);
        // Только сама форма задачи: внутри #issueForm лежат и другие формы
        // (окно новой метки), их отправка форму задачи не затрагивает.
        $("#issueForm > form").off('submit.issueForm').on('submit.issueForm', function (e) {
            // Пока предыдущая отправка не завершилась, форма не уходит повторно:
            // иначе быстрый повторный Enter или клик создаёт дубль задачи.
            // Отключённой кнопки для этого мало: часть браузеров отправляет форму
            // по Enter, даже когда кнопка отправки отключена.
            if (issueForm.submitting) return issueForm.stopSubmit(e);

            if (!issueForm.validateIssueForm()) return issueForm.stopSubmit(e);

            issueForm.setSubmitting(true);

            // Allow navigation without unload warning on successful submit
            window.removeEventListener('beforeunload', issueForm.blockClose);
        });
    },
    /**
     * Отменяет отправку формы.
     * @param {Event} e Событие submit.
     * @return {boolean} false - чтобы вернуть из обработчика submit.
     */
    stopSubmit: function (e) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        return false;
    },
    /**
     * Переводит форму в состояние отправки и обратно: в этом состоянии она
     * не принимает новых отправок, кнопка сохранения отключена, а страница
     * закрыта индикатором загрузки.
     * @param {boolean} value Перевести форму в состояние отправки.
     */
    setSubmitting: function (value) {
        if (issueForm.submitting === value) return;

        issueForm.submitting = value;
        $("#issueForm > form .save-line button[type=submit]").prop('disabled', value);

        if (value) preloader.show();
        else preloader.hide();
    },
    /**
     * Возврат из кеша браузера (кнопка «Назад») оживляет уже отправленную форму -
     * снимаем с неё состояние отправки, иначе отправить её снова будет нельзя.
     * @param {PageTransitionEvent} e Событие pageshow.
     */
    onPageShow: function (e) {
        if (!e.persisted || !issueForm.submitting) return;

        issueForm.setSubmitting(false);
        window.addEventListener('beforeunload', issueForm.blockClose);
    },
    onHide: function () {
        $('#issueForm > div.validateError').html('').hide();
        window.removeEventListener('beforeunload', issueForm.blockClose);
    },
    blockClose: function (e) {
        e.preventDefault();
        e.returnValue = '';
    },
    restoreInput: function () {
        if (!issueForm.inputForRestore) return false;
        let input = issueForm.inputForRestore;
        let data = input.data;

        issueForm.inputForRestore = null;

        // Если блокировка задачи осталась за нами, форма должна знать об этом:
        // иначе она попробует захватить её повторно и не снимет при отмене.
        if (input.hasLock) issueForm.lockAcquired = true;

        // Режим берём из самого ввода, а не из состояния страницы: иначе
        // редактирование, восстановленное после ошибки, может превратиться
        // в создание новой задачи.
        const isEdit = data.actionType === 'editIssue' && parseInt(data.issueId) > 0;

        // TODO: обработать удаленные изображения
        issueForm.setIssueBy({
            name: data.name,
            hours: data.hours,
            desc: data.desc,
            priority: data.priority,
            completeDate: data.completeDate,
            type: data.type,
            memberIds: data.members,
            membersSp: data.membersSp,
            testerIds: data.testers,
            masterIds: data.masters,
            issueId: isEdit ? data.issueId : '',
            revision: isEdit ? data.revision : '',
            newImagesUrls: data.imgUrls,
            preparedImages: data.clipboardImg,
            preparedDraftImages: data.draftImg,
            imagesInfo: issueForm.getImagesFromPage(),
            isOnBoard: data.putToBoard,
        }, isEdit);

        return true;
    },
    setIssueBy: function (value, isEdit = false) {
        // заполняем всю информацию
        // меняем заголовок
        issueForm.updateHeader(isEdit);

        // Идентификатор задачи и тип действия выставляем в первую очередь:
        // если заполнение формы прервётся ошибкой, форма не должна остаться
        // в режиме добавления и создать новую задачу вместо редактирования.
        $("#issueForm form input[name=issueId]").val(isEdit ? value.issueId : 0);
        $("#issueForm form input[name=revision]").val(isEdit ? value.revision : '');
        $("#issueForm form input[name=actionType]").val(isEdit ? 'editIssue' : 'addIssue');

        // имя
        $("#issueForm form input[name=name]").val(value.name);
        issueFormLabels.issueNameChanged(value.name);
        $("#issueForm form input[name=removedImages]").val('');
        $("#issueForm form input[name=removedFiles]").val('');
        // часы
        $("#issueForm form input[name=hours]").val(value.hours);

        // тип
        $('form input:radio[name=type]:checked', "#issueForm").removeAttr('checked');
        $('form input:radio[name=type][value=' + value.type + ']', "#issueForm").prop('checked', true);
        // приоритет
        $("#issueForm form input[name=priority]").val(value.priority);
        issuePage.setPriorityVal(value.priority);
        // дата окончания
        lpm.datePicker.setValue($("#issueForm form input[name=completeDate]")[0], value.completeDate);
        // исполнители
        issueForm.resetUsers('issueMembers', 'addIssueMembers');
        const memberIds = value.memberIds;
        if (memberIds) {
            let membersSp = value.membersSp ? value.membersSp : [];
            memberIds.forEach((memberId, index) => {
                issueForm.addIssueMemberById(memberId, membersSp[index]);
            });
        }

        // Тестеры
        issueForm.resetUsers('issueTesters', 'addIssueTesters');
        const testerIds = value.testerIds;
        if (testerIds) {
            testerIds.forEach((testerId) => {
                if (testerId.length > 0) {
                    issueForm.addIssueTesterById(testerId);
                }
            });
        }

        // Мастеры
        issueForm.resetUsers('issueMasters', 'addIssueMasters');
        const masterIds = value.masterIds;
        if (masterIds) {
            masterIds.forEach((masterId) => {
                if (masterId.length > 0) {
                    issueForm.addIssueMasterById(masterId);
                }
            });
        }

        $("#issueForm form textarea[name=desc]").val(value.desc);
        issuePage.resetDescPreview($("#issueForm"));
        issuePage.updateDescCounter($("#issueForm"));

        // уже добавленные изображения
        let imgUploadLi = $("#issueForm form .images-list > li:has(input[type=file])");
        let imgs = value.imagesInfo;
        let imgsList = $('#issueForm form .images-list').empty();
        if (imgs) {
            let imgLITmpl = $('#issueFormTemplates .image-item');
            imgs.forEach((img) => {
                let imgLI = imgLITmpl.clone();
                $('a.image-link', imgLI).attr('href', img.source);
                $('img.image-preview', imgLI).attr('src', img.preview);
                $('input[name=imgId]', imgLI).val(img.imgId);
                $('a.remove-img', imgLI).on('click', issueForm.removeImage);

                imgsList.append(imgLI);
            });
        }
        imgsList.append(imgUploadLi);

        const filesList = $('#issueForm form .files-list');
        const fileUploadItems = filesList.find('.file-item-upload').detach();
        filesList.empty();

        const files = value.filesInfo || [];
        if (files.length > 0) {
            const fileTemplate = $('#issueFormTemplates .file-item');
            files.forEach((file) => {
                const fileItem = fileTemplate.clone();
                const fileName = file.name || file.origName;
                const link = $('a.issue-file-link', fileItem);
                if (file.url) {
                    link.attr('href', file.url).attr('download', fileName);
                } else {
                    link.removeAttr('href');
                }
                $('span.file-name', fileItem).text(fileName);
                $('input.issue-file-id-input', fileItem).val(file.fileId);

                const sizeEl = $('.issue-file-size', fileItem);
                sizeEl.text(file.sizeFormatted ? '(' + file.sizeFormatted + ')' : '');

                filesList.append(fileItem);
            });
        }

        if (fileUploadItems.length > 0) {
            const uploadItem = $(fileUploadItems[0]);
            $('input[type=file]', uploadItem).val('');
            uploadItem.show();
            filesList.append(uploadItem);
        } else {
            issueForm.addFileUploadInput(filesList);
        }

        issueForm.ensureFileUploadSlot(filesList);

        // новые добавленные изображения
        let newImgs = value.newImagesUrls;
        if (newImgs) {
            newImgs.forEach((imgUrl) => {
                if (imgUrl) {
                    issueForm.addImageByUrl(imgUrl);
                }
            });
        }

        // Изображения, приложенные до сохранения задачи: форма получает их
        // обратно, когда восстанавливается после ошибки сохранения.
        const addPreparedImages = (images, fromDraft) => (images || []).forEach((dataUri) => {
            if (!dataUri) return;

            issueForm.addPreparedImage(dataUri, fromDraft);
        });
        addPreparedImages(value.preparedImages, false);
        addPreparedImages(value.preparedDraftImages, true);

        issueForm.refreshImageSlots();

        $("#issueForm form input[name=baseIds]").val(value.baseIds?.join(',') ?? '');
        $("#issueForm form input[name=linkedIds]").val(value.linkedIds?.join(',') ?? '');
        // меняем заголовок кнопки сохранения
        $("#issueForm form .save-line button[type=submit]").text("Сохранить");

        // выставляем галочку "Поместить на Scrum доску"
        var boardField = $("#putToBoardField");
        if (boardField && boardField[0])
            boardField[0].checked = value.isOnBoard;

        issueFormLabels.update();
    },
    handleAddIssueByState: function (issueId, copyLinked) {
        if (issueForm.restoreInput()) return;

        issueId = parseInt(issueId);
        const projectId = parseInt($('#issueProjectID').val());

        if (issueId <= 0 || projectId <= 0)
            return;

        // показываем прелоадер
        preloader.show();

        // Пробуем загрузить данные задачи
        srv.issue.load(
            issueId,
            copyLinked,
            function (res) {
                // скрываем прелоадер
                preloader.hide();

                if (res.success) {
                    const issue = new Issue(res.issue);
                    issueForm.setIssueBy({
                        name: issue.name,
                        hours: issue.hours,
                        desc: issue.desc,
                        priority: issue.priority,
                        completeDate: issue.getCompleteDateInput(),
                        type: issue.type,
                        memberIds: issue.getMemberIds(),
                        membersSp: issue.getMembersSp(),
                        testerIds: issue.getTesterIds(),
                        masterIds: issue.getMasterIds(),
                        newImagesUrls: issue.getImagesUrl(),
                        filesInfo: [],
                        isOnBoard: issue.isOnBoard,
                        baseIds: issue.getLinkedBaseIds(),
                        linkedIds: issue.getLinkedChildrenIds(),
                    });

                } else {
                    srv.err(res);
                }
            }
        );
    },
    handleAddFinishedIssueByState: function (issueId, kind) {
        if (issueForm.restoreInput()) return;

        issueId = parseInt(issueId);
        const projectId = parseInt($('#issueProjectID').val());

        if (issueId <= 0 || projectId <= 0)
            return;

        // показываем прелоадер
        preloader.show();

        // Пробуем загрузить данные задачи
        srv.issue.load(
            issueId,
            false,
            function (res) {
                // скрываем прелоадер
                preloader.hide();

                // Если создаётся задача по доделкам
                if (res.success) {
                    const issue = new Issue(res.issue);

                    let name = issue.name;
                    let desc = issue.desc;

                    switch (kind) {
                        case 'related-copy':
                            // Полная копия текста/заголовка + связь
                            break;
                        case 'apply':
                            desc = `Сделана в рамках другой [задачи](${issue.url}). \n\nНужно реализовать в проекте.`;
                            break;
                        case 'finished':
                        default:
                            name = Issue.getCompletionName(issue.name);
                            break;
                    }

                    const data = {
                        name: name,
                        hours: issue.hours,
                        desc: desc,
                        priority: issue.priority,
                        completeDate: issue.getCompleteDateInput(),
                        type: issue.type,
                        // надо сбросить SP по исполнителям,
                        // поэтому не передаем их
                        memberIds: issue.getMemberIds(),
                        testerIds: issue.getTesterIds(),
                        masterIds: issue.getMasterIds(),
                        newImagesUrls: issue.getImagesUrl(),
                        filesInfo: [],
                        isOnBoard: issue.isOnBoard,
                        baseIds: [issue.id],
                    };

                    issueForm.setIssueBy(data);
                } else {
                    srv.err(res);
                }
            }
        );
    },
    updateHeader: function (isEdit) {
        $("#issueForm > h3").text(isEdit ? "Редактирование задачи" : "Добавить задачу");
        // Черновик перезаписывает название, тип и описание целиком, поэтому
        // предлагается только при создании задачи.
        $('#issueForm .ai-issue-draft-row').toggleClass('d-none', isEdit);
    },

    // --- Черновик задачи от ИИ ---

    // Приложенные изображения текущего диалога: [{ name, dataUri, size }].
    // Черновик нигде не хранится — список живёт только пока окно открыто.
    draftImages: [],
    showDraftDialog: function () {
        const tpl = document.getElementById('aiIssueDraftContent');
        if (!tpl) return;

        issueForm.draftImages = [];

        lpm.dialog.show({
            title: 'Черновик задачи',
            content: tpl.innerHTML,
            primaryBtn: 'Собрать черновик',
            secondaryBtn: 'Отмена',
            onPrimary: function () {
                issueForm.generateDraft();
                // Окно закрывается только после успешной сборки черновика.
                return false;
            },
        });

        // lpm.dialog не умеет задавать размер окна, а в узком не помещаются
        // ни описание, ни превью скриншотов.
        issueForm.draftDialog().addClass('modal-lg');

        const $form = $('#issueForm form');
        const filled = !!$form.find('input[name=name]').val().trim()
            || !!$form.find('textarea[name=desc]').val().trim();
        if (filled) {
            issueForm.draft$('.ai-issue-draft-overwrite').show();
        }

        issueForm.draft$('#aiIssueDraftText').focus();
    },
    // Окно открытого диалога черновика.
    //
    // Скрытый шаблон #aiIssueDraftContent остаётся в DOM с теми же id, поэтому
    // глобальные селекторы попали бы в него; отбираем копию по .modal-dialog,
    // предка которого у шаблона нет. Опираться на .modal.show нельзя: этот
    // класс Bootstrap вешает после анимации подложки, то есть уже после того,
    // как lpm.dialog.show() вернул управление.
    draftDialog: function () {
        return $('.ai-issue-draft-body').closest('.modal-dialog');
    },
    // Ищем элементы только внутри открытого диалога — см. draftDialog().
    draft$: function (sel) {
        return issueForm.draftDialog().find(sel);
    },
    draftLimits: function () {
        const tpl = $('#aiIssueDraftContent');
        return {
            maxImages: parseInt(tpl.data('maxImages'), 10),
            maxTotalSizeMb: parseInt(tpl.data('maxTotalSizeMb'), 10),
        };
    },
    addDraftImages: function (files) {
        const limits = issueForm.draftLimits();

        // Набор картинок меняется — прежняя жалоба на него уже не актуальна.
        issueForm.clearDraftError();

        Array.prototype.forEach.call(files || [], function (file) {
            if (!file.type || file.type.indexOf('image/') !== 0) {
                issueForm.showDraftError('«' + (file.name || 'Файл') + '» — не изображение');
                return;
            }

            if (issueForm.draftImages.length >= limits.maxImages) {
                issueForm.showDraftError('Можно приложить не больше '
                    + limits.maxImages + ' изображений');
                return;
            }

            const totalSize = issueForm.draftImages.reduce(function (sum, img) {
                return sum + img.size;
            }, file.size);
            if (totalSize > limits.maxTotalSizeMb * 1024 * 1024) {
                issueForm.showDraftError('Суммарный размер изображений не должен превышать '
                    + limits.maxTotalSizeMb + ' Мб');
                return;
            }

            // Место в списке занимаем сразу, до асинхронного чтения файла:
            // иначе лимит не удержать, когда файлы выбраны разом.
            const image = { name: file.name || 'Скриншот', size: file.size, dataUri: null };
            issueForm.draftImages.push(image);

            const reader = new FileReader();
            reader.onload = function (e) {
                image.dataUri = e.target.result;
                issueForm.renderDraftImages();
            };
            reader.onerror = function () {
                issueForm.removeDraftImage(image);
                issueForm.showDraftError('Не удалось прочитать «' + image.name + '»');
            };
            reader.readAsDataURL(file);
        });
    },
    removeDraftImage: function (image) {
        const index = issueForm.draftImages.indexOf(image);
        if (index === -1) return;

        issueForm.draftImages.splice(index, 1);
        issueForm.clearDraftError();
        issueForm.renderDraftImages();
    },
    renderDraftImages: function () {
        const $list = issueForm.draft$('.ai-issue-draft-previews').empty();

        issueForm.draftImages.forEach(function (image) {
            if (!image.dataUri) return;

            const $img = $('<img class="border rounded" alt="">')
                .attr('src', image.dataUri)
                .attr('title', image.name)
                .css({ height: '72px', width: 'auto' });
            const $remove = $('<a href="javascript:void(0)" aria-label="Убрать изображение">')
                .addClass('remove-btn remove-draft-image align-top')
                .data('image', image);

            $('<li class="d-flex align-items-start">').append($img, $remove).appendTo($list);
        });
    },
    generateDraft: function () {
        const $btn = issueForm.draft$('.modal-footer .btn-primary');
        if ($btn.prop('disabled')) return;

        const text = issueForm.draft$('#aiIssueDraftText').val().trim();

        // Файл читается асинхронно, и до конца чтения у картинки нет dataUri —
        // а превью появляется только вместе с ним. Отправить сейчас значило бы
        // молча потерять уже приложенный скриншот, поэтому ждём чтения.
        if (issueForm.draftImages.some(function (image) { return !image.dataUri; })) {
            issueForm.showDraftError('Изображения ещё читаются — повторите через мгновение');
            return;
        }

        const images = issueForm.draftImages.map(function (image) { return image.dataUri; });

        if (!text && !images.length) {
            issueForm.showDraftError('Опишите задачу или приложите изображение');
            return;
        }

        issueForm.clearDraftError();
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>'
            + 'Собираем черновик…'
        );

        // Запоминаем текущее окно: ответа модели ждём секунды, и за это время
        // диалог могут закрыть или открыть заново. lpm.dialog удаляет копию
        // окна из DOM при закрытии, так что отцепленный элемент означает,
        // что этот запрос уже никому не нужен.
        const $dialog = issueForm.draftDialog();

        srv.ai.issueDraft($('#issueProjectID').val(), text, images, function (res) {
            // От черновика отказались, пока он собирался, — молча подменять
            // название, тип и описание в форме уже нельзя.
            if (!document.body.contains($dialog[0])) return;

            if (!res.success) {
                $btn.prop('disabled', false).text('Собрать черновик');
                issueForm.showDraftError(res.error || 'Не удалось собрать черновик');
                return;
            }

            const attached = issueForm.applyDraft(res, images);
            issueForm.closeDraftDialog();

            let message = 'Черновик собран — проверьте и поправьте поля';
            if (attached.skipped) {
                message = 'Черновик собран, но изображение в неподдерживаемом формате'
                    + ' приложить к задаче нельзя';
            } else if (attached.count) {
                message = 'Черновик собран, изображения приложены к задаче —'
                    + ' проверьте и поправьте поля';
            }
            lpm.toast.show(message);
        });
    },
    /**
     * Заполняет форму черновиком и прикладывает к задаче изображения,
     * по которым он собран.
     * @param {Object} draft Черновик: название, тип и описание.
     * @param {string[]} images Изображения диалога строками data URI.
     * @return {{count: number, skipped: number}} Сколько изображений приложено
     * к задаче и сколько пропущено из-за неподходящего формата.
     */
    applyDraft: function (draft, images) {
        const $form = $('#issueForm form');

        $form.find('input[name=name]').val(draft.name);
        issueFormLabels.issueNameChanged(draft.name);

        $form.find('input:radio[name=type][value=' + draft.type + ']').prop('checked', true);

        // Событие input обновляет счётчики символов и слов под полем описания.
        $form.find('textarea[name=desc]').val(draft.desc).trigger('input');

        return issueForm.attachDraftImages(images || []);
    },
    /**
     * Прикладывает к задаче изображения, по которым собран черновик.
     *
     * Приложенные прошлой сборкой изображения при этом снимаются: иначе
     * вложения копились бы от каждой попытки. Всё, что пользователь приложил
     * сам, остаётся на месте.
     * @param {string[]} images Изображения строками data URI.
     * @return {{count: number, skipped: number}} Сколько изображений приложено
     * и сколько пропущено из-за формата, который задача не принимает.
     */
    attachDraftImages: function (images) {
        $('#issueForm .images-list .draft-img').remove();

        const attachableTypes = issueForm.draftAttachableTypes();
        const result = { count: 0, skipped: 0 };

        images.forEach(function (dataUri) {
            const matches = String(dataUri).match(/^data:([^;,]*)/);
            const type = matches ? matches[1].toLowerCase() : '';

            if (attachableTypes.indexOf(type) === -1) {
                result.skipped++;
                return;
            }

            issueForm.addPreparedImage(dataUri, true);
            result.count++;
        });

        issueForm.refreshImageSlots();

        return result;
    },
    // Типы изображений, которые можно приложить к задаче: модель принимает
    // и те форматы, которые вложением задачи стать не могут.
    draftAttachableTypes: function () {
        const types = $('#aiIssueDraftContent').data('attachableTypes');
        return types ? String(types).split(',') : [];
    },
    closeDraftDialog: function () {
        // Ищем окно через draftDialog(), а не по .modal.show: этот класс
        // Bootstrap проставляет с задержкой, и сразу после открытия диалога
        // закрыть его по нему не получится.
        const el = issueForm.draftDialog().closest('.modal')[0];
        if (el) bootstrap.Modal.getOrCreateInstance(el).hide();
    },
    showDraftError: function (msg) {
        issueForm.draft$('.ai-issue-draft-error').text(msg).show();
    },
    clearDraftError: function () {
        issueForm.draft$('.ai-issue-draft-error').hide().text('');
    },
    addSprintNumToName: function () {
        $nameInput = $("#issueForm form input[name=name]");
        var name = $nameInput.val();

        const sprintNum = issueForm.getSprintNum();
        const sprintStr = ' #' + sprintNum;

        const matches = name.match(/ #\d+/ig);

        if (matches) {
            const current = matches[0];
            name = name.replace(current, current == sprintStr ? '' : sprintStr);
        } else {
            name = name + sprintStr;
        }

        $nameInput.val(name);
        issueFormLabels.update();
    },
    /**
     * Добавляет в форму изображение, которое будет загружено вместе с задачей:
     * превью и скрытое поле с данными (см. ProjectPage::prepareImages()).
     *
     * Изображения черновика отправляются отдельным полем: по нему форма
     * узнаёт их после восстановления, чтобы пересборка черновика заменяла
     * прежний набор, а не добавляла к нему новый.
     * @param {string} dataUri Изображение строкой data URI.
     * @param {boolean} [fromDraft] Изображение приложено черновиком.
     */
    addPreparedImage: function (dataUri, fromDraft) {
        if (!dataUri) return;

        const img = new Image(150, 100);
        img.src = dataUri;

        const $li = $('<li class="pasted-img">')
            .toggleClass('draft-img', !!fromDraft)
            .append($('<a>').append(img))
            .append('<a class="remove-btn remove-img" href="javascript:void(0)" aria-label="Убрать изображение">')
            .append($('<input type="hidden">')
                .attr('name', fromDraft ? 'draftImg[]' : 'clipboardImg[]')
                .val(dataUri));

        const $uploadLi = $('#issueForm .images-list input[type=file]').last().closest('li');
        if ($uploadLi.length) {
            $uploadLi.before($li);
        } else {
            $('#issueForm .images-list').append($li);
        }

        issueForm.refreshImageSlots();
    },
    /**
     * Показывает или прячет поля добавления изображений: когда к задаче уже
     * приложено предельное число картинок, добавлять больше некуда.
     *
     * Зовётся из всех путей, меняющих набор изображений формы, — и при
     * добавлении, и при снятии, поэтому поля возвращаются, как только картинок
     * снова стало меньше предела.
     */
    refreshImageSlots: function () {
        const max = window.lpmOptions.issueImgsCount;

        // Картинки, уже приложенные к форме. Выбранные в поле загрузки файлы
        // сюда не входят: их число проверяется при отправке (validateIssueForm).
        const count = $('#issueForm .images-list .image-item').length
            + $('#issueForm .images-list .pasted-img').length
            + $('#issueForm ul.images-url > li').not('.imgUrlTempl').length;
        const hasFreeSlot = !max || count < max;

        $('#issueForm .images-list > li:has(input[type=file])').each(function () {
            const input = $('input[type=file]', this)[0];
            const hasFiles = input && input.files && input.files.length > 0;

            // Поле с уже выбранными файлами не прячем: вместе с ним пропали бы
            // и сам выбор, и кнопка его снять.
            $(this).toggle(hasFreeSlot || !!hasFiles);
        });

        // Ссылка добавления по URL лежит рядом со списком, а не внутри него.
        $('#issueForm a[name=imgByUrl]').toggle(hasFreeSlot);
    },
    addImageByUrl: function (imageUrl, autofocus = false) {
        const urlLI = $("#issueForm ul.images-url > li.imgUrlTempl").clone().show();
        const imgInput = $("#issueForm ul.images-url");
        urlLI.removeClass('imgUrlTempl');
        if (imageUrl) {
            $('input[name="imgUrls[]"]', urlLI).val(imageUrl);
        }
        imgInput.append(urlLI);
        urlLI.find("a").on('click',  (event) => {
            urlLI.remove();
            issueForm.refreshImageSlots();
        });

        issueForm.refreshImageSlots();

        if (autofocus) urlLI.find('input').trigger('focus');
    },
    resetUsers: function (listId, selectId) {
        $('#' + selectId + ' option').not('option[value=-1]').remove();
        issueForm.members.forEach((member) => {
            $('#' + selectId).append(
                '<option value="' + member.userId + '">' + member.name + '</option>');
        })
        $('#' + listId + ' li').remove();
    },
    addIssueMemberCommon: function (fieldName, onRemoveClick, processItem) {
        const fieldNameFirstUpper = fieldName.charAt(0).toUpperCase() + fieldName.slice(1);

        /**
         * @type HTMLSelectElement
         */
        const selectElement = document.getElementById('addIssue' + fieldNameFirstUpper);
        const index = selectElement.selectedIndex;
        if (index == 0) return;

        const option = selectElement.options[index];
        const userId = option.value;

        const $item = $('#issueFormTemplates .members-list-item').clone();
        const $list = $('#issue' + fieldNameFirstUpper);

        $('.user-name', $item).html(option.innerHTML);
        $('.user-id-input', $item)
            .attr('name', fieldName + '[]')
            .val(userId);
        $('.remove-btn', $item).on('click', onRemoveClick);

        if (processItem) processItem($item);

        $list.append($item);

        selectElement.removeChild(option);
        selectElement.selectedIndex = 0;

        const isMe = userId == lpInfo.userId;
        if (isMe) $('#issueForm .' + fieldName + '-row .add-me-link').hide();
    },
    addMeAsMember: () => issueForm.addIssueMemberById(lpInfo.userId),
    addIssueMemberById: function (userId, sp) {
        $("#addIssueMembers option[value=" + userId + "]").prop('selected', true);
        issueForm.addIssueMember(sp);
    },
    addIssueMember: function (sp) {
        issueForm.addIssueMemberCommon('members', issueForm.removeIssueMember, ($item) => {
            const scrum = $('#issueForm').data('projectScrum') == 1;
            if (scrum) {
                $item.removeClass('hide-sp');
                $('.member-sp', $item).attr('name', 'membersSp[]');

                const spInt = parseInt(sp);
                if (Number.isInteger(spInt) && spInt > 0 || sp === "0.5") $('.member-sp', $item).val(sp);
            }
        });
    },
    addMeAsTester: () => issueForm.addIssueTesterById(lpInfo.userId),
    addIssueTesterById: function (userId) {
        $("#addIssueTesters option[value=" + userId + "]").prop('selected', true);
        issueForm.addIssueTester();
    },
    addIssueTester: () => issueForm.addIssueMemberCommon('testers', issueForm.removeIssueTester),
    addMeAsMaster: () => issueForm.addIssueMasterById(lpInfo.userId),
    addIssueMasterById: function (userId) {
        $("#addIssueMasters option[value=" + userId + "]").prop('selected', true);
        issueForm.addIssueMaster();
    },
    addIssueMaster: () => issueForm.addIssueMemberCommon('masters', issueForm.removeIssueMaster),
    removeIssueMember: (e) => issueForm.removeIssueMemberCommon(e, 'members'),
    removeIssueTester: (e) => issueForm.removeIssueMemberCommon(e, 'testers'),
    removeIssueMaster: (e) => issueForm.removeIssueMemberCommon(e, 'masters'),
    removeIssueMemberCommon: function (e, fieldName) {
        const fieldNameFirstUpper = fieldName.charAt(0).toUpperCase() + fieldName.slice(1);
        const selectName = 'addIssue' + fieldNameFirstUpper;

        const li = $(e.currentTarget).parents('.members-list-item');
        if (li.length == 0) return;

        const userId = $('input[name="' + fieldName + '[]"]', li).val();
        var userName = $('span.user-name', li).html();

        var option = document.createElement('option');
        option.value = userId;
        option.innerHTML = userName;

        var selectElement = document.getElementById(selectName);
        for (var i = 1; i < selectElement.options.length; i++) {
            if (userName < selectElement.options[i].innerHTML) break;
        }
        selectElement.appendChild(option, i);

        const isMe = userId == lpInfo.userId;
        if (isMe) $('#issueForm .' + fieldName + '-row .add-me-link').show();

        setTimeout(function () {
            li.remove();
        }, 0)
    },
    removeImage: function (e) {
        var li = $(e.currentTarget).parent('.image-item');
        var imageId = $('input[name=imgId]', li).val();

        lpm.dialog.confirm({
            text: 'Вы действительно хотите удалить это изображение?',
            yesLabel: 'Удалить',
            onYes: function () {
                li.remove();
                var val = $('#issueForm form input[name=removedImages]').val();
                if (val != '') val += ',';
                val += imageId;
                $('#issueForm form input[name=removedImages]').val(val);
                issueForm.refreshImageSlots();
            }
        });
    },
    removeFile: function (e) {
        const li = $(e.currentTarget).closest('.file-item');
        const fileId = $('.issue-file-id-input', li).val();
        if (!fileId) return;

        lpm.dialog.confirm({
            text: 'Вы действительно хотите удалить этот файл?',
            yesLabel: 'Удалить',
            onYes: function () {
                li.remove();
                let val = $('#issueForm form input[name=removedFiles]').val();
                if (val !== '') val += ',';
                val += fileId;
                $('#issueForm form input[name=removedFiles]').val(val);
                $('#issueForm .files-list .file-item-upload').show();
                issueForm.ensureFileUploadSlot();
            }
        });
    },
    initFileUploadTemplate: function () {
        if (issueForm.fileUploadTemplate) return;

        const template = $('#issueForm .files-list .file-item-upload').first();
        if (template.length) {
            issueForm.fileUploadTemplate = template.clone();
            $('input[type=file]', issueForm.fileUploadTemplate).val('');
        }
    },
    getFileUploadTemplate: function () {
        issueForm.initFileUploadTemplate();
        return issueForm.fileUploadTemplate.clone();
    },
    addFileUploadInput: function (filesList) {
        if (!filesList || filesList.length === 0) return;

        const newItem = issueForm.getFileUploadTemplate();
        if (filesList.children('.file-item-upload').length > 0) {
            $('input[type=file]#issueFilesField', newItem).removeAttr('id');
        }
        $('input[type=file]', newItem).val('');
        filesList.append(newItem);
    },
    onFileUploadInputChange: function () {
        issueForm.ensureFileUploadSlot();
    },
    refreshUploadRemoveButtons: function () {
        // Files list
        $('#issueForm .files-list input[type=file]').each(function () {
            issueForm.toggleRemoveUploadBtn(this);
        });
        // Images list
        $('#issueForm .images-list input[type=file]').each(function () {
            issueForm.toggleRemoveUploadBtn(this);
        });
    },
    ensureFileUploadSlot: function (filesList) {
        const list = filesList && filesList.length ? filesList : $('#issueForm .files-list');
        if (!list.length) return;

        const maxFiles = window.lpmOptions && window.lpmOptions.issueFilesCount
            ? window.lpmOptions.issueFilesCount
            : 0;
        const existingFilesCount = list.children('.file-item').not('.file-item-upload').length;

        const uploadItems = list.children('.file-item-upload');
        let newFilesCount = 0;
        const emptyItems = [];

        uploadItems.each(function () {
            const input = $('input[type=file]', this)[0];
            if (!input) return;

            const filesLength = input.files ? input.files.length : 0;
            if (filesLength > 0) {
                newFilesCount += filesLength;
            } else {
                emptyItems.push(this);
            }
        });

        if (maxFiles && existingFilesCount + newFilesCount >= maxFiles) {
            $(emptyItems).remove();
            return;
        }

        if (emptyItems.length === 0) {
            issueForm.addFileUploadInput(list);
        } else if (emptyItems.length > 1) {
            $(emptyItems.slice(1)).remove();
        }

        list.children('.file-item-upload').each(function () {
            const input = $('input[type=file]', this)[0];
            if (input) issueForm.toggleRemoveUploadBtn(input);
        });
    },
    toggleRemoveUploadBtn: function (inputEl) {
        if (!inputEl) return;
        var $li = $(inputEl).closest('li');
        var $btn = $('.remove-upload-input', $li);
        var hasFiles = inputEl.files && inputEl.files.length > 0;
        $btn.toggleClass('d-none', !hasFiles);
    },
    removeSelectedUploadInput: function (btnEl) {
        const $li = $(btnEl).closest('li');
        const $ul = $li.closest('ul');

        if ($('input[type=file]', $ul).length > 1) {
            $(btnEl).closest('li').remove();
            issueForm.ensureFileUploadSlot();
        } else {
            const input = $('input[type=file]', $li)[0];
            input.value = '';
            issueForm.toggleRemoveUploadBtn(input);
        }
    },
    validateIssueForm: function () {
        var errors = [];

        // Разбор тегов должен совпадать с серверным (Issue::LABELS_PATTERN),
        // иначе форма пропустит название, которое сервер не примет.
        const name = $.trim($("#issueForm form input[name=name]").val());
        const labelsStr = (name.match(/^(?:\[[^\]]*\]\s*)+/) || [''])[0];
        const labels = (labelsStr.match(/\[[^\]]*\]/g) || [])
            .map((label) => $.trim(label.slice(1, -1)))
            .filter((label) => label !== '');

        if ($.trim(name.substr(labelsStr.length)) === '') {
            errors.push('У задачи должен быть заголовок, а не только теги');
        }

        if ($('#issueForm').data('requireLabels') && labels.length === 0) {
            errors.push('У задачи должен быть указан хотя бы один тег');
        }

        const imageInputs = $("#issueForm input[name='images[]']");
        let newImagesCount = 0;
        imageInputs.each(function () {
            const files = this.files;
            if (files) newImagesCount += files.length;
        });

        const existingImagesCount = $("#issueForm .images-list .image-item").length;
        // Изображения, приложенные до сохранения (вставка из буфера, черновик),
        // занимают место наравне с выбранными в поле загрузки.
        const preparedImagesCount = $("#issueForm .images-list .pasted-img").length;
        if (newImagesCount + existingImagesCount + preparedImagesCount
                > window.lpmOptions.issueImgsCount) {
            errors.push('Вы не можете прикрепить больше ' + window.lpmOptions.issueImgsCount + ' изображений');
        }

        const attachmentInputs = $("#issueForm input[name='issueFiles[]']");
        let newFilesCount = 0;
        let totalFilesSize = 0;
        const maxTotalSize = window.lpmOptions.attachmentsTotalSizeMb;

        attachmentInputs.each(function () {
            if (!this.files) return;

            if (this.files.length > 0) {
                newFilesCount += this.files.length;
            }

            for (let i = 0; i < this.files.length; i++) {
                totalFilesSize += this.files[i].size;
            }
        });

        const existingFilesCount = $("#issueForm .files-list .file-item").not('.file-item-upload').length;
        if (existingFilesCount + newFilesCount > window.lpmOptions.issueFilesCount) {
            errors.push('Вы не можете прикрепить больше ' + window.lpmOptions.issueFilesCount + ' файлов');
        }

        if (maxTotalSize && totalFilesSize > maxTotalSize * 1024 * 1024) {
            errors.push('Суммарный размер файлов не должен превышать ' + maxTotalSize + ' Мб (сейчас ' + lpm.format.sizeMb(totalFilesSize) + ')');
        }

        if (errors.length == 0) {
            $('#issueForm > div.validateError').hide();
            return true;
        } else {
            const $error = $('#issueForm > div.validateError');
            $error.html(errors.join('<br/>')).show();
            // Ошибки выводятся вверху формы, а кнопка сохранения - внизу:
            // без прокрутки нажатие выглядит как отсутствие реакции.
            $error[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
    },
    getImagesFromPage: function () {
        let imgs = $("#issueInfo div > .images-line > li");
        return imgs.toArray().map((img) => {
            return {
                imgId: $('input[name=imgId]', img).val(),
                source: $('a.image-link', img).attr('href'),
                preview: $('img.image-preview', img).attr('src'),
            };
        });
    },
    getFilesFromPage: function () {
        let files = $("#issueInfo .issue-files-list .issue-file-item");
        return files.toArray().map((file) => {
            const sizeText = $('.issue-file-size', file).text().trim();
            return {
                fileId: $('.issue-file-id-input', file).val(),
                name: $('span.file-name', file).text(),
                url: $('a.issue-file-link', file).attr('href'),
                sizeFormatted: sizeText ? sizeText.replace(/[()]/g, '').trim() : '',
            };
        });
    },
};

let issueFormLabels = {
    openAdd: function () {
        $("#addIssueLabelForm")[0].reset();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addIssueLabelFormContainer')).show();
    },
    saveNew: function () {
        var label = $("#issueLabelText").val();
        var checked = $("#isAllProjectsCheckbox").is(':checked');
        var projectId = $("#issueProjectID").val();
        if (label.length > 0) {
            preloader.show();
            srv.issue.addLabel(label, checked, projectId, function (res) {
                preloader.hide();
                if (res.success) {
                    issueFormLabels.clear(label);
                    issueFormLabels.create(label, (checked ? 0 : projectId), res.id);
                    issueFormLabels.addToName(label);
                } else {
                    srv.err(res);
                }
            });
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addIssueLabelFormContainer')).hide();
    },
    openRemove: function () {
        issueFormLabels.updateEmptyState();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('removeIssuesLabelContainer')).show();
    },
    updateEmptyState: function () {
        var $rows = $("#removeIssuesLabelContainer tbody tr").not(".remove-issue-labels-empty");
        $("#removeIssuesLabelContainer .remove-issue-labels-empty").toggleClass('d-none', $rows.length > 0);
    },
    confirmRemove: function (name, id) {
        // Нативный confirm вместо модалки: Bootstrap 5.1.3 не поддерживает вложенные
        // модальные окна, а диалог удаления меток сам является модальным окном.
        var text = (id === undefined)
            ? 'Убрать метку «' + name + '» из этой задачи?'
            : 'Удалить метку «' + name + '»?\nОна будет удалена из списка меток проекта.';
        if (confirm(text)) {
            issueFormLabels.remove(name, id);
        }
    },
    remove: function (name, id) {
        if (typeof issueLabels === 'undefined')
            issueLabels = [];

        var success = false;


        if (id == undefined) {
            issueFormLabels.clear(name);
        } else {
            preloader.show();
            srv.issue.removeLabel(id, $("#issueProjectID").val(), function (res) {
                preloader.hide();
                if (res.success) {
                    issueFormLabels.clear(name);
                } else {
                    srv.err(res);
                }
            });
        }
    },
    create: function (label, id, projectId) {
        $(".add-issue-label").before(
            "<a href=\"javascript:void(0)\" class=\"issue-label\" onclick=\"issueFormLabels.addToName('"
            + label + "');\">" + label + "</a>");

        $("#removeIssuesLabelContainer tbody").append("<tr>" +
            "<td class=\"label-name\">" + label + "</td>" +
            "<td class=\"text-center\">0</td>" +
            "<td class=\"text-center\">0</td>" +
            "<td class=\"text-center\">" + (projectId == 0 ? "<i class=\"fas fa-check text-success\" aria-hidden=\"true\" title=\"Общая метка\"></i>" : "") + "</td>" +
            "<td class=\"text-end\">" +
            "<button type=\"button\" class=\"btn btn-sm btn-outline-danger\" title=\"Удалить метку\" onclick=\"issueFormLabels.confirmRemove('" + label + (id != 0 ? "', " + id : "") + ");\">" +
            "<i class=\"far fa-trash-can\" aria-hidden=\"true\"></i>" +
            "</button>" +
            "</td>" +
            "</tr>");
        issueFormLabels.updateEmptyState();
    },
    clear: function (labelName) {
        if (typeof issueLabels === 'undefined')
            issueLabels = [];
        if (issueLabels.indexOf(labelName) != -1)
            issueFormLabels.addToName(labelName);

        $("#removeIssuesLabelContainer tbody tr").each(function () {
            var item = $.trim($(this).find(".label-name").text());
            if (item == labelName) {
                $(this).remove();
            }
        });

        $(".issue-labels-container a.issue-label").each(function () {
            var item = $(this).text();
            if (item == labelName) {
                $(this).remove();
            }
        });

        issueFormLabels.updateEmptyState();
        // Удалённая метка могла быть среди скрытых — пересчитываем «ещё N».
        issueFormLabels.updateCollapsedCount();
    },
    addToName: function (labelName) {
        if (typeof issueLabels === 'undefined')
            issueLabels = [];
        var index = issueLabels.indexOf(labelName);
        var isAddingLabel = index == -1;
        var strPos = 0;
        var resultLabels = "";
        for (var i = 0, len = issueLabels.length; i < len; ++i) {
            var str = issueLabels[i];
            strPos += str.length + 2;
            if (index == i) { // на случай, если несколько одинаковых меток у задачи, ну мало ли кто накосячил.
                issueLabels.splice(index, 1);
                len--;
                i--;
                index = issueLabels.indexOf(labelName);
            } else {
                resultLabels += "[" + str + "]";
            }
        }

        if (isAddingLabel) {
            resultLabels += "[" + labelName + "]";
            issueLabels.push(labelName);
        }

        var name = $("#issueForm form input[name=name]").val();
        name = (resultLabels.length > 0 ? resultLabels + " " : "") + $.trim(name.substr(strPos));

        $("#issueForm form input[name=name]").val(name);
        issueFormLabels.update();
    },
    issueNameChanged: function (value) {
        if (typeof issueLabels === 'undefined')
            issueLabels = [];

        var labelsStr = $.trim(value).match(/^\[.*]/);
        // Метки, оставшиеся в начале заголовка. Пустой список, если ведущего
        // блока [..] больше нет (заголовок стёрли целиком или убрали метку руками).
        var labels = [];
        var isUpdate = false;
        if (labelsStr != null) {
            // т.к. на js нет нормальной регулярки для такой задачи, то как-то так
            var parts = labelsStr.toString().split("]");
            for (var i = 0, len = parts.length; i < len; ++i) {
                var label = $.trim(parts[i]);
                if (label.substr(0, 1) == '[') {
                    label = $.trim(label.substr(1));
                    if (label != "") {
                        labels.push(label);
                        if (issueLabels.indexOf(label) == -1) {
                            issueLabels.push(label);
                            isUpdate = true;
                        }
                    }
                } else {
                    break;
                }
            }
        }

        //Удаляем те, которые стерли
        var len = issueLabels.length;
        while (len-- > 0) {
            var label = issueLabels[len];
            if (labels.indexOf(label) == -1) {
                issueLabels.splice(len, 1);
                isUpdate = true;
            }
        }

        if (isUpdate)
            issueFormLabels.update();
    },
    toggleCollapse: function (el) {
        var $container = $(el).closest('.issue-labels-container');
        var collapsed = $container.toggleClass('labels-collapsed').hasClass('labels-collapsed');
        $(el).attr('title', collapsed ? 'Показать все метки' : 'Свернуть метки');
    },
    updateCollapsedCount: function () {
        var $toggle = $('.issue-labels-container .toggle-issue-labels');
        if (!$toggle.length) return;
        var $container = $toggle.closest('.issue-labels-container');
        // Скрытыми считаются свёрнутые метки, которые не выбраны (выбранные видны всегда).
        var count = $container.find('.issue-label-collapsed:not(.selected)').length;
        $container.find('.toggle-count').text(count);
        // Одну метку прятать нет смысла: «ещё 1» займёт столько же места. Прячем
        // ссылку и показываем список целиком, если скрытых меньше двух.
        if (count <= 1) {
            $toggle.addClass('d-none');
            $container.removeClass('labels-collapsed');
        } else {
            $toggle.removeClass('d-none');
        }
    },
    update: function () {
        if (typeof issueLabels !== 'undefined') {
            var subclass = 'selected';
            $(".issue-labels-container a.issue-label").each(function () {
                if ($(this).hasClass(subclass))
                    $(this).removeClass(subclass);

                var item = $(this).text();
                if (issueLabels.indexOf(item) != -1)
                    $(this).addClass(subclass);
            });
            issueFormLabels.updateCollapsedCount();
        }
    },
};
