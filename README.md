#Phermion the one file php framework
By Julien Delsescaux

#Introduction

#Prerequisites
PHP >= 5.4


#Routing and actions

#Data storage

#Services providers

#Sharing services




#Methods
#######registerAction($actionName, $callback, $callMethod=null, $httpMethod=null)
Exemple
```php
$application=new Phermion();
$application->registerAction(
	'hello',
	function($firname, $lastName='yolo') {
		return 'Hello '.$firname.' '.$lastName;
	}
	,'http'
	,'GET'
);
```
