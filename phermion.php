<?php
/*<container>*/
/*<application>*/
/*<class>*/


class Phermion
{

	protected $actionVariableName='action'; //name of the GET variable which define the action
	protected $hasToBeSaved=false;
	protected $exposeActionName='expose';


	protected $injectedMethods=array();
	protected $packages=array();


	protected $sourceFile;
	protected $source;
	protected $mode; //http|cli|php
	protected $requestMethod;

	protected $requestURI='';
	protected $scriptURL='';

	protected $action;
	protected $actions=array();
	protected $arguments=array();

	protected $headers=array();

	protected $serviceProviders=array();
	protected $foreignServices=null; //array();

	protected $autoExpose=false;
	protected $exposeForeignServices=false;
	protected $exposedServices=array();

	protected $variables=array();

	public function __construct($autorun=true) {

		chdir(__DIR__);
		date_default_timezone_set('Europe/Paris');


		$this->sourceFile=getcwd().'/'.basename(__FILE__);
		$this->source=file_get_contents($this->sourceFile);
		$this->extractParts();
		$this->extractData();



		$mode=php_sapi_name();

		if($mode=='cli') {
			$this->mode='cli';
		}
		else {
			$this->mode='http';
		}
		$this->parseArgument();
	}

	public function run() {

		$returnValue=$this->runRoute();

		if(!is_scalar($returnValue)) {
			$this->addHeader('Content-type', 'application/json; charset="utf-8"');
			$returnValue=json_encode($returnValue);
		}

		$this->sendHeaders();
		return $returnValue;

	}

	public function runRoute() {

		$returnValue=$this->runCustomAction();


		if(!$returnValue) {
			foreach($this->packages as $name=>$package) {
				$result=call_user_func_array(array(
					$package, 'runCustomAction'
				), array());

				if($result) {
					$headers=$package->getHeaders();
					$this->headers=array_merge($this->headers, $headers);
					return $result;
				}
			}
		}

		if(!$returnValue) {
			$returnValue=$this->runDefaultAction();
		}


		if(!$returnValue) {
			foreach($this->packages as $name=>$package) {
				$result=call_user_func_array(array(
					$package, 'runDefaultAction'
				), array());

				if($result) {
					return $result;
				}
			}
		}


		if(!$returnValue) {
			if($this->mode=='http') {
				return $this->action_http_notFound();
			}
			else if($this->mode=='cli') {
				return $this->action_cli_notFound();
			}
		}
		return $returnValue;
	}


	public function getHeaders() {
		return $this->headers;
	}

	protected function sendHeaders() {
		if($this->mode=='http') {
			foreach($this->headers as $descriptor) {
				if($descriptor['name']) {
					header($descriptor['name'].':'.$descriptor['value']);
				}
				else {
					header($descriptor['value']);
				}
			}
		}
	}

	public function addHeader($header, $content) {
		$this->headers[]=array(
			'name'=>$header,
			'value'=>$content
		);
	}


	public function addAction($actionName, $callback, $mode='*', $method='*') {
		$actionName=strtolower($actionName);

		if(!isset($this->actions[$actionName])) {
			$this->actions[$actionName]=array();
		}

		//change closure scope================
		$callback=$callback->bindTo($this, $this);

		$this->actions[$actionName][$mode][$method]=array(
			'callback'=>$callback,
			'mode'=>strtolower($mode),
			'method'=>strtolower($method)
		);
	}

	public function addMethod($methodName, $callback) {
		$this->injectedMethods[$methodName]=$callback;
	}


	public function __call($methodName, $parameters) {
		if(isset($this->injectedMethods[$methodName])) {
			$callback=$this->injectedMethods[$methodName]->bindTo($this, $this);
			return call_user_func_array($callback, $parameters);
		}


		foreach($this->packages as $name=>$package) {
			$result=call_user_func_array(array(
				$package, $methodName
			), $parameters);
			if($result) {
				return $result;
			}
		}


		return $this->callForeignService($methodName, $parameters);

	}



	public function addPackage($package, $name=null) {
		if($name!==null) {
			$this->packages[$name]=$package;
		}
		else {
			$this->packages[]=$package;
		}
	}

/*<actions>*/

	public function action_index() {
		return 'Phermion ok'."\n";
	}
	public function action_http_index() {
		return '<!doctype html><html>
			<head>
				<title>Phermion</title>
				<style>
					html {background-color:#CCC; font-family: arial;}
				</style>
			</head>
			<body>
				<h1>Phermion is running</h1>
			</body><html>'."\n";
	}



	public function action_http_notFound() {
		$this->addHeader(null, 'HTTP/1.0 404 Not Found');
		return '';
	}
	public function action_cli_notFound() {
		return 'Error : action '.$this->action.' not found'."\n";
	}


	public function action_getResource($resourceId='') {
		$data=$this->getFile($resourceId);
		$this->addHeader('Content-type', $data['contentType']);
		return $data['content'];
	}


	public function action_http_getSource() {
		return highlight_string("<?php\n".$this->getPart('class'), true);
	}
	public function action_cli_getSource() {
		return $this->getSource();
	}


	public function action_expose() {

		$this->addHeader('Content-type', 'application/json; encoding="utf-8"');

		if($this->exposeForeignServices) {
			$descriptors=array_merge($this->exposeForeignActions(), $this->exposeMethods(), $this->exposeCustomActions());
		}
		else {
			$descriptors=array_merge($this->exposeMethods(), $this->exposeCustomActions());
		}

		return json_encode($descriptors);
	}

	public function action_version() {
		return '0.8.0';
	}
/*</actions>*/


/*<internal>*/
	protected function exposeForeignActions() {
		$descriptors=array();
		if($this->foreignServices===null) {
			$this->loadForeignServicesDescriptors();
		}

		foreach($this->foreignServices as $descriptor) {
			$descriptors[$descriptor['descriptor']['name']]=$descriptor['descriptor'];
		}
		return $descriptors;
	}

	protected function exposeCustomActions() {
		$descriptors=array();
		foreach($this->actions as $name=>$action) {

			if($this->autoExpose || (isset($this->exposedServices[$name]) && $this->exposedServices[$name])) {
				$reflector=new ReflectionFunction($action['callback']);
				$descriptor=array(
					'name'=>$name,
					'type'=>$action['mode'],
					'method'=>$action['method'],
					'arguments'=>$this->extractParameters($reflector),
				);
				$descriptors[$name]=$descriptor;
			}
		}
		return $descriptors;
	}

	protected function exposeMethods() {

		$descriptors=array();

		$reflector=new \ReflectionClass($this);
		$methods=$reflector->getMethods();
		foreach($methods as $method) {
			if(preg_match('`^action_`i', $method->name)) {

				$methodName=preg_replace('`^action_`','', $method->name);
				$methodName=preg_replace('`^cli_`i', '', $methodName);
				$methodName=preg_replace('`^http_[^_]+_(.*)`i', '$1', $methodName);
				$methodName=preg_replace('`^http_`i', '', $methodName);

				if(!$this->autoExpose && (!isset($this->exposedServices[$methodName]) || !$this->exposedServices[$methodName])) {
					continue;
				}

				$type=false;
				if(preg_match('`^action_http`i', $method->name)) {
					$type='http';
				}
				elseif(preg_match('`^action_http`i', $method->name)) {
					$type='cli';
				}

				$methodCall=false;
				if(preg_match('`^action_http_([^_]+)_[^_]+`i', $method->name)) {
					$methodCall=preg_replace('`action_http_([^_]+)_.*`i', '$1', $method->name);
				}

				$descriptor=array(
					'name'=>$methodName,
					'type'=>$type,
					'method'=>$methodCall,
					'arguments'=>$this->extractParameters($method),
				);
				$descriptors[$methodName]=$descriptor;
			}
		}
		return $descriptors;
	}

	protected function extractParameters($method) {
		$argumentDescriptors=array();

		$arguments=$method->getParameters();
		foreach($arguments as $argument) {
			$defaultValue=null;
			$optional=false;
			if($argument->isOptional()) {
				$optional=true;
				if($argument->isDefaultValueAvailable()) {
					$defaultValue=$argument->getDefaultValue();
				}
			}

			$argumentType=null;
			if($argument->isArray()) {
				$argumentType='array';
			}
			else if($argument->getClass()){
				$argumentType=strtolower($argument->getClass()->name);
			}

			$argumentDescriptors[]=array(
				'name'=>$argument->name,
				'optional'=>$optional,
				'default'=>$defaultValue,
				'type'=>$argumentType,
			);
		}

		return $argumentDescriptors;
	}

	protected function extractParts() {
		preg_match_all('`/\*<([^/]+)>\*/(?R)?|(?:.)/\*</\1>\*/`sU', $this->source, $matches);
		foreach($matches[1] as $tag) {
			preg_match_all('`/\*<'.$tag.'>\*/(.*)/\*</'.$tag.'>\*/`sU', $this->source, $part);
			$this->sourceParts[$tag]=$part[1][0];
		}
	}

	protected function getPart($partName, $withContainer=true) {
		if(isset($this->sourceParts[$partName])) {
			if($withContainer) {
				return '/*'.'<'.$partName.'>*/'."\n".trim($this->sourceParts[$partName])."\n".'/*</'.$partName.'>*/';
			}
			else {
				return trim($this->sourceParts[$partName]);
			}
		}

		return null;
	}

/*</internal>*/

/*<execution>*/

	protected function runCustomAction() {


		$callback=false;
		$returnValue='';

		$actionKey=strtolower($this->action);


		if(isset($this->actions[$actionKey])) {

			if(isset($this->actions[$actionKey][$this->mode][$this->requestMethod])) {
				$callback=$this->actions[$actionKey][$this->mode][$this->requestMethod]['callback'];
			}
			else if(isset($this->actions[$actionKey][$this->mode]['*'])) {
				$callback=$this->actions[$actionKey][$this->mode]['*']['callback'];
			}
			else if(isset($this->actions[$actionKey]['*']['*'])) {
				$callback=$this->actions[$actionKey]['*']['*']['callback'];
			}
		}


		if($callback) {
			$reflector=new \ReflectionFunction($callback);
			$parameters=array();
			foreach($reflector->getParameters() as $index=>$parameter) {
				if(isset($this->arguments[$parameter->name])) {
					$parameters[]=$this->arguments[$parameter->name];
				}
				else {

					if(isset($this->arguments[$index])) {
						$parameters[]=$this->arguments[$index];
					}
					else if($parameter->isOptional()) {
						$parameters[]=$parameter->getDefaultValue();
					}
					else {
						$parameters[]=null;
					}
				}
			}
			return call_user_func_array(
				$callback,
				$parameters
			);
		}
		else {
			return false;
		}
	}

	protected function getPossibleActionNames() {
		return array(
			'action_'.$this->mode.'_'.$this->requestMethod.'_'.$this->action,
			'action_'.$this->mode.'_'.$this->action,
			'action_'.$this->action,
		);
	}

	protected function getExecutableActionName() {
		$methodNames=$this->getPossibleActionNames();
		foreach($methodNames as $methodName) {
			if(method_exists($this, $methodName)) {
				return $methodName;
			}
		}
		return false;
	}


	protected function runDefaultAction() {
		$action=$this->getExecutableActionName();


		if(!$action && $this->action) {
			$data=$this->callForeignService($this->action, $this->arguments);
			return $data;
		}
		else if($action) {

			$reflector=new \Reflectionmethod($this, $action);
			$parameters=array();
			foreach($reflector->getParameters() as $index=>$parameter) {
				if(isset($this->arguments[$parameter->name])) {
					$parameters[]=$this->arguments[$parameter->name];
				}
				else {

					if(isset($this->arguments[$index])) {
						$parameters[]=$this->arguments[$index];
					}
					else if($parameter->isOptional()) {
						$parameters[]=$parameter->getDefaultValue();
					}
					else {
						$parameters[]=null;
					}
				}
			}

			return call_user_func_array(
				array($this, $action),
				$parameters
			);
		}
	}

/*</execution>*/
/*<routing>*/

	protected function parseArgument() {
		if($this->mode!='cli') {
			$this->requestMethod=strtolower($_SERVER['REQUEST_METHOD']);

			$requestURI=str_replace(
				$_SERVER['SCRIPT_NAME'],
				'',
				$_SERVER['REQUEST_URI']
			);


			$this->scriptURL=strtolower(preg_replace('`(.*?)/.*`', '$1', $_SERVER['SERVER_PROTOCOL'])).'://'.$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];


			$this->requestURI=preg_replace('`.*?'.$_SERVER['SERVER_NAME'].'/?`', '', $requestURI);
			$this->requestURI=str_replace('/?', '?', $this->requestURI);




			$this->parseHTTPArguments();
		}
		else {
			$this->parseCliArguments();
		}
	}



	protected function parseHTTPArguments() {

		if(strpos($this->requestURI, '/')!==false) {
			$requestURI=preg_replace('`^/`', '', $this->requestURI);
			$arguments=explode('/', preg_replace('`\?.*`', '', $requestURI));
			$arguments=array_map('urldecode', $arguments);

			if(!$this->action) {
				$this->action=$arguments[0];
				array_shift($arguments);
			}
			$this->arguments=$arguments;
		}
		else if(isset($_GET[$this->actionVariableName])){
			$this->action=$_GET[$this->actionVariableName];
			$this->arguments=$_GET;
		}

		if(!$this->action) {
			$this->action='index';
		}



		if($this->requestMethod=='GET') {
			$this->arguments=array_merge($this->arguments, $_GET);
		}
		else if($this->requestMethod=='POST') {
			$this->arguments=array_merge($this->arguments, $_GET, $_POST);
		}
		else{
			parse_str(file_get_contents("php://input"), $input);
			$this->arguments=array_merge($this->arguments, $_GET, $input);
		}
	}


	protected function parseCliArguments() {
		global $argv;

		$backupArgv=$argv;

		array_shift($backupArgv);

		foreach ($backupArgv as $string) {

			if(strpos($string, '-')===0) {
				$this->parsePhermionArguments($string);
			}
			elseif(strpos($string, '=')) {
				$data=explode("=", $string);
				$this->arguments[$data[0]]=$data[1];
			}
			elseif(!isset($this->action)) {
				$this->action=$string;
			}
			else {
				$this->arguments[]=$string;
			}
		}

		if(!$this->action) {
			$this->action='index';
		}
	}


	protected function parsePhermionArguments($string) {
		$data=explode('=', preg_replace('`^-`', '', $string));

		if(strtolower($data[0])=='mode') {
			$this->mode=strtolower($data[1]);
		}
		else if(strtolower($data[0])=='method'){
			$this->requestMethod=strtolower($data[1]);
		}
	}
/*</routing>*/


/*<storage_methods>*/

	public function storeFile($fileId, $file, $mimeType='') {
		$fileBuffer=file_get_contents($file);
		$this->setVariable($fileId, array(
			'content'=>$fileBuffer,
			'contentType'=>$mimeType
		), 'file');
	}

	public function getFile($fileId) {
		$fileData=$this->getVariable($fileId, 'file', array(
			'content'=>'',
			'contentType'=>''
		));
		return $fileData;
	}

	public function getVariable($variableName, $namespace='', $default=null) {
		if(!$namespace) {
			$namespace='default';
		}
		if(isset($this->variables[$namespace][$variableName])) {
			return $this->variables[$namespace][$variableName];
		}
		else {
			foreach($this->packages as $package) {
				if($data=$package->getVariable($variableName, $namespace, $default)) {
					return $data;
				}
			}
		}
		return $default;
	}


	public function setVariable($variableName, $value, $namespace='') {
		$this->hasToBeSaved=true;
		if(!$namespace) {
			$namespace='default';
		}
		$this->variables[$namespace][$variableName]=$value;
	}

	public function loadData($data) {
		array_walk($data, function(&$value) {
			$value=unserialize(base64_decode($value));
		});
		$this->variables=array_merge($this->variables, $data);
	}


	protected function extractData() {
		$buffer=trim(preg_replace('`/\*(.*)\*/`sU', '$1', $this->getPart('data', false)));

		eval($buffer.';');
		if(isset($data) && is_array($data)) {
			array_walk($data, function(&$value) {
				$value=unserialize(base64_decode($value));
			});
			if(is_array($data)) {
				$this->variables=$data;
			}
		}
	}
/*</storage_methods>*/


/*<application_methods>*/

	public function addServiceProvider($url) {

		$this->serviceProviders[]=$url;
	}

	public function getSource() {
		return $this->source;
	}

	public function save($filepath=null) {
		if($filepath===null) {
			$filepath=__FILE__;
		}

		$values=$this->variables;
		array_walk($values, function(&$value) {
			$value=base64_encode(serialize($value));
		});

		file_put_contents($filepath,
			'<?php'."\n/*<container>*/\n".$this->getPart('application').
			"\n".'/'.'*<'.'data>*//*'."\n".'$data='.var_export($values, true)."\n".'*//*<'.'/data>*/'.
			"\n/*</container>*/\n"
		);
	}

	public function __destruct() {
		if($this->hasToBeSaved) {
			$this->save();
		}
	}

/*</application_methods>*/
/*<service_methods>*/


	public function httpQuery($url, $method='get', $data=array()) {
		$raw=http_build_query($data);

		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\nContent-Length: ".strlen($raw)."\r\n",
				'method'  => strtoupper($method),
				'content' => $raw,
				'request_fulluri' => true
			),
		);
		$context  = stream_context_create($options);
		return file_get_contents($url, false, $context);
	}

	public function autoExpose($value) {
		$this->autoExpose=$value;
	}

	public function exposeForeign($value) {
		$this->exposeForeignServices=$value;
	}

	public function exposeService($action, $value=true) {
		$this->exposedServices[$action]=$value;
	}


	protected function loadForeignServicesDescriptors() {

		$this->foreignServices=false;


		foreach($this->serviceProviders as $provider) {
			$methods=json_decode($this->httpQuery($provider.'?action=expose', 'get', $this->arguments), true);


			if($methods) {
				foreach($methods as $descriptor) {
					$this->foreignServices[strtolower($descriptor['name'])]=array(
						'provider'=>$provider,
						'descriptor'=>$descriptor
					);
				}
			}
		}


	}



	public function callForeignService($methodName, $arguments) {
		if($this->foreignServices===null) {
			$this->loadForeignServicesDescriptors();
		}

		$methodName=strtolower($methodName);
		$data=false;


		if(isset($this->foreignServices[$methodName])) {
			$provider=$this->foreignServices[$methodName]['provider'];
			$data=$this->httpQuery($provider.'?action='.$methodName, $this->requestMethod, $arguments);
		}


		if($object=json_decode($data, true)) {
			return $object;
		}
		else {
			return $data;
		}
	}

/*</service_methods>*/
}
/*</class>*/


/*<custom_code>*/




/*</custom_code>*/
/*</application>*/
/*<data>*//*
*//*</data>*/
/*</container>*/
