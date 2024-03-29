$(function ($) {
    document.addEventListener('paste', pasteClipboardImage);
    $('.images-list').on('click', '.pasted-img .remove-img', function () {
        $(this).parent('.pasted-img').remove();
    });

    function pasteClipboardImage(event) {
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
                        var img = new Image(150, 100);
                        img.src = event.target.result;
                        $('input[type=file]').last().parent().before("<li id='current'><a></a></li>");
                        $('li#current a').append(img);
                        $('li#current').append("<a class='remove-btn remove-img' onclick='javascript: return false;'>");
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'clipboardImg[]';
                        input.value = img.src;
                        $('li#current').append(input);
                        $('li#current').removeAttr("id").addClass('pasted-img');
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
});

let issueForm = {
    inputForRestore: null,
    members: null,
    defaultMemberId: null,
    testers: null,
    masters: null,
    getSprintNum: () => $('#issueForm').data('scrumSprintNum'),
    handleEditState: function () {
        if (!issueForm.restoreInput(true)) {
            let getVal = (fieldName) => $("#issueInfo input[name=" + fieldName + "]").val();
            let getArrVal = (fieldName) => {
                let val = getVal(fieldName);
                return val.length > 0 ? val.split(',') : [];
            }

            issueForm.setIssueBy({
                name: $("#issueInfo > h3 > .issue-name").text(),
                hours: $("#issueInfo > h3 .issue-hours").text(),
                desc: $("#issueInfo .desc .raw-desc").text(),
                priority: getVal("priority"),
                completeDate: getVal("completeDate"),
                type: getVal("type"),
                memberIds: getArrVal("members"),
                membersSp: getArrVal("membersSp"),
                testerIds: getArrVal("testers"),
                masterIds: getArrVal("masters"),
                issueId: getVal("issueId"),
                imagesInfo: issueForm.getImagesFromPage(),
                isOnBoard: $("#issueInfo").data('isOnBoard') == 1,
            }, true);
        }
    },
    handleAddState: function () {
        if (!issueForm.restoreInput(false)) {
            issueForm.updateHeader(false);

            if (issueForm.defaultMemberId) {
                issueForm.addIssueMemberById(issueForm.defaultMemberId);
            }
        }
    },
    restoreInput: function (isEdit) {
        if (!issueForm.inputForRestore) return false;
        let input = issueForm.inputForRestore;
        let data = input.data;

        issueForm.inputForRestore = null;

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
            newImagesUrls: data.imgUrls,
            imagesInfo: issueForm.getImagesFromPage(),
            isOnBoard: data.putToBoard,
        }, isEdit);

        return true;
    },
    setIssueBy: function (value, isEdit = false) {
        // заполняем всю информацию
        // меняем заголовок
        issueForm.updateHeader(isEdit);
        // имя
        $("#issueForm form input[name=name]").val(value.name);
        issueFormLabels.issueNameChanged(value.name);
        // часы
        $("#issueForm form input[name=hours]").val(value.hours);

        // тип
        $('form input:radio[name=type]:checked', "#issueForm").removeAttr('checked');
        $('form input:radio[name=type][value=' + value.type + ']', "#issueForm").prop('checked', true);
        // приоритет
        $("#issueForm form input[name=priority]").val(value.priority);
        issuePage.setPriorityVal(value.priority);
        // дата окончания
        $("#issueForm form input[name=completeDate]").val(value.completeDate);
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

        var imgsCount = 0

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

            imgsCount += imgs.length;
        }
        imgsList.append(imgUploadLi);

        // новые добавленные изображения
        let newImgs = value.newImagesUrls;
        if (newImgs) {
            newImgs.forEach((imgUrl) => {
                if (imgUrl) {
                    issueForm.addImageByUrl(imgUrl);
                    imgsCount++;
                }
            });
        }

        if (imgsCount >= window.lpmOptions.issueImgsCount) {
            imgUploadLi.hide();
            $("#issueForm form li a[name=imgByUrl]").hide();
        }

        // идентификатор задачи
        if (isEdit)
            $("#issueForm form input[name=issueId]").val(value.issueId);
        // действие меняем на редактирование
        $("#issueForm form input[name=actionType]").val(isEdit ? 'editIssue' : 'addIssue');
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
        if (issueForm.restoreInput(false)) return;

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
        if (issueForm.restoreInput(false)) return;

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
                        case 'apply':
                            desc = `Сделана в рамках другой [задачи](${issue.url}). 
                            
Нужно реализовать в проекте.
                            `
                            break;
                        case 'finished':
                        default:
                            name = Issue.getCompletionName(issue.name);
                    }

                    issueForm.setIssueBy({
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
                        isOnBoard: issue.isOnBoard,
                        baseIds: [issue.id],
                    });
                } else {
                    srv.err(res);
                }
            }
        );
    },
    updateHeader: function (isEdit) {
        $("#issueForm > h3").text(isEdit ? "Редактирование задачи" : "Добавить задачу");
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
    addImageByUrl: function (imageUrl, autofocus = false) {
        const urlLI = $("#issueForm ul.images-url > li.imgUrlTempl").clone().show();
        const imgInput = $("#issueForm ul.images-url");
        urlLI.removeAttr('class');
        if (imageUrl) {
            $('input[name="imgUrls[]"]', urlLI).val(imageUrl);
        }
        imgInput.append(urlLI);
        urlLI.find("a").on('click',  (event) => urlLI.remove());

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
                // TODO: удалить часть с проверкой на 0, тут должна быть NaN когда не надо показывать
                if (Number.isInteger(spInt) && spInt > 0) $('.member-sp', $item).val(sp);
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

        console.log(li);

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

        if (confirm('Вы действительно хотите удалить это изображение?')) {
            li.remove();
            var val = $('#issueForm form input[name=removedImages]').val();
            if (val != '') val += ',';
            val += imageId;
            $('#issueForm form input[name=removedImages]').val(val);
        }
    },
    validateIssueForm: function () {
        var errors = [];
        var inputs = $("#issueForm input:file");
        var len = 0;

        if (!$.isEmptyObject({ inputs })) {
            inputs.each(function (i) {
                len += inputs[i].files.length;
            });
        }

        if (len > window.lpmOptions.issueImgsCount)
            errors.push('Вы не можете прикрепить больше ' + window.lpmOptions.issueImgsCount + ' изображений');

        if (errors.length == 0) {
            $('#issueForm > div.validateError').hide();
            return true;
        } else {
            $('#issueForm > div.validateError').html(errors.join('<br/>')).show();
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
};

let issueFormLabels = {
    openAdd: function () {
        $("#addIssueLabelFormContainer").dialog({
            resizable: false,
            width: 400,
            modal: true,
            draggable: false,
            title: "Добавление новой метки",
            buttons: [{
                text: "Сохранить",
                click: function (e) {
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
                    $("#addIssueLabelForm")[0].reset();
                    $("#addIssueLabelFormContainer").dialog('close');
                }
            }, {
                text: "Отмена",
                click: function (e) {
                    $("#addIssueLabelForm")[0].reset();
                    $("#addIssueLabelFormContainer").dialog('close');
                }
            }],
            open: function () {
                $("#addIssueLabelFormContainer").keypress(function (e) {
                    if (e.keyCode == $.ui.keyCode.ENTER) {
                        $(this).parent().find("button:eq(0)").trigger("click");
                        return false;
                    }
                });
            }
        });
    },
    openRemove: function () {
        $("#removeIssuesLabelContainer").dialog({
            resizable: false,
            width: 'auto',
            modal: true,
            draggable: false,
            title: "Удаление меток"
        });
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

        $("#removeIssuesLabelContainer .table").append("<div class=\"table-row\">" +
            "<div class=\"table-cell label-name\">" + label + "</div>" +
            "<div class=\"table-cell\">0</div>" +
            "<div class=\"table-cell\">" + (projectId == 0 ? "<i class=\"far fa-check-square\" aria-hidden=\"true\"></i>" : "") + "</div>" +
            "<div class=\"table-cell\">" +
            "<a href=\"javascript:void(0)\" onclick=\"issueFormLabels.remove('" + label + (id != 0 ? "', " + id : "") + ");\">" +
            "<i class=\"far fa-minus-square\" aria-hidden=\"true\"></i>" +
            "</a>" +
            "</div>" +
            "</div>");
    },
    clear: function (labelName) {
        if (typeof issueLabels === 'undefined')
            issueLabels = [];
        if (issueLabels.indexOf(labelName) != -1)
            issueFormLabels.addToName(labelName);

        $("#removeIssuesLabelContainer .table-row").each(function () {
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
        if (labelsStr != null) {
            // т.к. на js нет нормальной регулярки для такой задачи, то как-то так
            var labels = labelsStr.toString().split("]");
            var isUpdate = false;
            var currentSymbol = 0;
            for (var i = 0, len = labels.length; i < len; ++i) {
                var label = $.trim(labels[i]);
                if (label.substr(0, 1) == '[') {
                    currentSymbol += 2;
                    label = $.trim(label.substr(1));
                    if (label == "") {
                        labels.splice(i--, 1);
                        len--;
                    } else {
                        labels[i] = label;
                        if (issueLabels.indexOf(label) == -1) {
                            issueLabels.push(label);
                            isUpdate = true;
                        }
                    }
                } else {
                    break;
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
        }
    },
};
