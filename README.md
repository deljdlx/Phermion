#Phermion the one file php framework
By Julien Delsescaux

##Introduction

##Prerequisites
PHP >= 5.4


##Routing

##Actions

##Data storage

##Services providers

##Sharing services




##Methods
######registerAction($actionName, $callback, $callMethod=null, $httpMethod=null)
Add a new action handling to the current application
Exemple
```php
$application=new Phermion();
$application->registerAction(
	'hello',
	function($firname='John', $lastName='doe') {
		return 'Hello '.$firname.' '.$lastName;
	}
	,'http'
	,'GET'
);
```


######registerServiceProvider($url)
```php
$application->registerServiceProvider('http://localhost/___www/phermion/foo.php');
```
######autoExpose($bool)
```php
$application->autoExpose(true);
```
######exposeForeign($bool)
```php
$application->exposeForeign(true);
```


