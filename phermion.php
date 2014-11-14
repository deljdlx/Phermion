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

	public function __construct() {
	
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


	public function action_http_notFound() {
		$this->addHeader(null, 'HTTP/1.0 404 Not Found');
		return '404 Not found';
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

		if($action) {
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
			$this->requestURI=preg_replace('`.*?'.$_SERVER['SERVER_NAME'].'`', '', $requestURI);
			
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
			$methods=json_decode($this->httpQuery($provider.'/'.$this->exposeActionName(), 'get', $this->arguments), true);
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
			$data=$this->httpQuery($provider.'/'.$methodName, $this->requestMethod, $arguments);
		}
		return $data;
	}
	
/*</service_methods>*/
}
/*</class>*/


/*<custom_code>*/



function getCorePackage() {
	$core=new Phermion();
	$core->addMethod('buildHTML', function($variables=array()) {
		extract($variables);
		return '
			<!doctype html><html>
				<head>
					<link rel="icon" type="image/png" href="'.$scriptURL.'?action=getResource&resourceId=favicon"/>
					<title>Phermion the one file framework</title>
					<style>
						'.$this->getStyle().'
					</style>
				</head>
				<body>
					'.$content.'
				</body>
			</html>
		';
	});


	$core->addAction('addFile', function($file, $mimeType='text/plain') {
		$this->storeFile('test', $file, $mimeType);
		return true;
	});


	$core->addAction('index', function () {
		$buffer="\n\n".'_________________________________________________'."\n";
		$buffer.="  ____  _                         _             
	 |  _ \| |__   ___ _ __ _ __ ___ (_) ___  _ __  
	 | |_) | '_ \ / _ \ '__| '_ ` _ \| |/ _ \| '_ \ 
	 |  __/| | | |  __/ |  | | | | | | | (_) | | | |
	 |_|   |_| |_|\___|_|  |_| |_| |_|_|\___/|_| |_|
	";
		$buffer.='        "php '.basename(__FILE__).' help" for help'."\n";
		$buffer.='_________________________________________________'."\n\n";
			return $buffer;
	}, 'cli');


		
	$core->addAction('index', function() {
		$this->addHeader('Content-type', 'text/html, encoding="utf-8"');
		return $this->buildHTML(array(
			'scriptURL'=>$this->scriptURL,
			'content'=>'
				<span id="forkongithub"><a href="https://github.com/ElBiniou/Phermion">Fork me on GitHub</a></span>
				
				
				<div id="container">
					<img src="'.$this->scriptURL.'?action=getResource&resourceId=phermionLogo" id="logo"/>
					<div id="content">
					<h1>Phermion</h1>
					<p>The one file framework</p>
					<p><a href="?action=getSource">Source</a> | <a href="?action=documentation">Documentation</a></p>
				</div>
			'
		));
	}, 'http');


	$core->addAction('documentation', function() {
		$this->addHeader('Content-type', 'text/html; charset="utf-8"');
		return $this->buildHTML(array(
			'scriptURL'=>$this->scriptURL,
			'content'=>'
				<span id="forkongithub"><a href="https://github.com/ElBiniou/Phermion">Fork me on GitHub</a></span>
				
				
				<div  style="margin-top:30px">
					<img src="'.$this->scriptURL.'?action=getResource&resourceId=phermionLogo" id="logo"/>
					<div id="content">
					<h1>Phermion quick reference</h1>
					<div style="width:900px; margin: auto; padding-top:50px; text-align: left">
						<div>
							<h2>Qu\'est ce que Phermion ?</h2>
							<p>
								Phermion est un micro framework ayant la capacité de fonctionner sans aucune dépendance.  L\'objectif  de ce framwork est d\'offrir la possiblité de créer rapidement des containers de services déployables instantanément. En effet, le code source d\'une application Phermion encapsule le noyaux du framework, le code métier mais aussi les données nécessaires au fonctionnement. En outre Phermion embarque de base des mécanismes permettant d\'étendre rapidement votre code ou même de déléguer des taches à d\'autre noeud Phermion.
							</p>
						</div>
						<div>
							<h2>Votre première application</h2>
							<p>
								Voici comment comment créer une application
								<pre style="background-color:#FFF; padding:10px;">'.highlight_string('<?php
$application=new Phermion();
$application->addAction("sayHello", function($who="John Doe") {
	return "Hello ".$who."\n";
});
echo $application->run();
', true).'</pre>

							</p>
						</div>
						<div>
							<h2>Déployer Phermion</h2>
							<p>
								La façon la plus naïve de déployer une application Phermion et de déposer le fichier d\'application dans un dossier accessible par votre serveur web. "Et voilà" votre application fonctionne !
							</p>
						</div>
						<div>
							<h2>Partager vos services</h2>
							<p>
							
							</p>
						</div>
						<div>
							<h2>Etendre votre application</h2>
							<p>
							
							</p>
						</div>
					</div>
				</div>
			'
		));
	}, 'http');




	$core->addMethod('getStyle', function() {
		return '
			* {margin:0px; padding:0px;}
			html {font-family: arial;	background-color:#666;text-align:center; height:100%;color:#FFF;}
			#container { top:40%; position: absolute; width:100%;}
			h1 {font-size:50px;} a { font-size:12px; color:#FFF; font-weight: bold;}
			#content {margin-top:-152px;}
			
			h2 {
				margin-top:20px;
			}
			
			#forkongithub a{
				background:#000;
				text-decoration:none;
				font-family:arial,sans-serif;
				text-align:center;
				font-weight:bold;
				padding:5px 40px;
				font-size:1rem;
				line-height:2rem;
				position:relative;
				transition:0.5s;
			}
			#forkongithub a:hover{
				background:#26437C;color:#FFF;
			}
			#forkongithub a::before,#forkongithub a::after{
				content:"";
				width:100%;
				display:block;
				position:absolute;
				top:1px;left:0;
				height:1px;
				background:#fff;
			}
			#forkongithub a::after{
				bottom:1px;
				top:auto;
			}
			@media screen and (min-width:800px) {
				#forkongithub{
					position:fixed;display:block;
					top:0;
					right:0;
					width:200px;
					overflow:hidden;
					height:200px;
					z-index:9999;
				}
				#forkongithub a{
					width:200px;position:absolute;
					top:60px;
					right:-60px;
					transform:rotate(45deg);-webkit-transform:rotate(45deg);-ms-transform:rotate(45deg);-moz-transform:rotate(45deg);-o-transform:rotate(45deg);
					box-shadow:4px 4px 10px rgba(0,0,0,0.8);
				}
			}
		';
	});




	$core->addAction('help', function() {
			return "actions : \n"
			."	help		show this help\n"
			."	credits		show Phermion credits\n"
			;
	});

	$core->addAction('credit', function() {
		return "\n****************************************\nPhermion, the one file framework V ".$this->action_version()."\n****************************************\n";
	});

	$core->addAction('credit', function() {
		return "<div>Phermion, the one file framework V ".$this->action_version()."</div>";
	}, 'http');
	
	
	$core->loadData(array (
  'file' => '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',
));
	
	return $core;
}





chdir(__DIR__);

$application=new Phermion();
$application->addPackage(getCorePackage());


$application->addAction("sayHello", function($who="John Doe") {
	return "Hello ".$who."\n";
});



echo $application->run();



return;
/*</custom_code>*/
/*</application>*/
/*<data>*//*
*//*</data>*/
/*</container>*/
