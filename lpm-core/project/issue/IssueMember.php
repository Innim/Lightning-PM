<?php
class IssueMember extends Member
{
    public static function deleteInfo($instanceId, $userIds = null)
    {
        $hash = [
            'DELETE' => LPMTables::ISSUE_MEMBER_INFO,
            'WHERE' => [
                '`instanceId` = ' . $instanceId
            ]
        ];

        if ($userIds !== null) {
            $hash['WHERE'][] = '`userId` IN (' . implode(',', $userIds) . ')';
        }

        return self::getDB()->queryb($hash);
    }

    /**
     * Количество SP для этого участника по задаче.
     * Только для Scrum проектов.
     * @var float
     */
    public $sp = 0;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->_typeConverter->addFloatVars('sp');
        $this->addClientFields('sp');
    }
}
