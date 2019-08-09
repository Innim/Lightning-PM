$(document).ready(function(){
    
    $("form").hide();
    //Проверка, добавлeн ли тестер к проекту, если тестер добавлен - показываем,
    //если нет выполняем функцию добавления.
    (function TesterCheck(){
        let Check = window.location.href;
        $.post(
            "/lpm-core/tester/tester-check.php",
            {
                Check: Check
            },
            Checkedfun
        );
        function Checkedfun(data){
            if(data === 'NotFoundProgect') {
                TesterStartWrite();
            } else {
                console.log(data);
                $('#NameTester').text(data);
            }
        }
    })();
    //Добавление тестера
    function TesterStartWrite() {
        $("form").show();
        // let options_leg = $("option").length;
        // for (let i = 0; i < options_leg; i++) {
        //     let optionFor = $("option").eq(i).val();
        //     console.log(optionFor);
        // }

        //Получаем значение из select, в его value записывается ID Тестера,
        // В его  содержимом находятся атрибуты: Имя Фамилия и логин
        $('#btnSellect').click(function (event) {
            valueSelected = $('select').val();
            textSelected = $('select option:selected').text();
            //если тестер не выбран, сбрасываем запрос к БД
            if(valueSelected === "Выбрать тестера"){
                    event.preventDefault();
            } else {

            $('#NameTester').empty();
            urlProject = window.location.href;
            //Полученные значения передаём на запись в базу данных
            $.post(
                "/lpm-core/tester/tester-add.php",
                {
                    params: valueSelected,
                    params2: textSelected,
                    params3: urlProject
                },
                onAjaxSuccess
            ).done(function(){
                console.log(' -- Успешная отправка ajax запроса -- ');
            }).fail(function(){
                console.log(' -- Ошибка ajax запроса -- ');
            });
            function onAjaxSuccess(data){
                console.log(data);
            }
            funHideAppend();
        }
        });

           //Скрываем поля и показываем выбранного Тестера
        function funHideAppend() {
            $('form').hide();
            $('#NameTester').append(textSelected).show();
        }
    }
});