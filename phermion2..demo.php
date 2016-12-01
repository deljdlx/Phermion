<?php


namespace Phermion;


Trait Routing
{
	protected $routes=array();

	public function addRoute($mode, $validator, $callback) {
		$route=new Route();
	}
}

//=============================================================================================


class Request
{

	const MODE_HTTP='http';
	const MODE_CLI='cli';


	protected $mode;
	protected $query;
	protected $method;


	protected $parameters=array();
	protected $postParameters=null;
	protected $getParameters=null;


	protected $states;


	public function __construct($mode=null, array $parameters=null) {
		if($mode==null) {
			$mode=php_sapi_name();
			if($mode=='cli') {
				$this->setMode('cli');
			}
			else if(isset($_SERVER['REQUEST_URI'])) {
				$this->setMode('http');
			}
		}

		if($this->getMode()==static::MODE_HTTP && $parameters===null) {

			$this->query=$_SERVER['REQUEST_URI'];

			$this->parameters=array_merge($this->parameters, $_GET);
			$this->parameters=array_merge($this->parameters, $_POST);

			$this->postParameters=$_GET;
			$this->postParameters=$_POST;
		}
		else {
			$this->setParameters($this->parameters);
		}
	}

	public function getQuery() {
		return $this->query;
	}


	public function setParameters(array $parameters) {
		$this->parameters=$parameters;
		return $this;
	}

	public function setParameter($parameterName, $value) {
		$this->parameters[$parameterName]=$value;
		return $this;
	}

	public function getParameter($parameterName) {
		if(isset($this->parameters[$parameterName])) {
			return $this->parameters[$parameterName];
		}
		return null;
	}



	public function getParameters() {
		return $this->parameters;
	}

	public function setMode($mode) {
		$this->mode=$mode;
		return $this;
	}

	public function getMode() {
		return $this->mode;
	}


	public function addState(Request $request, $name=null) {

		if($name===null) {
			$this->states[]=$request;
		}
		else {
			$this->states[$name]=$request;
		}
		return $this;
	}


}

//=============================================================================================


class Route
{
	protected $validator;
	protected $mode;
	protected $callback;

	public function __construct($mode, $validator, $callback=null) {
		$this->mode=$mode;
		$this->validator=$validator;
		$this->callback=$callback;
	}


	public function validate(Request $request) {

		if(!$this->mode) {
			return false;
		}

		if(is_array($this->mode)) {
			return $this->validateMultipleMode($request);
		}
		else {
			if($this->mode==$request->getMode()) {
				return $this->executeValidation($request);
			}
		}
		return false;
	}


	protected function validateMultipleMode(Request $request) {
		$mode=$request->getMode();
		if(in_array($mode, $this->mode)) {
			return $this->executeValidation($request);
		}
		return false;
	}


	protected function executeValidation(Request $request) {
		if($this->validator) {
			if(is_callable($this->validator)) {
				return $this->validateByCall($request);
			}
			else if(is_bool($this->validator)) {
				if($this->validator===true) {
					return true;
				}
			}
			else {
				return $this->validateByRegexp($request);
			}
		}

		return false;
	}


	protected function validateByCall(Request $request) {
		return call_user_func_array($this->validator, array($request));
	}


	protected function validateByRegexp(Request $request) {

		$matches=array();


		if(preg_match_all($this->validator, $request->getQuery(), $matches)) {


			array_shift($matches);
			$forgedRequest=clone $request;
			foreach ($matches as $parameterName => $value) {
				$forgedRequest->setParameter($parameterName, $value[0]);
			}
			$request->addState($forgedRequest);

			return true;
		}
		return false;
	}





}



class Response
{

	protected $headers=array();
	protected $content;


}




class Application
{



}


//================================

$request=new Request();

echo $request->getMode();



//================================

$route=new Route('http', function ($request) {
	echo '<pre id="' . __FILE__ . '-' . __LINE__ . '" style="border: solid 1px rgb(255,0,0); background-color:rgb(255,255,255)">';
	echo '<div style="background-color:rgba(100,100,100,1); color: rgba(255,255,255,1)">' . __FILE__ . '@' . __LINE__ . '</div>';
	print_r($request);
	echo '</pre>';

});
$routeValidation=$route->validate($request);



//================================


$route=new Route('http', '`(?<foo>.*)`');
$routeValidation=$route->validate($request);



echo '<pre id="' . __FILE__ . '-' . __LINE__ . '" style="border: solid 1px rgb(255,0,0); background-color:rgb(255,255,255)">';
echo '<div style="background-color:rgba(100,100,100,1); color: rgba(255,255,255,1)">' . __FILE__ . '@' . __LINE__ . '</div>';
print_r($request);
echo '</pre>';

