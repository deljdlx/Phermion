<?php

namespace Phermion;

include(__DIR__.'/phermion.php');


ini_set('display_errors', 'on');


/**
 * Class PhermionStorage
 * @property  \SQLite3 $database
 */

class Sqlite extends \Phermion
{

	protected $database;
	protected $databaseFile;


	public function __construct() {
		parent::__construct();
		$this->initialize();
	}

	//==============================================================
	//==============================================================

	public function action_queryAndFetch($query) {
		return $this->queryAndFetch($query);
	}

	public function action_query($query) {
		return $this->query($query);
	}

	public function action_tableExists($tableName) {
		return $this->tableExists($tableName);
	}


	//==============================================================
	//==============================================================

	protected function initialize() {

		$this->databaseFile=__DIR__.'/storage.sqlite';
		$this->database=new \SQLite3($this->databaseFile);

	}



	protected function dropDatabase() {
		unlink($this->databaseFile);
	}


	protected function tableExists($tableName) {
		$query="
			SELECT name FROM sqlite_master WHERE type='table' AND name='".$tableName."';
		";

		$data=$this->queryAndFetch($query);
		if(!empty($data)) {
			return true;
		}
		else {
			return false;
		}

	}

	protected function getLastInsertId() {
		$query="SELECT last_insert_rowid() as lastInsertId";
		$data=$this->queryAndFetch($query);
		return $data[0]['lastInsertId'];
	}


	protected function escape($string) {
		return $this->database->escapeString($string);
	}

	protected function query($query) {
		return $this->database->query($query);
	}

	protected function queryAndFetch($query) {
		$result=$this->database->query($query);
		$rows=array();
		if($result) {
			while($row=$result->fetchArray(SQLITE3_ASSOC)) {
				$rows[]=$row;
			}
		}
		return $rows;
	}

	protected function now() {
		return date('Y-m-d H:i:s');
	}

}



/*
$application=new Sqlite();
//$application->dropDatabase();
$application->initialize();
echo $application->run();
*/

