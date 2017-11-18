<?php
class MembersInstance extends LPMBaseObject
{
	protected $_members = null;
	
	public function getMembers() {
		if ($this->_members == null && !$this->loadMembers()) 
			return array();
		return $this->_members;
	}
	
	public function getMemberIds() {
		if ($this->_members == null && !$this->loadMembers()) return array();
		$arr = array();
		foreach ($this->_members as $member) array_push( $arr, $member->userId ); 
		return $arr;
	}
	
	public function getMemberIdsStr() {
		return implode( ',', $this->getMemberIds() );
	}
	
	protected function loadMembers() {
		// требует переопределения в наследниках
		return false;
	}
	
}