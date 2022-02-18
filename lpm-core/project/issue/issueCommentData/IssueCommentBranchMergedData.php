<?php
/**
 * Данные для IssueComment с типом IssueCommentType::BRANCH_MERGED.
 */
class IssueCommentBranchMergedData
{
    public static function serialize($repositoryId, $branchName): string {
        return serialize([$repositoryId, $branchName]);
    }

    public static function serializeBy(IssueBranch $issueBranch): string {
        return self::serialize($issueBranch->repositoryId, $issueBranch->name);
    }

    public $repositoryId;
    public $branchName;

    function __construct(string $data)
    {
        $deserialized = unserialize($data);
        $this->repositoryId = $deserialized[0];
        $this->branchName = $deserialized[1];
    }
}