<?php

class App_Archmage
{
	protected $_dbTable;
	
	public function setDbTable($dbTable)
	{
		if (is_string($dbTable)) {
			$dbTable = new $dbTable();
		}
		
		if (!$dbTable instanceof Zend_Db_Table_Abstract) {
			throw new Exception('Invalid table data gateway provided');
		}
			
		$this->_dbTable = $dbTable;
	}
	
	public function getDbTable() {
		if (null === $this->_dbTable) {
			$this->setDbTable('Application_Model_DbTable_Archmage');
		}
		return $this->_dbTable;
	}
	
	public function save(Application_Model_Guestbook $guestbook)
	{
		$data = array(
			'email'		=> $guestbook->getEmail(),
			'comment'	=> $guestbook->getComment(),
			'created'	=> date('Y-m-d H:i:s')
		);
		
		if (null === ($id = $guestbook->getId())) {
			unset($data['id']);
			$this->getDbTable()->insert($data);
		} else {
			$this->getDbTable()->update($data, array('id = ?' => $id));
		}
	}
	
	public function find($id, Application_Model_Archmage $archmage)
	{
		$result = $this->getDbTable()->find($id);
		if (0 == count($result)) {
			return;
		}
		$row = $result->current();
		$archmage->setId($row->id)
				  ->setEmail($row->email)
				  ->setComment($row->comment)
				  ->setCreated($row->created);
	}
	
	public function fetchAll()
	{
		$resultSet = $this->getDbTable()->fetchAll();
		$entries = array();
		foreach ($resultSet as $row) {
			$entry = new Application_Model_Archmage();
			$entry->setId($row->id)
				  ->setEmail($row->email)
				  ->setComment($row->comment)
				  ->setCreated($row->created);
			$entries[] = $entry;
		}
		return $entries;
	}

	public function getArchmageInfos($id)
	{
		$row = $this->getDbTable()->fetchRow("id=".$id);

		if (count($row)>0) {
			$infos = array();
			
			$infos['Id']=$row->id;
			$infos['MagicSchoolId']=$row->magic_school_id;
			$infos['NetPower']=$row->net_power;
			$infos['ActionPoints']=$row->action_points;
			$infos['TotalGP']=$row->total_gp;
			$infos['TotalMP']=$row->total_mp;
			$infos['TotalPop']=$row->total_pop;
			$infos['TotalUpkeepGP']=$row->total_upkeep_gp;
			$infos['TotalUpkeepMP']=$row->total_upkeep_mp;
			$infos['TotalUpkeepPop']=$row->total_upkeep_pop;
			$infos['Fame']=$row->fame;
			$infos['ProtectedUntil']=$row->protected_until;
			$infos['DateCreated']=$row->date_created;
			$infos['LastLogin']=$row->last_login;
			$infos['AppUsername']=$row->app_username;
			
			return $infos;
		} else{
			return null;
		}
	}
/*
	public function getArrayArchmageInfos($id)
	{
		$resultSet = $this->getDbTable()->fetchAll();
		$entries = array();
		foreach ($resultSet as $row) {
			$entry = new Application_Model_Archmage();
			$entry->setId($row->id)
				  ->setMagicSchoolId($row->magic_school_id)
				  ->setNetPower($row->net_power)
				  ->setActionPoints($row->action_points);
			$entries[] = $entry;
		}
		return $entries;
	}
*/
}


?>

