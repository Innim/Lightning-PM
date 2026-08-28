<?php
/**
 * Слоты параллельного сжатия видео: общий на сервер счётчик запущенных
 * процессов ffmpeg, чтобы одновременная загрузка нескольких роликов
 * не съела всю память.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::VIDEO_COMPRESS_SLOTS);
        $files = $this->t(LPMTables::FILES);

        if (!$this->tableExists($table)) {
            // slotNo — PRIMARY KEY, fileId — UNIQUE: слот занимается одним
            // INSERT IGNORE, который атомарно резервирует и номер слота,
            // и сам файл (двойной запуск сжатия одного файла невозможен).
            $this->exec("CREATE TABLE `{$table}` (
                `slotNo` tinyint unsigned NOT NULL COMMENT 'номер слота параллельного сжатия',
                `fileId` bigint NOT NULL COMMENT 'файл, занимающий слот',
                `pid` int unsigned NOT NULL DEFAULT '0' COMMENT 'PID процесса воркера, 0 — ещё не запущен',
                `startedAt` int unsigned NOT NULL COMMENT 'время занятия слота, unix time',
                PRIMARY KEY (`slotNo`),
                UNIQUE KEY `fileId` (`fileId`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci "
                . "COMMENT='занятые слоты фонового сжатия видео'");
        }

        // Очередь выбирается по этому статусу при каждой загрузке видео
        // и при завершении каждого сжатия.
        if (!$this->indexExists($files, 'compressStatus')) {
            $this->exec("ALTER TABLE `{$files}` ADD KEY `compressStatus` (`compressStatus`)");
        }

        // Файлы, которые старый код оставил в статусе "в обработке" (1): их
        // воркеры не знают про слоты, и новый код принял бы такой файл за
        // ожидающий очереди — то есть запустил бы для него второе сжатие.
        // 3 — ошибка сжатия: оригинал файла на месте и доступен.
        $this->exec("UPDATE `{$files}` SET `compressStatus` = 3 WHERE `compressStatus` = 1");
    }

    public function down()
    {
        $table = $this->t(LPMTables::VIDEO_COMPRESS_SLOTS);
        $files = $this->t(LPMTables::FILES);

        if ($this->tableExists($table)) {
            $this->exec("DROP TABLE `{$table}`");
        }

        if ($this->indexExists($files, 'compressStatus')) {
            $this->exec("ALTER TABLE `{$files}` DROP KEY `compressStatus`");
        }
    }
};
