<?php
/**
 * Читает CHANGELOG.md и разбивает его на список выпущенных версий
 * для страницы изменений.
 */
class ChangelogParser
{
    /**
     * Bootstrap-классы фона/текста бейджа для известных типов изменений.
     * Ключ — заголовок секции в нижнем регистре.
     */
    private static $sectionBadges = [
        'added'      => 'bg-success',
        'changed'    => 'bg-primary',
        'fixed'      => 'bg-warning text-dark',
        'security'   => 'bg-danger',
        'removed'    => 'bg-secondary',
        'deprecated' => 'bg-secondary',
    ];

    /**
     * Разбирает файл изменений на список выпущенных версий.
     *
     * Неопубликованный раздел «Next» пропускается. Тело каждой версии
     * разбивается на секции (Added/Changed/Fixed/…), содержимое секций
     * преобразуется из Markdown в HTML.
     *
     * @param  string|null $path Путь к файлу изменений. По умолчанию — CHANGELOG.md в корне.
     * @return array Список записей вида
     *               ['version' => string, 'date' => string, 'slug' => string,
     *                'sections' => array<['title' => string, 'badge' => string, 'html' => string]>],
     *               в порядке следования в файле (сверху — самые новые).
     */
    public static function parse($path = null)
    {
        if ($path === null) {
            $path = ROOT . 'CHANGELOG.md';
        }

        if (!is_readable($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $entries = [];
        $current = null;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (preg_match('/^##\s+(.+?)\s*$/u', $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = self::startEntry($m[1]);
                continue;
            }

            if ($current !== null) {
                $current['body'][] = $line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        $result = [];
        foreach ($entries as $entry) {
            // Пропускаем неопубликованные разделы (Next).
            if ($entry['version'] === null) {
                continue;
            }

            $result[] = [
                'version'  => $entry['version'],
                'date'     => $entry['date'],
                'slug'     => 'v' . $entry['version'],
                'sections' => self::parseSections($entry['body']),
            ];
        }

        return $result;
    }

    /**
     * Начинает новую запись по заголовку версии.
     *
     * @param  string $header Текст заголовка без префикса «## ».
     * @return array Запись с ключами version (null для «Next»), date, body.
     */
    private static function startEntry($header)
    {
        $version = null;
        $date    = '';

        if (preg_match('/^(\S+)\s*-\s*(.+?)\s*$/u', $header, $m)) {
            $version = $m[1];
            $date    = self::formatDate($m[2]);
        } elseif (!preg_match('/^next$/iu', trim($header))) {
            // Версия без даты, но не «Next».
            $version = trim($header);
        }

        return ['version' => $version, 'date' => $date, 'body' => []];
    }

    /**
     * Разбивает тело версии на секции по заголовкам «### ».
     * Содержимое до первой секции (если есть) попадает в секцию без заголовка.
     *
     * @param  array $lines Строки тела версии.
     * @return array Список секций ['title' => string, 'badge' => string, 'html' => string].
     */
    private static function parseSections(array $lines)
    {
        $sections = [];
        $title    = '';
        $buffer   = [];

        $flush = function () use (&$sections, &$title, &$buffer) {
            $html = self::renderMarkdown($buffer);
            if ($title !== '' || $html !== '') {
                $sections[] = [
                    'title' => $title,
                    'badge' => self::sectionBadge($title),
                    'html'  => $html,
                ];
            }
            $buffer = [];
        };

        foreach ($lines as $line) {
            if (preg_match('/^###\s+(.+?)\s*$/u', $line, $m)) {
                $flush();
                $title = $m[1];
                continue;
            }
            $buffer[] = $line;
        }
        $flush();

        return $sections;
    }

    /**
     * Возвращает Bootstrap-классы бейджа для заголовка секции.
     * Для неизвестных типов используется нейтральный цвет.
     *
     * @param  string $title
     * @return string
     */
    private static function sectionBadge($title)
    {
        $key = mb_strtolower(trim($title));
        return isset(self::$sectionBadges[$key]) ? self::$sectionBadges[$key] : 'bg-secondary';
    }

    /**
     * Приводит дату из формата ГГГГ-ММ-ДД к ДД.ММ.ГГГГ.
     * Строки в другом формате возвращаются как есть.
     *
     * @param  string $date
     * @return string
     */
    private static function formatDate($date)
    {
        $date = trim($date);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return $m[3] . '.' . $m[2] . '.' . $m[1];
        }

        return $date;
    }

    /**
     * Преобразует набор строк Markdown в безопасный HTML.
     *
     * @param  array $lines
     * @return string HTML-разметка (пустая строка, если строк нет).
     */
    private static function renderMarkdown(array $lines)
    {
        $text = trim(implode("\n", $lines));
        if ($text === '') {
            return '';
        }

        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true);

        return $parsedown->text($text);
    }
}
