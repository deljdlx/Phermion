#Phermion the one file php framework

#Introduction


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
