<?php
/**
 * Данные для IssueComment с типом IssueCommentType::CREATE_BRANCH.
 */
class IssueCommentCreateBranchData
{
    public static function serialize($repositoryId, $branchName): string {
        return serialize([$repositoryId, $branchName]);
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