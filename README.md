#Phermion the one file php framework

#######registerAction($actionName, $callback, $callMethod=null, $httpMethod=null)
Exemple

$application=new Phermion();
$application->registerAction(
	'hello',
	function($firname, $lastName='yolo') {
		return 'Hello '.$firname.' '.$lastName;
	}
	,'http'
	,'GET'
);
