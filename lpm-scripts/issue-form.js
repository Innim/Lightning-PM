$(document).ready(function ($) {
    document.addEventListener('paste', pasteClipboardImage);
    $('.images-list').on('click', '.pasted-img .remove-btn', function () {
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
                        $('li#current').append("<a class='remove-btn' onclick='javascript: return false;'>");
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
});

let issueForm = {
    handleEditState: function () {
        let getVal = (fieldName) => $("#issueInfo input[name=" + fieldName + "]").val();
        let imgs = $("#issueInfo div > .images-line > li");
        issueForm.setIssueBy({
            name: $("#issueInfo > h3 > .issue-name").text(),
            hours: $("#issueInfo > h3 .issue-hours").text(),
            desc: $("#issueInfo .desc .raw-desc").text(),
            priority: getVal("priority"),
            completeDate: getVal("completeDate"),
            type: getVal("type"),
            memberIds: getVal("members").split(','),
            membersSp: getVal("membersSp").split(','),
            testerIds: getVal("testers").split(','),
            parentId: getVal("parentId"),
            issueId: getVal("issueId"),
            imagesInfo: imgs.toArray().map((img) => {
                return {
                    imgId: $('input[name=imgId]', img).val(),
                    source: $('a.image-link', img).attr('href'),
                    preview: $('img.image-preview', img).attr('src'),
                };
            }),
            isOnBoard: $("#issueInfo").data('isOnBoard') == 1,
        }, true);
    },
    handleAddState: function () {
        var selectedPerformer = $('#selected-performer').val();
        if (selectedPerformer) {
            issueForm.addIssueMember();
        }
    },
    setIssueBy: function (value, isEdit = false) {
        // заполняем всю информацию
        // меняем заголовок
        $("#issueForm > h3").text(isEdit ? "Редактирование задачи" : "Добавить задачу");
        // имя
        $("#issueForm form input[name=name]").val(value.name);
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
        let memberIds = value.memberIds;
        if (memberIds) {
            let membersSp = value.membersSp ? value.membersSp : [];
            memberIds.forEach((memberId, index) => {
                $("#addIssueMembers option[value=" + memberId + "]").prop('selected', true);
                issueForm.addIssueMember(membersSp[index]);
            });
        }

        // Тестеры
        let testerIds = value.testerIds;
        if (testerIds) {
            testerIds.forEach((testerId) => {
                if (testerId.length > 0) {
                    $("#addIssueTesters option[value=" + testerId + "]").prop('selected', true);
                    issueForm.addIssueTester();
                }
            });
        }

        $("#issueForm form textarea[name=desc]").val(value.desc);

        var imgsCouns = 0

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
                $('a.remove-btn', imgLI).click(issueForm.removeImage);

                imgsList.append(imgLI);
            });

            imgsCouns += imgs.length;
        }
        imgsList.append(imgUploadLi);

        // новые добавленные изображения
        let newImgs = value.newImagesUrls;
        if (newImgs) {
            newImgs.forEach((imgUrl) => {
                if (imgUrl) {
                    issueForm.addImagebyUrl(imgUrl);
                    imgsCouns++;
                }
            });
        }

        if (imgsCouns >= window.lpmOptions.issueImgsCount) {
            imgUploadLi.hide();
            $("#issueForm form li a[name=imgbyUrl]").hide();
        }

        // родитель
        $("#issueForm form input[name=parentId]").val(value.parentId);
        // идентификатор задачи
        if (isEdit)
            $("#issueForm form input[name=issueId]").val(value.issueId);
        // действие меняем на редактирование
        $("#issueForm form input[name=actionType]").val(isEdit ? 'editIssue' : 'addIssue');
        $("#issueForm form input[name=baseIdInProject]").val(value.baseIdInProject);
        // меняем заголовок кнопки сохранения
        $("#issueForm form .save-line button[type=submit]").text("Сохранить");

        // выставляем галочку "Поместить на Scrum доску"
        var boardField = $("#putToBoardField");
        if (boardField && boardField[0])
            boardField[0].checked = value.isOnBoard;

        issueFormLabels.update();
    },
    addIssueBy: function (issueIdInProject) {
        issueIdInProject = parseInt(issueIdInProject);
        var projectId = parseInt($('#issueProjectID').val());

        if (issueIdInProject <= 0 || projectId <= 0)
            return;

        // показываем прелоадер
        preloader.show();

        // Пробуем загрузить данные задачи
        srv.issue.loadByIdInProject(
            issueIdInProject,
            projectId,
            function (res) {
                // скрываем прелоадер
                preloader.hide();

                if (res.success) {
                    var issue = new Issue(res.issue);
                    // console.log("issue-name: " + issue.name);

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
                        parentId: issue.parentId,
                        issueId: issue.id,
                        newImagesUrls: issue.getImagesUrl(),
                        isOnBoard: issue.isOnBoard,
                        baseIdInProject: 0
                    });

                } else {
                    srv.err(res);
                }
            }
        );
    },
    finishedIssueBy: function (issueIdInProject) {
        issueIdInProject = parseInt(issueIdInProject);
        var projectId = parseInt($('#issueProjectID').val());

        if (issueIdInProject <= 0 || projectId <= 0)
            return;

        // показываем прелоадер
        preloader.show();

        // Пробуем загрузить данные задачи
        srv.issue.loadByIdInProject(
            issueIdInProject,
            projectId,
            function (res) {
                // скрываем прелоадер
                preloader.hide();

                // Если создаётся задача по доделкам
                if (res.success) {
                    const issue = new Issue(res.issue);
                    // var url = $("#projectView").data('projectUrl');

                    issueForm.setIssueBy({
                        name: Issue.getCompletionName(issue.name),
                        hours: issue.hours,
                        desc: issue.desc + "\n\n" + "Оригинальная задача: " + issue.url,
                        priority: issue.priority,
                        completeDate: issue.getCompleteDateInput(),
                        type: issue.type,
                        // надо сбросить SP по исполнителям,
                        // поэтому не передаем их
                        memberIds: issue.getMemberIds(),
                        testerIds: issue.getTesterIds(),
                        parentId: issue.parentId,
                        issueId: issue.id,
                        newImagesUrls: issue.getImagesUrl(),
                        isOnBoard: issue.isOnBoard,
                        baseIdInProject: issueIdInProject
                    });
                } else {
                    srv.err(res);
                }
            }
        );
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
    addImagebyUrl: function (imageUrl, autofocus = false) {
        // $("#issueForm li > ul.images-url > li input").removeAttr('autofocus');
        var urlLI = $("#issueForm li > ul.images-url > li.imgUrlTempl").clone().show();
        var imgInput = $("#issueForm ul.images-url");
        urlLI.removeAttr('class');
        if (imageUrl)
            urlLI[0].children[0].value = imageUrl;
        //urlLI.("input").attr('autofocus','true');
        //добавляем в контейнер
        imgInput.append(urlLI);
        // setCaretPosition(urlLI.find("input"));
        urlLI.find("a").click(function (event) {
            urlLI.remove();
        });

        if (autofocus) urlLI.find('input').focus();
    },
    addIssueMember: function (sp) {
        /**
         * @type HTMLSelectElement
         */
        var selectElement = document.getElementById('addIssueMembers');
        var index = selectElement.selectedIndex;
        if (index == 0)
            return;

        var scrum = $('#issueForm').data('projectScrum') == 1;
        var option = selectElement.options[index];
        var $memberLi = $('<li>');


        $memberLi.
            append($('<span class="user-name">').html(option.innerHTML)).
            append($('<input type="hidden" name="members[]">').val(option.value));

        // проверка что это скрам проект
        if (scrum)
            $memberLi.
                append($('<input type="text" name="membersSp[]" class="member-sp">').val(sp > 0 ? sp : "")).
                append($('<span class="member-sp-label">').html("SP"));


        $memberLi.
            append($('<a class="remove-btn">').click(issueForm.removeIssueMember));

        $('#issueMembers').append($memberLi);

        selectElement.removeChild(option);
        selectElement.selectedIndex = 0;
    },
    addIssueTester: function () {
        /**
         * @type HTMLSelectElement
         */
        var selectElement = document.getElementById('addIssueTesters');

        var option = selectElement.options[selectElement.selectedIndex];

        /**
         * @type HTMLOListElement
         */

        var testers = document.getElementById('issueTesters');

        /**
         * @type HTMLOListElement
         */
        var li = document.createElement('li');

        /**
         * @type HTMLSpanElement
         */
        var nameLabel = document.createElement('span');
        nameLabel.innerHTML = option.innerHTML;
        nameLabel.className = 'user-name';

        /**
         * @type HTMLLinkElement
         */
        var idField = document.createElement('input');
        idField.type = 'hidden';
        idField.name = 'testers[]';
        idField.value = option.value;

        /**
         * @type HTMLButtonElement
         */
        var removeBtn = document.createElement('a');
        //removeBtn.innerHTML = 'Удалить';
        removeBtn.className = 'remove-btn';
        removeBtn.onclick = issueForm.removeIssueTester;

        li.appendChild(nameLabel);
        li.appendChild(idField);
        li.appendChild(removeBtn);

        testers.appendChild(li);

        selectElement.removeChild(option);
        selectElement.selectedIndex = 0;
    },
    removeIssueMember: function (e) {
        issueForm.removeIssueMemberCommon(e, 'members', 'addIssueMembers');
    },
    removeIssueTester: function (e) {
        issueForm.removeIssueMemberCommon(e, 'testers', 'addIssueTesters');
    },
    removeIssueMemberCommon: function (e, fieldName, selectName) {
        var li = $(e.currentTarget).parent('li');

        var userId = $('input[name="' + fieldName + '[]"]', li).val();
        var userName = $('span.user-name', li).html();

        var option = document.createElement('option');
        option.value = userId;
        option.innerHTML = userName;

        var selectElement = document.getElementById(selectName);
        for (var i = 1; i < selectElement.options.length; i++) {
            if (userName < selectElement.options[i].innerHTML) break;
        }
        selectElement.appendChild(option, i);

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

        if ($('#issueForm #issueMembers input[type=hidden][name="members[]"]').size() == 0)
            errors.push('Задаче должен быть назначен хотя бы один исполнитель');

        if (errors.length == 0) {
            $('#issueForm > div.validateError').hide();
            return true;
        } else {
            $('#issueForm > div.validateError').html(errors.join('<br/>')).show();
            return false;
        }
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
            id = $(this).find(".label-name").data("labelid");
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