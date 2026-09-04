<?php
/**
 * Данные для IssueComment с типом IssueCommentType::BRANCH_MERGED.
 *
 * Комментарий может рассказывать сразу о нескольких ветках задачи, если все
 * они влиты одним пушем, поэтому данные хранят список веток.
 */
class IssueCommentBranchMergedData
{
    /**
     * Сериализует данные одной влитой ветки.
     */
    public static function serialize($repositoryId, $branchName): string
    {
        return serialize([[$repositoryId, $branchName]]);
    }

    /**
     * Сериализует данные всех веток, о влитии которых говорит комментарий.
     *
     * @param IssueBranch[] $issueBranches Влитые ветки.
     * @param string $sha Коммит, которым ветки попали в целевую ветку:
     * по нему состояние сборки привязывается именно к этому влитию, а не
     * к более позднему влитию той же ветки.
     */
    public static function serializeBy(array $issueBranches, string $sha = ''): string
    {
        $branches = [];
        foreach ($issueBranches as $issueBranch) {
            $branches[] = [$issueBranch->repositoryId, $issueBranch->name, $sha];
        }

        return serialize($branches);
    }

    /**
     * Разбирает сохранённое значение в список веток.
     *
     * Значения, записанные до появления нескольких веток в одном комментарии,
     * хранят одну пару [repositoryId, branchName] на верхнем уровне - такой
     * формат тоже читается. Коммит влития появился ещё позже, поэтому у
     * записанного раньше он пуст.
     *
     * @return array Список веток в формате {@see self::$branches}.
     */
    private static function parseBranches($deserialized): array
    {
        if (!is_array($deserialized) || empty($deserialized)) {
            return [];
        }

        if (!is_array($deserialized[0])) {
            $deserialized = [$deserialized];
        }

        $branches = [];
        foreach ($deserialized as $branch) {
            if (!is_array($branch) || count($branch) < 2) {
                continue;
            }

            $branches[] = [
                'repositoryId' => $branch[0],
                'branchName'   => $branch[1],
                'sha'          => isset($branch[2]) ? (string)$branch[2] : '',
            ];
        }

        return $branches;
    }

    /**
     * Влитые ветки, о которых говорит комментарий.
     *
     * Каждый элемент - массив с ключами `repositoryId`, `branchName` и `sha`.
     *
     * @var array
     */
    public $branches;

    function __construct(string $data)
    {
        $this->branches = self::parseBranches(unserialize($data));
    }
}
