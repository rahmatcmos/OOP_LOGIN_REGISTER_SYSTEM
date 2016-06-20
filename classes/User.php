<?php
class User {
	private $_db,
			$_data,
			$_sessionName,
			$_cookieName,
			$_isLoggedIn;

	public function __construct($user = null){
		$this->_db = DB::getInstance();
		$this->_sessionName = Config::get('session/session_name');
		$this->_cookieName = Config::get('remember/cookie_name');

		if (!$user) {
			if (Session::exists($this->_sessionName)) {
				$user = Session::get($this->_sessionName);
				if ($this->find($user)) {
					$this->_isLoggedIn = true;
				}
			}
		}else{
			$this->find($user);
		}
	}

	public function create($fields = array()){
		if (!$this->_db->insert('users', $fields)) {
			throw new Exception("Error Processing Register Request");
		}
	}

	public function find($user){
		if ($user){
			$field = (is_numeric($user)) ? 'id' : 'username'; 
			$data = $this->_db->get('users', array($field, "=", $user));
		
			if ($data->count()) {
				$this->_data = $data->first();
				return true;
			}
		}
		return false;
	}

	public function login($username = null, $password = null, $remember = false){
		if (!$username && !$password && $this->exists()){
			Session::put($this->_sessionName, $this->_data->id);
		}else{
			$user  = $this->find($username);		
			if ($user) {
				if ($this->data()->password === Hash::make($password, $this->data()->salt)) {
					Session::put($this->_sessionName, $this->data()->id);
					if ($remember) {
						$hash = Hash::unique();
						$hashCheck = $this->_db->get('users_session', array('user_id', '=', $this->data()->id));
						if (!$hashCheck->count()) {
							$this->_db->insert('users_session', array(
								'user_id' => $this->data()->id,
								'hash'    => $hash
							));
						}else{
							$hash = $hashCheck->first()->hash;
						}
						echo $hash;
						Cookie::put($this->_cookieName, $hash, Config::get('remember/cookie_expiry'));
					}
					return true;
				}
			}
			return false;
		}
	}

	public function update($fields = array(), $id = null){
		if (!$id) {
			$id = $this->data()->id;
		}
		$this->_db->update('users', $id, $fields);
	}

	public function data(){
		return $this->_data;
	}

	public function hasPermission($key){
		$groups = $this->_db->get('groups', array('id', '=', $this->data()->group));
		if ($groups->count()) {
			$permissions = json_decode($groups->first()->permissions, true);
			if ($permissions[$key] == true) {
				return true;
			}
		}
		return false;
	}

	public function exists(){
		return (!empty($this->_data)) ? true : false;
	}

	public function isLoggedIn(){
		return $this->_isLoggedIn;
	}

	public function logout(){
		$this->_db->delete('users_session', array('user_id', '=', $this->data()->id));
		Cookie::delete($this->_cookieName);
		Session::delete($this->_sessionName);
	}

}