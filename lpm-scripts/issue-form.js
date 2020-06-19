let issueForm = {
    setIssueBy: function (value) {
        // заполняем всю информацию
        //$( "" ).value( $( "" ) );
        // меняем заголовок
        $("#issueForm > h3").text("Добавить задачу");
        // имя
        $("#issueForm form input[name=name]").val(value.name);
        // часы
        $("#issueForm form input[name=hours]").val(value.hours);

        // тип
        $('form input:radio[name=type]:checked', "#issueForm").removeAttr('checked');
        $('form input:radio[name=type][value=' + value.type + ']', "#issueForm").prop('checked', true);
        // приоритет
        // var priorityVal = $( "#issueInfo li input[name=priority]" ).val();
        $("#issueForm form input[name=priority]").val(value.priority);
        issuePage.setPriorityVal(value.priority);
        // дата окончания
        $("#issueForm form input[name=completeDate]").val(value.completeDate);
        // исполнители
        var memberIds = value.members.split(',');
        var i, l = 0;
        l = memberIds.length;
        for (i = 0; i < l; i++) {
            $("#addIssueMembers option[value=" + memberIds[i] + "]").prop('selected', true);
            issueForm.addIssueMember();
        }

        // Тестеры
        var testerIds = value.testers/*$( "#issueInfo li input[name=testers]" ).val()*/.split(',');
        l = testerIds.length;
        for (i = 0; i < l; i++) {
            var testerId = testerIds[i];
            if (testerId.length > 0) {
                $("#addIssueTesters option[value=" + testerId + "]").prop('selected', true);
                issueForm.addIssueTester();
            }
        }

        //$( "#issueForm form" ).value( $( "" ) );
        // описание
        // пришлось убрать, потому что там уже обработанное описание - с ссылками и тп
        // вообще видимо надо переделать это все
        //$( "#issueForm form textarea[name=desc]" ).val( $( "#issueInfo li.desc .value" ).html() );
        $("#issueForm form textarea[name=desc]").val(value.desc);
        // изображения
        var imgs = value.images;
        var numImages = imgs.length;
        for (i = 0; i < numImages; ++i) {
            issueForm.addImagebyUrl(imgs[i].source);
        }
        /*var imgs = $("#issueInfo li > .images-line > li");
        l = imgs.length;
        var $imgInputField = $('#issueForm form .images-list > li').has('input[name="images[]"]');
        var $imgInput = $('#issueForm form .images-list').empty();
        var imgLI = null;
        for (i = l - 1; i >= 0; i--) {
            //$('input[name=imgId]',imgs[i]).val()
            imgLI = imgs[i].cloneNode( true );
            $(imgLI).append('<a href="javascript:;" class="remove-btn" onclick="removeImage(' +
                $('input[name=imgId]', imgLI).val() + ')"></a>');
            $imgInput.append(imgLI);
            //imgInput.insertBefore(imgLI, imgInput.children[0]);
        };
        $imgInput.append($imgInputField);
        if (l >= window.lpmOptions.issueImgsCount) {
            $("#issueForm form .images-list > li input[type=file]").hide();
            $("#issueForm form li a[name=imgbyUrl]").hide();
        }*/

        // родитель
        $("#issueForm form input[name=parentId]").val(value.parentId /*$( "#issueInfo input[name=parentId]" ).val()*/);
        // идентификатор задачи
        // $( "#issueForm form input[name=issueId]" ).val( value.issueId/*$( "#issueInfo input[name=issueId]" ).val()*/ );
        // действие меняем на редактирование
        $("#issueForm form input[name=actionType]").val('addIssue');
        $("#issueForm form input[name=baseIdInProject]").val(value.baseIdInProject);
        // меняем заголовок кнопки сохранения
        $("#issueForm form .save-line button[type=submit]").text("Сохранить");

        // выставляем галочку "Поместить на Scrum доску"
        var boardField = $("#putToBoardField");
        if (boardField && boardField[0])
            boardField[0].checked = value.isOnBoard;
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
                        members: issue.getMemberIds(),
                        testers: issue.getTesterIds(),
                        parentId: issue.parentId,
                        issueId: issue.id,
                        images: issue.images,
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
                        members: issue.getMemberIds(),
                        testers: issue.getTesterIds(),
                        parentId: issue.parentId,
                        issueId: issue.id,
                        images: issue.images,
                        isOnBoard: issue.isOnBoard,
                        baseIdInProject: issueIdInProject
                    });
                } else {
                    srv.err(res);
                }
            }
        );
    },
    onShowAddIssue: function () {
        var selectedPerformer = $('#selected-performer').val();
        if (selectedPerformer) {
            issueForm.addIssueMember();
        }
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