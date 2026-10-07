<?php
/**
 * Срок действия пользовательского токена GitLab.
 *
 * GitLab отдаёт `expires_at` при выписке токена, но раньше значение
 * выбрасывалось: протухший токен в базе был неотличим от рабочего, и
 * перевыписать его заранее было нечем.
 *
 * Колонка допускает NULL: у токенов, выписанных до этой миграции, срок
 * неизвестен — его негде взять, не перевыписав токен.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::USERS);
        if ($this->columnExists($table, 'gitlabTokenExpiresAt')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}`
            ADD `gitlabTokenExpiresAt` date DEFAULT NULL
            COMMENT 'дата истечения токена GitLab' AFTER `gitlabId`");
    }

    public function down()
    {
        $table = $this->t(LPMTables::USERS);
        if (!$this->columnExists($table, 'gitlabTokenExpiresAt')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP `gitlabTokenExpiresAt`");
    }
};
