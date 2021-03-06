## Next release

## 0.10.7 - 2021-07-12

### Added 

- Возможность назначить мастеров задаче.
- Выделение задач с указанным сроком выполнения.

## 0.10.6 - 2021-07-05

### Added 

- Обработка имени ветки при создании: нижний регистр и `-` как разделитель.
- Функция "Взять задачу" дает выбор - добавить или заменить исполнителя.
- Задача "В работе" не завершается автоматически при влитии в `develop`.

## 0.10.5 - 2021-06-28

### Added

- Более умный выбор репозитория по умолчанию при создании ветки.
- Более удобное добавление ссылок.
- Быстрое добавление номера спринта к имени задачи.

## 0.10.4 - 2021-06-18

### Added 

- Кнопка "Добавить ошибку" открывает форму добавления задачи с шаблоном баг-репорта.
- Кнопка "добавить себя" в исполнителях/тестерах при редактировании задачи.

## 0.10.3 - 2021-06-04

### Added 

- При перевешивании задачи в работу исполнитель ставится автоматически, если не задан.
- При создании ветки автоматически добавляется исполнитель и задача перевешивается в работу.
- Возможность не указывать дату выполнения.

### Fixed

- Кнопка "Взять задачу себе" показывается для пользователя который уже назначен исполнителем, если он тестировщик.
- В mention предлагаются заблокированные пользователи.

## 0.10.2 - 2021-04-23

### Added

- Возможность не указывать исполнителя при создании задачи.

## 0.10.1 - 2021-04-09

### Added

- Предпросмотр в форме комментария о прохождении теста.

## v0.10.0 - 2021-04-02

### Added

- В комменте с именем ветки при создании указывается название проекта (репозитория).
- Предпросмотр комментариев при добавлении.

### Fixed

- Задача повторно закрывается при влитии ветки, даже если уже была закрыта.
- Не дает добавить задачу с типом "поддержка".
- Если пользователь не привязан к GitLab, то попытка добавить задачу выдает непонятную ошибку.
- Оповещения на почту отправляются заблокированным пользователям.

## v0.9.33 - 2021-02-05

### Added

- Курсор автоматически ставится в поле названия ветки.
- Задача не отправляется в тест, если привязана неслитая ветка.

### Fixed

- Общая скрам-доска неправильно отображает список задач.
- При переносе стикера из "Тестируется" в колонку "В работе" не меняется статус задачи.
- Отсутствует кнопка "Удалить" для нового комментария.

## v0.9.32 - 2021-01-18

### Added

- Выводится остаток символов при вводе описания.
- Если передано слишком длинное описание - возвращается ошибка.
- Обновлено взаимодействие со Slack API: убрано использование устаревшего метода.

### Fixed

- В списке и при просмотре выводятся разные значения приоритета.
- При изменении приоритета в списке не меняется значение приоритета во всплывающей подсказке.

## v0.9.31 - 2021-01-15

### Fixed

- В списке веток показывается не больше 20 веток для репозитория.

## v0.9.30 - 2020-12-07

### Added

- Автоматическая отметка о том, что задача влита в `develop` и закрытие задачи.

## v0.9.29 - 2020-11-20

### Added 
- Автоматическое добавление MR к задаче: если ветка для задачи
создавалась из таска, то ссылка на открытый MR этой ветки
автоматически добавляется в комментарии к задаче, а ссылка на задачу
автоматически добавляется к MR.

## v0.9.28 - 2020-11-06

### Added
- Ссылка на SCRUM доску в списке проектов.
- Если нет тегов, то автоматически выбирается самый популярный из последних использованных в этом проекте репозиториев.
- Переделан диалог подтверждения закрытия задачи при отметке о влитии.

## v0.9.27 - 2020-10-20

### Added
- Создание ветки по нажатие Enter.

## v0.9.26 - 2020-09-21

### Added
- Сохраняем локально текст комментария для каждой задачи,
чтобы можно было дописать после перезагрузки страницы.
- Возможность писать дополнительный текст когда задача прошла тестирование.

### Fixed
- В некоторых случаях не добавляется исполнитель по умолчанию.

## v0.9.25 - 2020-09-14

### Fixed
- Не считается статистика на странце просмотра снимка доски.
- Косяки верстки с полями ввода.

## v0.9.24 - 2020-09-09

### Fixed
- При добавлении отметки о влитии в develop появляется окно завершения задачи, даже если задача уже была завершена.

## v0.9.23 - 2020-09-07

### Added
- Кнопка создания ветки взятой задачи.

## v0.9.21 - 2020-08-31

### Added
- Возможность припинить проекты для пользователя.
- Общая скрам-доска по всем проектам для пользователя.

### Fixed
- Ошибка после отмены завершения задаче при отметке о влитии.

## v0.9.20 - 2020-08-24

### Added
- Возможность сразу же завершить задачу при проставлении отметки о влитии.
- Тестеру не присылается сообщение с отметкой о влитии.
- Задача автоматически отправляется в тест по влитию MR.

## v0.9.19 - 2020-08-03

### Fixed

- При прикреплении картинок к комментарию размещает в случайном порядке
- Небольшие корректировки внешнего вида.

## v0.9.18 - 2020-07-20

### Added
- Параллельная загрузка вложений.

### Fixed
- Для только что добавленного комментария не выводятся приложения.
- Не выставляется флаг "Поместить на SCRUM доску".

## v0.9.17 - 2020-07-10

### Added
- Возможность добавлять эмодзи в комментарии, задачи и проекты 🥳.
- В комментариях показывается превью для изображений, на которые дается ссылка.
- Валидация формы добавления проекта.

### Fixed
- Ошибки при наличии после решетки не числа.
- Копятся исполнители/тестеры при отмене редактирования.

## v0.9.16 - 2020-07-04

### Added
- Возможность добавлять ссылку на задачу по номеру.
- Совет, кнопка и горячие клавиши вставки ссылки в текст.
- Подсказки горячих клавиш форматирования.

### Fixed
- Не обрабатывается наличие спринта в статистике.

## v0.9.15 - 2020-07-01

### Fixed
- Не показывается заголовок при добавлении задачи.

## v0.9.14 - 2020-06-29

### Fixed
- Не работает восстановление при ошибке во время копирования/добавления задачи по доделке.

## v0.9.13 - 2020-06-26

### Fixed
- При ошибке во время добавления/сохранения задачи часть данных не восстанавливается в форме или восстанавливается в неверном виде.

## v0.9.12 - 2020-06-24

### Fixed
- Не работает получение статуса MR из-за обновления GitLab.

## v0.9.11 - 2020-06-22

### Added
- Более читабельный номер задачи на стикере.
- Поддержка gif формата изображений.
- Обновлена библиотека загрузки изображений.
- Обработка imgur ссылок на gif изображение.
- Автофокус на поле ввода при добавлении изображения по ссылке.

### Fixed
- Большие изображения не вписываются по ширине коммента.

## v0.9.10 - 2020-06-12

### Added
- Данные о видео в комментариях и задачах подгружаются асинхронно, клиентом.
- Код проекта автоматически отформатирован в едином стиле.

### Fixed
- Блокируется загрузка YouTube видео по http.

## v0.9.9 - 2020-06-05

### Added
- Данные о MR подгружаются асинхронно, клиентом.

### Fixed
- Сломался переход по ссылке к комментарию.
- Предупреждения после входа.
- Ошибка при выставлении токена GitLab.

## v0.9.8 - 2020-04-20

### Fixed
- Не дает перейти на страницу с MR, если GitLab недоступен.

## v0.9.7 - 2020-03-27

### Added
- Обработка изменения состояния MR от Gitlab.
- Оповещение тестировщика о влитом запросе.

## v0.9.6 - 2020-03-16

### Added
- При автоматическом расчете SP они выделяются, чтобы можно было сразу ввести другое значение, если надо.
- При добавлении стикера на доску записывается время добавления. 
- Добавление первого стикера на доску считается временем начала спринта.
- В снимке доски записывается время начала спринта.
- В статистике выводится временной промежуток учтенных спринтов и количество недель.

### Fixed
- Описание не сохраняется при ошибке добавления новой задачи.
- При попытке удалить только что добавленный коммент ничего не происходит.
- При удалении 1 коммента со страницы пропадают все.
- Не обновляется имя исполнителя когда берешь задачу на доске.
- Если в inline коде есть пробел после открывающей кавычки и дальше в строке еще есть inline код - вся верстка ломается.

## v0.9.5 - 2020-03-10

### Fixed
- Не выбирается тип задачи при редактировании.
- Сломалась работа с участниками и тестерами задачи.

## v0.9.4 - 2020-03-10

### Added
- SP по исполнителям автоматически суммируются если поставить курсор в поле SP.
- Автозаполнение имени участника в тексте задачи и комментарии.
- Обновление версии jQuery.
- Подсказка значения приоритета по наведению.
- Системные подсказки по наведению заменены на кастомную.

## v0.9.3 - 2020-03-04

### Fixed
- Зависает при получении данных ссылки если недоступно облако.

## v0.9.2 - 2020-02-27

### Fixed
- Не работает, если не заданы или пусты настройки интеграции с GitLab.
- Notice на станице участников проекта, если не задан исполнитель по умолчанию.
- Notice при сохранении задачи без тестеров.
- Notice при архивировании доски.

## v0.9.1 - 2020-02-24

*Минимальная версия повышена до PHP 7.1.*

### Added
- Обработка исключения на моменте инициализации.
- Статистика SP по проекту за месяц.
- Интеграция с Gitlab: связь пользователя в таске с пользователем GitLab по email.
- Вывод статуса Merge Request по ссылке в комментариях.

### Fixed
- При добавлении тега выводится ошибка, если поле заголовка пустое.
- В превью текста в Slack отображаются лишние переносы строк.

## v0.8a.016 - 2020-02-14

### Added
- Выбор мастера проекта.
- При фильтре по исполнителю задачи в тесте не скрываются.
- Страница просмотра/редакторивания пользователя.
- Возможность задать имя/Member ID в Slack для пользователя.
- Для OG изображения используется изображение из задачи, если есть.

### Fixed
- В счетчике для задачи учитываются удаленные комментарии.
- После удаления комментария не обновляется счетчик комментариев у задачи.
- Сломалось отображение информации в профиле.

## v0.8a.015 - 2020-02-03

### Fixed
- При удалении комментария он остается на странице.

## v0.8a.014 - 2020-01-28

### Fixed
- Если код не вмещается в контейнер, то он выходит за его пределы.
- В комментариях неправильно отображаются списки.
- Заголовки в описании задачи/комментария ломают верстку.

## v0.8a.013 - 2020-01-20

### Added
- Изменение заголовка при добавлении задачи "по доделкам".
- Поддержка Slack и Markdown разметки (форматирования, списков) в описании задач.
- Комментарии поддерживают такое же форматирование, как и описание задач.
- Сочетания клавиш для форматирования для описания задачи и текста комментария.
- Скрипты и стили не кэшируются, если таск запущен в дебаг режиме. 

### Fixed
- Не сохраняется введенное название проекта при ошибке добавления.
- Сразу после добавления комментария у него сломано форматирование.

## v0.8a.012 - 2019-12-26

### Added
- В Архиве для спринта выводится информация о том, сколько SP было сделано.

## v0.8a.011 - 2019-12-23

### Added
- Валидация поля "Ник" и ограничения на допустимые символы.

### Fixed
- Не выводятся ошибки при регистрации.
- При использовании inline разметки кода строки друг под другом наезжают и перекрывают друг друга.

## v0.8a.010 - 2019-12-16
- Отображение видео в комментариях задачи

## v0.8a.009 - 2019-12-16
- Возможность для мастера проекта отправить задачу на проверку со  страницы задачи (требуется, если задача уходит в тест только после ревью)

## v0.8a.008 - 2019-11-18

### Added
- Добавлен CHANGELOG

### Fixed
- При восстановлении пароля выдается ошибка, если до этого хотя бы раз уже запрашивали восстановление.

