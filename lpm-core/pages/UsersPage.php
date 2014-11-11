<?php
class UsersPage extends BasePage
{
	function __construct()
	{
		parent::__construct( 'users', 'Пользователи', true, false, 'users' );		
        array_push( $this->_js, 'users' );
	}
}
?>