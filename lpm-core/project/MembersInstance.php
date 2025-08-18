<?php
abstract class MembersInstance extends LPMBaseObject
{
    protected $_members = null;
    
    public function getMembers($onlyNotLocked = false)
    {
        if ($this->_members === null && !$this->loadMembers()) {
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
     * @return bool
     */
    public function hasMembers()
    {
        return !empty($this->getMembers());
    }

    /**
     * Добавляет участника в список.
     *
     * Не записывает в БД.
     *
     * Если список членов не определен, то он будет загружен.
     * @param Member $member
     */
    public function addMember(Member $member)
    {
        if ($this->_members != null || $this->loadMembers()) {
            $this->_members[] = $member;
        } else {
            throw new Exception('Не удалось добавить члена');
        }
    }
    
    abstract protected function loadMembers();
}
