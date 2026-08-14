<?php
/**
 * Миграции схемы БД.
 *
 * Использование (из корня приложения):
 *   php lpm-cli/migrate.php status              состояние миграций
 *   php lpm-cli/migrate.php apply [--dry-run]   применить неприменённые
 *   php lpm-cli/migrate.php rollback [--step=N] откатить последние N (по умолчанию 1)
 *   php lpm-cli/migrate.php create <имя>        создать заготовку миграции
 *   php lpm-cli/migrate.php baseline            отметить все применёнными, не выполняя
 *
 * Коды возврата: 0 — успех, 1 — ошибка, 2 — есть неприменённые миграции
 * (для `status`, чтобы команду можно было использовать как проверку в CI).
 *
 * Длительные ALTER лучше выполнять именно отсюда: у процесса нет ни лимита
 * времени PHP, ни таймаутов веб-сервера, а вывод попадает в лог деплоя.
 */

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit(1);
}

require_once __DIR__ . '/../lpm-core/init.inc.php';

set_time_limit(0);

exit(runCommand($argv));

/**
 * @param array $argv Аргументы запуска.
 * @return int Код возврата.
 */
function runCommand(array $argv)
{
    $args = array_slice($argv, 1);
    $command = '';
    $options = [];
    $params = [];

    foreach ($args as $arg) {
        if (strpos($arg, '--') === 0) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = isset($parts[1]) ? $parts[1] : true;
        } elseif ($command === '') {
            $command = $arg;
        } else {
            $params[] = $arg;
        }
    }

    if ($command === '' || isset($options['help'])) {
        printUsage();
        return $command === '' ? 1 : 0;
    }

    try {
        switch ($command) {
            case 'status':
                return commandStatus();
            case 'apply':
                return isset($options['dry-run']) ? commandPreview() : commandApply();
            case 'rollback':
                return commandRollback(isset($options['step']) ? (int)$options['step'] : 1);
            case 'create':
                return commandCreate(isset($params[0]) ? $params[0] : '');
            case 'baseline':
                return commandBaseline();
            default:
                out('Неизвестная команда: ' . $command);
                printUsage();
                return 1;
        }
    } catch (\Throwable $e) {
        out('Ошибка: ' . $e->getMessage());
        return 1;
    }
}

/**
 * Показывает состояние всех миграций.
 * @return int Код возврата: 2, если есть неприменённые.
 */
function commandStatus()
{
    $migrator = new DbMigrator();
    $states = $migrator->getStatus();

    out('Каталог: ' . $migrator->getMigrationsDir());
    out('');

    if (empty($states)) {
        out('Миграций нет.');
        return 0;
    }

    $applied = 0;
    $pending = 0;
    foreach ($states as $state) {
        if ($state->isApplied()) {
            $applied++;
        } else {
            $pending++;
        }

        out(sprintf('  %s %s%s', statusMark($state), $state->name, statusNote($state)));

        if ($state->getError() !== '') {
            out('      ' . $state->getError());
        }
    }

    out('');
    out(sprintf('Применено: %d, ожидают применения: %d', $applied, $pending));

    $orphans = $migrator->getOrphanNames();
    if (!empty($orphans)) {
        out('');
        out('В журнале есть записи без файлов миграций (файл удалён или переименован):');
        foreach ($orphans as $name) {
            out('  ' . $name);
        }
    }

    return $pending > 0 ? 2 : 0;
}

/**
 * Показывает, что сделает apply, ничего не изменяя.
 * @return int Код возврата.
 */
function commandPreview()
{
    $preview = (new DbMigrator())->preview();

    if (!empty($preview['baseline'])) {
        out('Будут отмечены применёнными без выполнения (схема уже создана дампом):');
        foreach ($preview['baseline'] as $name) {
            out('  ' . $name);
        }
        out('');
    }

    if (empty($preview['pending'])) {
        out('Нет миграций для применения.');
        return 0;
    }

    out('Будут применены:');
    foreach ($preview['pending'] as $name) {
        out('  ' . $name);
    }

    return 0;
}

/**
 * Применяет неприменённые миграции.
 * @return int Код возврата.
 */
function commandApply()
{
    $report = (new DbMigrator())->apply();

    if (!empty($report['baseline'])) {
        out(sprintf(
            'Схема существующей установки принята за исходную; отмечено применёнными без выполнения: %d.',
            count($report['baseline'])
        ));
        out('');
    }

    if (empty($report['results'])) {
        out('Нет миграций для применения.');
        return 0;
    }

    return printResults($report['results'], 'Применена', 'Не удалось применить', true);
}

/**
 * Откатывает последние применённые миграции.
 * @param int $steps Сколько миграций откатить.
 * @return int Код возврата.
 */
function commandRollback($steps)
{
    $results = (new DbMigrator())->rollback($steps);

    if (empty($results)) {
        out('Нет применённых миграций для отката.');
        return 0;
    }

    return printResults($results, 'Откачена', 'Не удалось откатить');
}

/**
 * Создаёт заготовку миграции.
 * @param string $slug Имя миграции.
 * @return int Код возврата.
 */
function commandCreate($slug)
{
    if ($slug === '') {
        out('Укажите имя миграции: create <имя>');
        return 1;
    }

    $path = (new DbMigrator())->create($slug);
    out('Создана миграция: ' . $path);

    return 0;
}

/**
 * Отмечает все неприменённые миграции применёнными, не выполняя их.
 * @return int Код возврата.
 */
function commandBaseline()
{
    $names = (new DbMigrator())->baseline();

    if (empty($names)) {
        out('Нет миграций для отметки.');
        return 0;
    }

    out('Отмечены применёнными без выполнения:');
    foreach ($names as $name) {
        out('  ' . $name);
    }

    return 0;
}

/**
 * Печатает результаты применения или отката миграций.
 * @param array $results Результаты из DbMigrator.
 * @param string $okLabel Подпись успешного результата.
 * @param string $failLabel Подпись неудачного результата.
 * @param bool $warnOnFail Предупреждать ли о частично изменённой схеме.
 * @return int Код возврата.
 */
function printResults(array $results, $okLabel, $failLabel, $warnOnFail = false)
{
    $failed = false;
    foreach ($results as $result) {
        if ($result['ok']) {
            out(sprintf('%s %s (%d мс)', $okLabel, $result['name'], $result['execTime']));
        } else {
            $failed = true;
            out(sprintf('%s %s: %s', $failLabel, $result['name'], $result['error']));
        }
    }

    if ($failed && $warnOnFail) {
        out('');
        out('Выполнение остановлено. Схема могла остаться изменённой частично —');
        out('исправьте причину и запустите команду снова.');
    }

    return $failed ? 1 : 0;
}

/**
 * Отметка состояния миграции для вывода.
 * @param DbMigrationState $state Состояние миграции.
 * @return string
 */
function statusMark(DbMigrationState $state)
{
    if ($state->isFailed()) {
        return '[!]';
    }

    if ($state->isInterrupted()) {
        return '[?]';
    }

    return $state->isApplied() ? '[x]' : '[ ]';
}

/**
 * Пояснение к состоянию миграции.
 * @param DbMigrationState $state Состояние миграции.
 * @return string
 */
function statusNote(DbMigrationState $state)
{
    $note = ' — ' . $state->getStatusText();

    if ($state->isChanged()) {
        $note .= '; файл изменён после применения';
    }

    return $note;
}

/**
 * Печатает справку по командам.
 */
function printUsage()
{
    out('Миграции схемы БД.');
    out('');
    out('  php lpm-cli/migrate.php status              состояние миграций');
    out('  php lpm-cli/migrate.php apply [--dry-run]   применить неприменённые');
    out('  php lpm-cli/migrate.php rollback [--step=N] откатить последние N (по умолчанию 1)');
    out('  php lpm-cli/migrate.php create <имя>        создать заготовку миграции');
    out('  php lpm-cli/migrate.php baseline            отметить все применёнными, не выполняя');
}

/**
 * Печатает строку вывода.
 * @param string $message Текст.
 */
function out($message)
{
    fwrite(STDOUT, $message . PHP_EOL);
}
