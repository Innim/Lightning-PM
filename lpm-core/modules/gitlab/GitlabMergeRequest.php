<?php
/**
 * Данные Gitlab Merge Request.
 */
class GitlabMergeRequest extends \GMFramework\StreamObject
{
    const STATE_OPENED = 'opened';
    const STATE_MERGED = 'merged';
    const STATE_CLOSED = 'closed';
    const STATE_LOCKED = 'locked';

    /**
     * Идентификатор запроса.
     * @var int
     */
    public $id;

    /**
     * Внутренний ID запроса,
     *
     * ID внутри проекта, он обычно используется в URL и так далее,
     * @var int
     */
    public $internalId;

    /**
     * Текущее состояние (см. STATE_*).
     * @var string
     */
    public $state;

    /**
     * Ссылка на MR в веб-интерфейсе.
     * @var String
     */
    public $url;

    /**
     * Название исходной ветки.
     * @var String
     */
    public $sourceBranch;

    /**
     * Название целевой ветки.
     * @var String
     */
    public $targetBranch;

    /**
     * ID проекта исходной ветки.
     * @var int
     */
    public $sourceProjectId;

    /**
     * ID проекта целевой ветки.
     * @var int
     */
    public $targetProjectId;

    /**
     * Название MR.
     * @var String
     */
    public $title;

    /**
     * Описание MR.
     * @var String
     */
    public $description;

    /**
     * Дата влития MR.
     * @var \GMFramework\Date
     */
    public $mergedAt;

    /**
     * SHA последнего коммита исходной ветки.
     * @var string
     */
    public $sha;

    /**
     * SHA мерж-коммита; пусто, если мерж-коммита нет
     * (влитие перемоткой или со squash без мерж-коммита).
     * @var string
     */
    public $mergeCommitSha;

    /**
     * SHA squash-коммита; пусто, если изменения влиты без squash.
     * @var string
     */
    public $squashCommitSha;

    /**
     * Является ли MR черновиком (Draft).
     *
     * У черновика состояние остаётся {@see self::STATE_OPENED},
     * это отдельный признак незавершённости.
     * @var bool
     */
    public $draft;

    private $_data;

    public function __construct($data)
    {
        parent::__construct();

        $this->_data = $data;

        $this->loadStream($data);
    }

    protected function initTypes()
    {
        parent::initTypes();

        $this->_int('id', 'internalId');
        $this->_bool('draft');

        $this->mergedAt = new \GMFramework\Date();

        $this->addAlias('web_url', 'url');
        $this->addAlias('iid', 'internalId');
        $this->addAlias('source_branch', 'sourceBranch');
        $this->addAlias('target_branch', 'targetBranch');
        $this->addAlias('source_project_id', 'sourceProjectId');
        $this->addAlias('target_project_id', 'targetProjectId');
        $this->addAlias('merged_at', 'mergedAt');
        $this->addAlias('merge_commit_sha', 'mergeCommitSha');
        $this->addAlias('squash_commit_sha', 'squashCommitSha');
    }

    public function isOpened()
    {
        return $this->state === self::STATE_OPENED;
    }

    public function isMerged()
    {
        return $this->state === self::STATE_MERGED;
    }

    public function isClosed()
    {
        return $this->state === self::STATE_CLOSED;
    }

    public function isDraft()
    {
        return $this->isOpened() && $this->draft;
    }

    /**
     * Возвращает коммит, которым изменения MR попали в целевую ветку.
     *
     * Именно для него GitLab запускает сборку целевой ветки. При влитии
     * перемоткой (fast-forward) мерж-коммита нет, и целевая ветка встаёт
     * на последний коммит исходной - его и возвращаем.
     *
     * @return string SHA коммита или пустая строка, если определить его
     * не удалось.
     */
    public function getMergedCommitSha()
    {
        foreach ([$this->mergeCommitSha, $this->squashCommitSha, $this->sha] as $sha) {
            if (!empty($sha)) {
                return $sha;
            }
        }

        return '';
    }
}
