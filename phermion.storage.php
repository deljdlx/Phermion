<?php

namespace Phermion;


include(__DIR__.'/phermion.php');

//include(__DIR__.'/phermion.sqlite.php');

ini_set('display_errors', 'on');


/**
 * Class PhermionStorage
 */

//class NoSQL extends Sqlite
class Storage extends \Phermion
{

	protected $entityTableName='entity';
	protected $attributeTableName='attribute';


	protected $repositoryName;



	public function action_add($type, $values) {
		return $this->addObject($type, $values);
	}

	public function action_get($id) {
		$object=$this->fetchObject($id);
		return $object;
	}

	public function action_delete($id) {
		return $this->deleteObject($id);
	}

	public function action_getAll() {

		$query="
			SELECT * FROM ".$this->entityTableName." entity
			JOIN ".$this->attributeTableName." attribute
				ON attribute.id_entity=entity.id
		";

		$objects=array();

		$rows=$this->_queryAndFetch($query);
		if(!empty($rows)) {
			foreach ($rows as $row) {
				if (!isset($objects[$row['id_entity']])) {
					$objects[$row['id_entity']] = array(
						'id' => $row['id_entity'],
						'type' => $row['type'],
						'date_creation' => $row['date_creation'],
						'date_modification' => $row['date_modification'],
						'values' => array()
					);
				}
				$objects[$row['id_entity']]['values'][$row['name']] = $row['value'];
			}
		}

		return $objects;

	}



	public function action_update($id, $values) {
		if(($values=json_decode($values, true)) && $id) {
			return $this->updateObject($id, $values);
		}
		else {
			return false;
		}
	}



	protected function deleteObject($id) {
		$object=$this->fetchObject($id);

		$query="DELETE FROM ".$this->entityTableName." WHERE id=".$id;
		$this->_query($query);
		$this->deleteObjectValues($id);

		return $object;
	}

	protected function updateObject($id, $values) {
		$this->deleteObjectValues($id);
		$this->saveObjectValues($id, $values);
		$query="UPDATE ".$this->entityTableName." SET date_modification='".$this->now()."' WHERE id=".$id;
		$this->_query($query);
		return $this->fetchObject($id);
	}



	protected function addObject($type, $values) {
		$now=$this->now();

		$query="
			INSERT INTO ".$this->entityTableName." (
				date_creation,
				date_modification,
				type
			) VALUES (
				'".$now."',
				'".$now."',
				'".$type."'
			)
		";

		$idEntity=$this->_insert($query);



		if($values=json_decode($values, true)) {
			$this->saveObjectValues($idEntity, $values);
		}

		$object=$this->action_get($idEntity);

		return $object;
	}

	protected function deleteObjectValues($idObject) {
		$query="DELETE FROM ".$this->attributeTableName." WHERE id_entity=".$idObject;
		return $this->_query($query);
	}


	protected function saveObjectValues($idObject, $values) {
		foreach ($values as $name=>$value) {
			$query="
				INSERT INTO ".$this->attributeTableName." (
					id_entity,
					name,
					value
				) VALUES (
					'".$idObject."',
					'".$name."',
					'".$value."'
				)
			";
			$this->_query($query);
		}
	}



	protected function fetchObject($id) {
		$query="
			SELECT * FROM ".$this->entityTableName." entity
			JOIN ".$this->attributeTableName." attribute
				ON attribute.id_entity=entity.id
			WHERE entity.id=".$id.";
		";



		$data=$this->_queryAndFetch($query);


		if(!empty($data)) {
			$object = array(
				'id' => $data[0]['id_entity'],
				'type' => $data[0]['type'],
				'date_creation' => $data[0]['date_creation'],
				'date_modification' => $data[0]['date_modification'],
				'values' => array()
			);

			foreach ($data as $value) {
				$object['values'][$value['name']] = $value['value'];
			}
			return $object;
		}
		else {
			return false;
		}
	}



	//==============================================================
	//==============================================================


	public function initialize($repositoryName) {

		$this->repositoryName=$repositoryName;
		$this->initializeDatabase($repositoryName);


		if(!$this->_tableExists('entity')) {
			$this->createEntityTable();
		}

		if(!$this->_tableExists('attribute')) {
			$this->createAttributeTable();
		}
	}

	protected function createEntityTable() {
		$query="
			CREATE TABLE ".$this->entityTableName." (
				id INTEGER PRIMARY KEY   AUTOINCREMENT,
				date_creation TEXT,
				date_modification TEXT,
				type TEXT
			)
		";

		$this->_query($query);

		$indexQuery="CREATE INDEX type ON  ".$this->entityTableName."(type);";
		$this->_query($indexQuery);

		$indexCreation="CREATE INDEX creation ON  ".$this->entityTableName."(date_creation);";
		$this->_query($indexCreation);

		$indexModification="CREATE INDEX modification ON  ".$this->entityTableName."(date_modification);";
		$this->_query($indexModification);
	}

	protected function createAttributeTable() {
		$query="
			CREATE TABLE ".$this->attributeTableName." (
				id INTEGER PRIMARY KEY   AUTOINCREMENT,
				id_entity INTEGER,
				name TEXT,
				value TEXT
			)
		";
		$this->_query($query);

		$indexQuery="CREATE INDEX name ON  ".$this->attributeTableName."(name);";
		$this->_query($indexQuery);

		$indexQuery="CREATE INDEX id_entity ON  ".$this->attributeTableName."(id_entity);";
		$this->_query($indexQuery);

		$indexQuery="CREATE INDEX value ON  ".$this->attributeTableName."(value);";
		$this->_query($indexQuery);
	}


	protected function _tableExists($tableName) {
		return $this->tableExists($tableName, $this->repositoryName);
	}

	protected function _query($query) {
		return $this->query($query, $this->repositoryName);
	}

	protected function _queryAndFetch($query) {
		return $this->queryAndFetch($query, $this->repositoryName);
	}

	protected function _insert($query) {
		return $this->insert($query, $this->repositoryName);
	}



	//==============================================================
	//==============================================================
}






$application=new NoSQL();


$application->addServiceProvider('http://192.168.1.64/project/Phermion/phermion.sqlite.php');

$application->initialize('storage');

echo $application->run();

exit();

