<?php
/**
 * Данные для IssueComment с типом IssueCommentType::MERGE_REQUEST.
 *
 * Состояния сборок хранятся в разрезе пары «задача — merge request»
 * ({@see IssuePipeline}), поэтому комментарий несёт идентификатор своего MR.
 */
class IssueCommentMergeRequestData
{
    /**
     * Выделяет исходную ветку merge request'а из текста комментария.
     *
     * Нужно комментариям, записанным до того, как идентификатор MR стал
     * сохраняться: по ветке merge request задачи находится в {@see IssueMR}.
     *
     * @param  string $text Текст комментария.
     * @return string Имя ветки; пустая строка, если веток в тексте нет.
     */
    public static function parseSourceBranch(string $text): string
    {
        return preg_match(self::BRANCHES_PATTERN, $text, $matches) ? $matches[1] : '';
    }

    /**
     * Сериализует данные комментария.
     *
     * @param int $mrId Идентификатор MR на GitLab
     *                  ({@see GitlabMergeRequest::$id}).
     */
    public static function serialize($mrId): string
    {
        return serialize([(int)$mrId]);
    }

    /**
     * Строка комментария с ветками merge request'а: `исходная → целевая`.
     *
     * Занимает отдельную строку и стоит перед описанием MR, поэтому
     * поиск идёт по границам строки и берёт первое совпадение.
     * Имя ветки в git не содержит пробелов.
     */
    const BRANCHES_PATTERN = '/^`([^\s`]+)\s*→\s*[^\s`]+`\r?$/m';

    /**
     * GitlabMergeRequest::$id; 0, если идентификатор не сохранён.
     * @var int
     */
    public $mrId;

    public function __construct(string $data)
    {
        $deserialized = unserialize($data);
        $this->mrId = is_array($deserialized) && isset($deserialized[0])
            ? (int)$deserialized[0]
            : 0;
    }
}
