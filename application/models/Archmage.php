<?php

class Application_Model_Archmage
{
	protected $_id;
	protected $_user_id;
	protected $_magic_school_id;
	protected $_app_username;
	protected $_app_password;
	protected $_net_power;
	protected $_action_points;
	protected $_total_gp;
	protected $_total_mp;
	protected $_total_pop;
	protected $_fame;
	protected $_protected_until;
	protected $_date_created;
	protected $_last_login;

	public function __construct(array $options = null){
		if (is_array($options)) {
			$this->setOptions($options);
		}
	}
	
	public function __set($name, $value)
	{
		$method = 'set' . $name;
		if (('mapper' == $name) || !method_exists($this, $method)) {
			throw new Exception('Invalid archmage property');
		}
		$this->$method($value);
	}
	
	public function __get($name)
	{
		$method = 'get' . $name;
		if(('mapper' == $name) || !method_exists($this, $method)) {
			throw new Exception('Invalid archmage property');
		}
		return $this->$method();
	}
	
	public function setOptions(array $options)
	{
		$methods = get_class_methods($this);
		foreach($options as $key => $value) {
			$method = 'set' . ucfirst($key);
			if(in_array($method, $methods)) {
				$this->$method($value);
			}
		}
		return $this;
	}
		
	public function setUserId($id) { $this->_user_id = (int) $id; return $this; }
	public function getUserId() { return $this->_user_id; }
	
	public function setMagicSchoolId($id) { $this->_magic_school_id = (int) $id; return $this; }
	public function getMagicSchoolId() { return $this->_magic_school_id; }
	
	public function setAppUsername($string) { $this->_app_username = (string) $string; return $this; }
	public function getAppUsername() { return $this->_app_username; }
	
	public function setAppPassword($string) { $this->_app_password = (string) $string; return $this; }
	public function getAppPassword() { return $this->_app_password; }

	public function setNetPower($id) { $this->_net_power = (int) $id; return $this; }
	public function getNetPower() { return $this->_net_power; }
	
	public function setActionPoints($id) { $this->_action_points= (int) $id; return $this; }
	public function getActionPoints() { return $this->_action_points; }
	
	public function setTotalGP($id) { $this->_total_gp = (int) $id; return $this; }
	public function getTotalGP() { return $this->_total_gp; }
	
	public function setTotalMP($id) { $this->_total_mp = (int) $id; return $this; }
	public function getTotalMP() { return $this->_total_mp; }
	
	public function setTotalPop($id) { $this->_total_pop = (int) $id; return $this; }
	public function getTotalPop() { return $this->_total_pop; }
	
	public function setTotalUpkeepGP($id) { $this->_total_upkeep_gp = (int) $id; return $this; }
	public function getTotalUpkeepGP() { return $this->_total_upkeep_gp; }
	
	public function setTotalUpkeepMP($id) { $this->_total_upkeep_mp = (int) $id; return $this; }
	public function getTotalUpkeepMP() { return $this->_total_upkeep_mp; }
	
	public function setTotalUpkeepPop($id) { $this->_total_upkeep_pop = (int) $id; return $this; }
	public function getTotalUpkeepPop() { return $this->_total_upkeep_pop; }
	
	public function setFame($id) { $this->_fame = (int) $id; return $this; }
	public function getFame() { return $this->_fame; }

	public function setProtectedUntil($ts) { $this->_protected_until = $ts; return $this; }
	public function getProtectedUntil() { return $this->_protected_until; }

	public function setCreated($ts) { $this->_date_created = $ts; return $this; }
	public function getCreated() { return $this->_date_created; }
	
	public function setLastLogin($ts) { $this->_last_login = $ts; return $this; }
	public function getLastLogin() { return $this->_last_login; }
	
	public function setId($id) { $this->_id = (int) $id; return $this; }
	public function getId() { return $this->id; }
}

