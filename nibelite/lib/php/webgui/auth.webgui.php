<?php

include_once 'simple.webgui.php'; 

class CMSAuth extends CMS {

	function CMSAuth( $allow_delete = 0 ) {

		$prefix						= 'auth';
		$table						= 'core.ussers';
		$fields = array(
			'id'				=> '0',
			'login'			=> '0',
			'password'	=> '0',
		);

		$this->CMS( $prefix, $table, $fields, $allow_delete );

		$this->tasks['login']				= 'return $this->login();';
		$this->tasks['logout']			= 'return $this->logout();';
		$this->tasks['norights']		= 'return $this->norights();';
		$this->tasks['unauthed']		= 'return $this->unauthed();';
	}
  
	function login() {

		global $status, $USER_ID, $TPL;

		if ($USER_ID) {
			$status = translate('authenticated_ok');
			return template($TPL['auth_welcome'],array('script'=>$_SERVER['SCRIPT_NAME']));
		} else {
			$status = translate('authenticated_error');
			return template($TPL['auth_login_form'],array('script'=>$_SERVER['SCRIPT_NAME']));
		};

	}

  function logout() {

		global $TPL;

    // Logout and clear sessions
    if ($skey = db_escape($_COOKIE['SESSID'])) {
      db("select core.delete_session('$skey')");
      setcookie('SESSID', '', time() - 3600);
    };

    return translate('auth_logged_off');

  }

	function norights() {

		global $TPL;

		return template($TPL['auth_norights'], array(
		));

	}

	function unauthed() {

		global $TPL;

		return template($TPL['auth_unauthed'], array(

		));

	}

} // class CMSAuth

?>
