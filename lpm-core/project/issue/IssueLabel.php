<?php
/**
 * Метка задачи: справочник меток проектов и учёт их использований.
 */
class IssueLabel extends LPMBaseObject
{
    /**
     * Возвращает список стандартных меток для задачи, отсортированных по количеству использований
     * в рамках указанного проекта (общие метки ранжируются по использованиям именно в этом проекте,
     * а не суммарно по всем проектам).
     * @return array[{id, label, countUses, projectUses, projectId}...n] Список меток для задачи.
     */
    public static function getLabels($projectId)
    {
        $projectId = (int) $projectId;
        $labels = array();
        // countUses — суммарное количество использований по всем проектам (вторичный критерий),
        // projectUses — количество использований метки в текущем проекте (основной критерий).
        $sql = "SELECT `l`.`id`, `l`.`label`, `l`.`countUses`, `l`.`projectId`, " .
            "COALESCE(`u`.`countUses`, 0) AS `projectUses` " .
            "FROM `%1\$s` `l` " .
            "LEFT JOIN `%2\$s` `u` ON `u`.`labelId` = `l`.`id` AND `u`.`projectId` = " . $projectId . " " .
            "WHERE (`l`.`deleted` = " . LabelState::ACTIVE . ") AND " .
            "(`l`.`projectId` = " . $projectId . " OR `l`.`projectId` = 0) " .
            "ORDER BY `projectUses` DESC, `l`.`countUses` DESC";

        $db = LPMGlobals::getInstance()->getDBConnect();
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS, LPMTables::ISSUE_LABEL_USES);
        if ($res) {
            while ($array = $res->fetch_assoc()) {
                $labels[] = $array;
            }
        }
        return $labels;
    }

    /**
     * Возвращает список меток во всех проектах по тексту метки.
     * @param Имя меток, которые нужно вернуть.
     * @return array Список меток по имени.
     */
    public static function getLabelsByLabelText($label)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $label = $db->escape_string($label);
        $labels = array();
        $sql = "SELECT * FROM `%s` WHERE `label` = '" . $label . "'";
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS);
        if ($res) {
            while ($array = $res->fetch_assoc()) {
                $labels[] = $array;
            }
        }
        return $labels;
    }

    /**
     * Возвращает метку по id.
     * @param $id
     * @return array|null
     */
    public static function getLabel($id)
    {
        $id = (int) $id;
        $sql = "SELECT * FROM `%s` WHERE `id` = " . $id;
        $db = LPMGlobals::getInstance()->getDBConnect();
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS);
        return ($res) ? $res->fetch_assoc() : null;
    }

    /**
     * Добавить метками количество использований.
     * Увеличивает как суммарный счётчик метки (`countUses`), так и счётчик использований
     * метки в рамках указанного проекта (таблица использований по проектам).
     * @param $labelNames Список имен меток, которым нужно добавить использование.
     * @param $projectId Идентификатор проекта приоритет метки которого нужно изменить, либо 0,
     * если нужно изменить приоритет только общей для проектов метки.
     */
    public static function addLabelsUsing($labelNames, $projectId = 0)
    {
        $projectId = (int) $projectId;
        if (empty($labelNames)) {
            return;
        }

        $db = LPMGlobals::getInstance()->getDBConnect();
        foreach ($labelNames as $key => $value) {
            $labelNames[$key] = $db->escape_string($value);
        }
        $inList = "'" . implode("','", $labelNames) . "'";

        // Суммарный счётчик использований по всем проектам (вторичный критерий сортировки).
        $sql = "UPDATE `%s` SET `countUses` = `countUses` + 1 WHERE `label` IN(" . $inList . ")" .
            " AND (`projectId` = 0 OR `projectId` = " . $projectId . ")";
        $db->queryt($sql, LPMTables::ISSUE_LABELS);

        // Счётчик использований метки в рамках конкретного проекта (основной критерий сортировки).
        // Область совпадает с обновлением суммарного счётчика выше (без фильтра по `deleted`):
        // отключённые проектные метки, замещённые общими, продолжают накапливать статистику,
        // чтобы при их восстановлении (removeLabel) значение projectUses не оказалось устаревшим.
        // Источник обёрнут в подзапрос, выбирающий только `id`: иначе колонка `countUses` есть
        // и в таблице-источнике, и в целевой, из-за чего ссылка в ON DUPLICATE KEY UPDATE
        // становится неоднозначной (ERROR 1052) и запрос молча не выполняется.
        $sql = "INSERT INTO `%1\$s` (`labelId`, `projectId`, `countUses`) " .
            "SELECT `src`.`id`, " . $projectId . ", 1 " .
            "FROM (SELECT `id` FROM `%2\$s` " .
            "WHERE `label` IN(" . $inList . ") " .
            "AND (`projectId` = 0 OR `projectId` = " . $projectId . ")) `src` " .
            "ON DUPLICATE KEY UPDATE `countUses` = `countUses` + 1";
        $db->queryt($sql, LPMTables::ISSUE_LABEL_USES, LPMTables::ISSUE_LABELS);
    }

    /**
     * Переносит (суммирует) использования по проектам с метки-источника на целевую метку.
     * Применяется при слиянии меток (например, повышение проектной метки до общей), чтобы
     * накопленная статистика использований в проектах сохранилась за целевой (активной) меткой.
     * Строки метки-источника не удаляются — так же, как при слиянии не обнуляется её `countUses`,
     * что позволяет корректно вернуть статистику при обратной операции (removeLabel).
     * @param $fromLabelId int Идентификатор метки-источника.
     * @param $toLabelId int Идентификатор целевой метки.
     */
    public static function mergeLabelUses($fromLabelId, $toLabelId)
    {
        $fromLabelId = (int) $fromLabelId;
        $toLabelId = (int) $toLabelId;
        if ($fromLabelId <= 0 || $toLabelId <= 0 || $fromLabelId === $toLabelId) {
            return;
        }

        // Источник оборачиваем в подзапрос и переименовываем `countUses` в `uses`: иначе
        // одноимённая колонка есть и в источнике, и в целевой таблице, из-за чего ссылка
        // на `countUses` в ON DUPLICATE KEY UPDATE становится неоднозначной (ERROR 1052).
        $sql = "INSERT INTO `%1\$s` (`labelId`, `projectId`, `countUses`) " .
            "SELECT " . $toLabelId . ", `src`.`projectId`, `src`.`uses` " .
            "FROM (SELECT `projectId`, `countUses` AS `uses` FROM `%1\$s` WHERE `labelId` = " . $fromLabelId . ") `src` " .
            "ON DUPLICATE KEY UPDATE `countUses` = `countUses` + VALUES(`countUses`)";
        $db = LPMGlobals::getInstance()->getDBConnect();
        $db->queryt($sql, LPMTables::ISSUE_LABEL_USES);
    }

    /**
     * Возвращает список меток по имени.
     *
     * Метки - это идущие подряд блоки в квадратных скобках в начале имени;
     * между ними допустимы пробелы. Текст метки может быть на любом языке,
     * пустые блоки пропускаются.
     *
     * @param $issueName Имя задачи.
     * @return array<string> Список меток в указанном имени.
     */
    public static function getLabelsByName($issueName)
    {
        $name = trim($issueName);
        if (mb_substr($name, 0, 1) !== '[') return [];

        $labels = [];
        $matches = [];
        if (preg_match_all(self::LABELS_PATTERN, $name, $matches)) {
            foreach ($matches[1] as $label) {
                $label = trim($label);
                if ($label !== '' && !in_array($label, $labels)) {
                    $labels[] = $label;
                }
            }
        }
        return $labels;
    }

    /**
     * Сохраняет метку.
     * @param $label string Текст метки.
     * @param $projectId int Идентификатор проекта для которого создается метка (если не передан, то метка будет общей).
     * @param $id int Идентификатор метки (если не передан, то будет создана новая метка).
     * @param $countUses int Количество использований метки.
     * @param $deleted bool Удалена ли метка.
     * @return int|null Идентификатор вставленной/обновленной записи или null в случае ошибки.
     */
    public static function saveLabel($label, $projectId = 0, $id = 0, $countUses = 0, $deleted = 0)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $id = ((int)$id > 0) ? (int)$id : "NULL";
        $projectId = (int) $projectId;
        $countUses = (int) $countUses;
        $label = $db->escape_string($label);

        $sql = "INSERT INTO `%s` (`id`, `projectId`, `label`, `countUses`, `deleted`) " .
            "VALUES ('" . $id . "', '" . $projectId . "', '" . $label . "', '" . $countUses . "', '" . $deleted . "') " .
            "ON DUPLICATE KEY UPDATE ".
            "`projectId` = VALUES(`projectId`), `label` = VALUES(`label`), `countUses` = VALUES(`countUses`), `deleted` = VALUES(`deleted`)";

        if ($db->queryt($sql, LPMTables::ISSUE_LABELS)) {
            return $db->insert_id;
        }
        return null;
    }

    /**
     * Регистрирует использование меток из имени задачи в справочнике: заводит
     * недостающие метки проекта и начисляет им использование. Метки, уже
     * присутствовавшие в $oldName, повторно не учитываются — вызывать при
     * каждом сохранении имени задачи (создании или переименовании), а не
     * только один раз при создании.
     * Имена передаются в «сыром» виде, без экранирования — как в Issue::createNew().
     * @param $name string Имя задачи после сохранения.
     * @param $projectId int Идентификатор проекта, к которому относится задача.
     * @param $oldName string|null Имя задачи до сохранения, либо null, если
     * задача только создаётся.
     */
    public static function registerLabelsUsage($name, $projectId, $oldName = null)
    {
        $labels = self::getLabelsByName($name);

        if ($oldName !== null) {
            $oldLabels = self::getLabelsByName($oldName);
            foreach ($labels as $key => $value) {
                if (in_array($value, $oldLabels)) {
                    unset($labels[$key]);
                }
            }
        }

        if (empty($labels)) {
            return;
        }

        $allLabels = self::getLabels($projectId);
        $countedLabels = [];
        foreach ($allLabels as $value) {
            $index = array_search($value['label'], $labels);
            if ($index !== false) {
                $countedLabels[] = $labels[$index];
                unset($labels[$index]);
            }
        }

        if (!empty($countedLabels)) {
            self::addLabelsUsing(self::escapePercentForQueryt($countedLabels), $projectId);
        }

        if (!empty($labels)) {
            // Создаём новые метки без использований, затем через addLabelsUsing
            // начисляем использование и в общий счётчик, и в счётчик по проекту.
            foreach ($labels as $newLabel) {
                self::saveLabel(str_replace('%', '%%', $newLabel), $projectId, 0, 0);
            }
            self::addLabelsUsing(self::escapePercentForQueryt($labels), $projectId);
        }
    }

    /**
     * Удаляет метку.
     * @param $id int Идентификатор метки.
     * @param $deleted bool Состояние удаления метки.
     * @return bool true в случае успешной операции, иначе false.
     */
    public static function changeLabelDeleted($id, $deleted)
    {
        $id = (int)$id;
        if ($id > 0) {
            $sql = "UPDATE `%s` SET `deleted` = " . $deleted . " WHERE `id` = " . $id;

            $db = LPMGlobals::getInstance()->getDBConnect();
            return ($db->queryt($sql, LPMTables::ISSUE_LABELS)) ? true : false;
        }
    }

    /**
     * Экранирует знак процента в каждой строке списка для queryt().
     * @param $values string[]
     * @return string[]
     */
    private static function escapePercentForQueryt(array $values)
    {
        return array_map(function ($value) {
            return str_replace('%', '%%', $value);
        }, $values);
    }

    /**
     * Метка в начале имени задачи: блок в квадратных скобках и пробелы за ним.
     *
     * Шаблон привязан к началу поиска (`A`), поэтому подряд идущие совпадения -
     * это ровно те метки, с которых начинается имя. Должен совпадать
     * с разбором меток в issue-form.js.
     */
    const LABELS_PATTERN = '/\[([^\]]*)\]\s*/uA';
}
