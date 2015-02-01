<?php

namespace Phermion;


include(__DIR__.'/phermion.php');

//include(__DIR__.'/phermion.sqlite.php');
//ini_set('display_errors', 'on');


/**
 * Class PhermionStorage
 */

//class NoSQL extends Sqlite
class UserStorage extends \Phermion
{
	public function action_create($login, $password, $email) {
		$object=$this->add('user', json_encode(array(
			'login'=>$login,
			'password'=>$password,
			'email'=>$email
		)));
		return $object;
	}

	public function action_login($login, $password) {

		/*
		$query=array(
			'user'=>array(
				'login'=>$login,
				'password'=>$password
			)
		);
		if($id=$this->find($query)) {
			echo '<pre id="' . __FILE__ . '-' . __LINE__ . '" style="border: solid 1px rgb(255,0,0); background-color:rgb(255,255,255)">';
			print_r($id);
			echo '</pre>';
		}
		*/

		$query="
			SELECT entity.id
			FROM entity
			JOIN attribute login
				ON entity.id=login.id_entity
				AND login.name='login'
				AND login.value='".$login."'

			JOIN attribute password
				ON entity.id=password.id_entity
				AND password.name='password'
				AND password.value='".$password."'
		";


		$data=$this->queryAndFetch($query);

		if(!empty($data)) {
			$id = reset($data)['id'];
			$object = $this->get($id);
			return $object;
		}
		else {
			return false;
		}
	}



}






$application=new UserStorage();


	$application->addServiceProvider('http://192.168.1.64/project/Phermion/phermion.storage.php');
	$application->initialize('storage');

echo $application->run();
exit();

