#Phermion the one file php framework
By Jean Luc Biniou

Demo http://www.phermion.com

##Introduction

##Prerequisites
PHP >= 5.4 because of [Closure::bindTo](http://php.net/manual/fr/closure.bindto.php)  usage

##Quick start


##Routing




##Actions

##Data storage

##Saving application

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
$application=new Phermion();
$application->registerServiceProvider('http://somewhere.local/foreignPhermion.php');
```
######autoExpose($bool)

######exposeForeign($bool)



