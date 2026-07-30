// Кастомный date picker на базе Vanilla Calendar Pro.
//
// Превращает поле с классом `.js-date-picker` в удобный, единообразный во всех
// браузерах календарь. Значение поля хранится и отправляется в ISO-формате
// (YYYY-MM-DD) через скрытый input с тем же `name`, а пользователю показывается
// дата в формате ДД.ММ.ГГГГ. Это сохраняет контракт для серверного кода и
// остального JS, который читает/пишет значение по имени поля.
//
// Дату можно выбрать в календаре (по клику на поле или иконке) либо ввести
// вручную в формате ДД.ММ.ГГГГ; некорректный ввод откатывается к последнему
// валидному значению.
//
// Пикеры внутри контейнера с атрибутом `data-lpm-picker-template` пропускаются:
// такой контейнер используется как шаблон для клонирования (например, диалог
// экспорта), поле инициализируется уже на клонированной копии.
//
// Публичный API: `lpm.datePicker.attach(input)`, `lpm.datePicker.setValue(el, iso)`.

import { Calendar } from './libs/vanilla-calendar-pro.mjs';

(function () {
    var INIT_FLAG = 'lpmDatePickerInit';

    function isoToDisplay(iso) {
        if (!iso) return '';
        var p = String(iso).split('-');
        return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : iso;
    }

    // Разбирает введённую вручную дату.
    // Возвращает ISO (YYYY-MM-DD), '' для пустой строки или null, если ввод некорректен.
    function parseDisplay(str) {
        str = (str || '').trim();
        if (!str) return '';
        var m = str.match(/^(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{2,4})$/);
        if (!m) return null;
        var day = +m[1], month = +m[2], year = +m[3];
        if (year < 100) year += 2000;
        if (month < 1 || month > 12 || day < 1 || day > 31) return null;
        var date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return null;
        }
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return year + '-' + pad(month) + '-' + pad(day);
    }

    // Приводит значение к ISO (YYYY-MM-DD). ISO принимается как есть, форматы
    // ДД.ММ.ГГГГ / ДД/ММ/ГГГГ / ДД-ММ-ГГГГ разбираются; иначе возвращает ''.
    function toIso(value) {
        value = (value || '').trim();
        if (!value) return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
        var iso = parseDisplay(value);
        return iso === null ? '' : iso;
    }

    function attach(input) {
        if (!input || input.dataset[INIT_FLAG]) return;
        if (input.closest('[data-lpm-picker-template]')) return;
        input.dataset[INIT_FLAG] = '1';

        var iso0 = (input.value || '').trim();
        var name = input.getAttribute('name');

        // Скрытое поле хранит отправляемое ISO-значение под настоящим именем.
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        if (name) {
            hidden.name = name;
            input.removeAttribute('name');
        }
        hidden.value = iso0;

        // input-group: поле + кнопка с иконкой календаря.
        var wrap = document.createElement('div');
        wrap.className = 'input-group lpm-date-picker';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'input-group-text lpm-date-picker__toggle';
        toggleBtn.tabIndex = -1;
        toggleBtn.setAttribute('aria-label', 'Открыть календарь');
        toggleBtn.innerHTML = '<i class="far fa-calendar"></i>';
        wrap.appendChild(toggleBtn);
        wrap.appendChild(hidden);

        input.autocomplete = 'off';
        if (!input.placeholder) input.placeholder = 'дд.мм.гггг';

        function sync(iso) {
            hidden.value = iso || '';
            input.value = isoToDisplay(iso);
        }

        var calendar = new Calendar(input, {
            inputMode: true,
            positionToInput: 'auto',
            firstWeekday: 1,
            locale: 'ru',
            selectedTheme: 'light',
            openOnFocus: false,
            enableDateToggle: true,
            enableJumpToSelectedDate: true,
            selectedDates: iso0 ? [iso0] : [],
            onChangeToInput: function (self) {
                var dates = self.context.selectedDates;
                sync((dates && dates[0]) || '');
                self.hide();
            },
        });
        calendar.init();
        sync(iso0);

        // Ссылки для программного изменения значения (см. setValue).
        input._lpmCalendar = hidden._lpmCalendar = calendar;
        input._lpmSync = hidden._lpmSync = sync;

        function applyManualInput() {
            var iso = parseDisplay(input.value);
            if (iso === null) {
                // Некорректный ввод — возвращаем последнее валидное значение.
                input.value = isoToDisplay(hidden.value);
                return;
            }
            calendar.selectedDates = iso ? [iso] : [];
            calendar.set({ dates: true });
            sync(iso);
        }

        input.addEventListener('change', applyManualInput);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyManualInput();
                calendar.hide();
            }
        });
        // Клик по иконке открывает календарь тем же путём, что и клик по полю
        // (Vanilla Calendar инициализирует inputMode по клику именно на input).
        toggleBtn.addEventListener('click', function () {
            input.focus();
            input.click();
        });
    }

    // Программно задаёт дату для уже инициализированного поля. Значение может быть
    // в ISO (YYYY-MM-DD) либо в ДД.ММ.ГГГГ / ДД/ММ/ГГГГ / ДД-ММ-ГГГГ — оно
    // приводится к ISO; нераспознанное значение очищает поле.
    // `el` — видимое поле пикера либо связанный с ним скрытый input.
    function setValue(el, value) {
        if (!el) return;
        var iso = toIso(value);
        var calendar = el._lpmCalendar;
        if (calendar) {
            calendar.selectedDates = iso ? [iso] : [];
            calendar.set({ dates: true });
        }
        if (el._lpmSync) {
            el._lpmSync(iso);
        } else {
            el.value = iso;
        }
    }

    function attachAll(root) {
        (root || document).querySelectorAll('.js-date-picker').forEach(attach);
    }

    // Инициализация полей, добавленных в DOM динамически (например, диалог экспорта).
    function observe() {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches('.js-date-picker')) attach(node);
                    if (node.querySelectorAll) node.querySelectorAll('.js-date-picker').forEach(attach);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.lpm = window.lpm || {};
    window.lpm.datePicker = { attach: attach, attachAll: attachAll, setValue: setValue };

    function init() {
        attachAll();
        observe();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
