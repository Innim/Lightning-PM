$(document).ready(
    function () {
        $('#issueView .comments form.add-comment').hide();

        states.addState($("#issueView"));
        states.addState($("#issueForm"), 'edit', setEditInfo);

        states.updateView();

        /*$( "#issueInfo li .priority-val" ).css( 
                'backgroundColor', 
                issuePage.getPriorityColor( $( "#issueInfo li input[name=priority]" ).val() ) 
        );*/

        if ($('#issueView .comments .comments-list .comments-list-item').size() == 0)
            $('#issueView .comments .links-bar a.toggle-comments').hide();


        // TODO: заюзать метод issueForm.setIssueBy

        function setEditInfo() {
            // заполняем всю информацию
            //$( "" ).value( $( "" ) );
            // меняем заголовок
            $("#issueForm > h3").text("Редактирование задачи");
            // имя
            var issueName = $("#issueInfo > h3 > .issue-name").text();
            $("#issueForm form input[name=name]").val(issueName);
            // внешний вид меток
            issueFormLabels.update();
            // часы
            $("#issueForm form input[name=hours]").val($("#issueInfo > h3 .issue-hours").text());

            // тип
            $('form input:radio[name=type]:checked', "#issueForm").removeAttr('checked');
            $('form input:radio[name=type][value=' + $("#issueInfo div input[name=type]").val() + ']',
                "#issueForm").prop('checked', true);
            // приоритет
            var priorityVal = $("#issueInfo div input[name=priority]").val();
            $("#issueForm form input[name=priority]").val(priorityVal);
            issuePage.setPriorityVal(priorityVal);
            // дата окончания
            $("#issueForm form input[name=completeDate]").val(
                $("#issueInfo div input[name=completeDate]").val());
            // исполнители
            var memberIds = $("#issueInfo div input[name=members]").val().split(',');
            var membersSp = $("#issueInfo div input[name=membersSp]").val().split(',');
            var i, l = 0;
            l = memberIds.length;
            for (i = 0; i < l; i++) {
                $("#addIssueMembers option[value=" + memberIds[i] + "]").prop('selected', true);
                issueForm.addIssueMember(membersSp[i]);
            }

            // Тестеры
            var testerIds = $("#issueInfo div input[name=testers]").val().split(',');
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
            // изображения
            var imgs = $("#issueInfo div > .images-line > li");
            l = imgs.length;
            var $imgInputField = $('#issueForm form .images-list > li').has('input[name="images[]"]');
            var $imgInput = $('#issueForm form .images-list').empty();
            var imgLI = null;
            for (i = l - 1; i >= 0; i--) {
                //$('input[name=imgId]',imgs[i]).val() 
                imgLI = imgs[i].cloneNode(true);
                $(imgLI).append('<a href="javascript:;" class="remove-btn" onclick="removeImage(' +
                    $('input[name=imgId]', imgLI).val() + ')"></a>');
                $imgInput.append(imgLI);
                //imgInput.insertBefore(imgLI, imgInput.children[0]);
            };
            $imgInput.append($imgInputField);
            if (l >= window.lpmOptions.issueImgsCount) {
                $("#issueForm form .images-list > li input[type=file]").hide();
                $("#issueForm form li a[name=imgbyUrl]").hide();
            }

            // родитель
            $("#issueForm form input[name=parentId]").val($("#issueInfo input[name=parentId]").val());
            // идентификатор задачи
            $("#issueForm form input[name=issueId]").val($("#issueInfo input[name=issueId]").val());
            // действие меняем на редактирование
            $("#issueForm form input[name=actionType]").val('editIssue');
            // меняем заголовок кнопки сохранения    
            $("#issueForm form .save-line button[type=submit]").text("Сохранить");

        }
    }
);

function showMain() {
    window.location.hash = '';
    states.updateView();
};
