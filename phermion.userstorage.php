<?php

namespace Phermion;


include(__DIR__.'/phermion.php');

//include(__DIR__.'/phermion.sqlite.php');

ini_set('display_errors', 'on');


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


}






$application=new UserStorage();


	$application->addServiceProvider('http://192.168.1.64/project/Phermion/phermion.storage.php');
	$application->initialize('storage');

echo $application->run();
exit();

