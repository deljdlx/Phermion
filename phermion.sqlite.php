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
	}

	//==============================================================
	//==============================================================

	public function action_queryAndFetch($query, $repositoryName) {
		$this->initializeDatabase($repositoryName);
		return $this->queryAndFetch($query);
	}

	public function action_query($query, $repositoryName) {
		$this->initializeDatabase($repositoryName);
		return $this->query($query);
	}

	public function action_tableExists($tableName, $repositoryName) {
		$this->initializeDatabase($repositoryName);
		return $this->tableExists($tableName);
	}

	public function action_insert($query, $repositoryName) {
		$this->initializeDatabase($repositoryName);
		$this->query($query);
		return $this->getLastInsertId();
	}


	public function action_initializeDatabase($repositoryName) {
		$this->initializeDatabase($repositoryName);
		return $repositoryName;
	}


	public function action_dropDatabase($repositoryName) {
		$this->initializeDatabase($repositoryName);
		return $this->dropDatabase();
	}


	//==============================================================
	//==============================================================

	protected function initializeDatabase($repositoryName) {


		$this->databaseFile=__DIR__.'/'.$repositoryName.'.sqlite';
		$this->database=new \SQLite3($this->databaseFile);
		return true;
		//return $this->database;
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



$application=new Sqlite();
$application->autoExpose(true);
echo $application->run();
//*/

