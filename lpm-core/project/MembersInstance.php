<?php
abstract class MembersInstance extends LPMBaseObject
{
    protected $_members = null;
    
    public function getMembers($onlyNotLocked = false)
    {
        if ($this->_members == null && !$this->loadMembers()) {
            return array();
        }
        if ($onlyNotLocked) {
            $arr = [];
            foreach ($this->_members as $member) {
                if (!$member->locked) {
                    $arr[] = $member;
                }
            }
            return $arr;
        } else {
            return $this->_members;
        }
    }
    
    public function getMember($userId)
    {
        $members = $this->getMembers();
        foreach ($members as $member) {
            if ($member->userId == $userId) {
                return $member;
            }
        }

        return null;
    }
    
    public function getMemberIds($onlyNotLocked = false)
    {
        $members = $this->getMembers($onlyNotLocked);
        $arr = array();
        foreach ($members as $member) {
            $arr[] = $member->userId;
        }
        return $arr;
    }
    
    public function getMemberIdsStr()
    {
        return implode(',', $this->getMemberIds());
    }

    /**
     * Определяет, есть ли хотя бы один участник.
     */
    public function hasMembers()
    {
        return !empty($this->getMembers());
    }
    
    abstract protected function loadMembers();
}
