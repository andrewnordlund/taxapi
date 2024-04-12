<?php
class UserController extends BaseController {
	private $outData = null;
	private $errData = null;
	private $logging = false;
	private $doIt = true;

	public function __construct ($od=null, $logging=false, $doIt=true) {
		if ($od) $this->setOutData($od);
		if ($logging) $this->setLogging($logging);
		if ($doIt) $this->setDoIt($doIt);
	}
	/**
	* "/user/list" Endpoint - Get list of users
	*/
	public function listAction () {
		$strErrorDesc = '';
		//$strErrorHeader = '';
		$requestMethod = $_SERVER["REQUEST_METHOD"];
		$arrQueryStringParams = $this->getQueryStringParams();
		$arrUsers = array();
		$responseDate = json_encode($arrUsers);
		if (strtoupper($requestMethod) == 'GET') {
			try {
				/*
				$userModel = new UserModel();
				$intLimit = 10;
				if (isset($arrQueryStringParams['limit']) && $arrQueryStringParams['limit']) {
					$intLimit = $arrQueryStringParams['limit'];
				}
				$arrUsers = $userModel->getUsers($intLimit);
				*/
				$arrUsers = array("1" => "John", "2" => "Frank");
				$responseData = json_encode($arrUsers);
			}
			catch (Error $e) {
				$strErrorDesc = $e->getMessage().'Something went wrong! Please contact support.';
				$strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
			}
		} else {
			$strErrorDesc = 'Method not supported';
			$strErrorHeader = 'HTTP/1.1 422 Unprocessable Entity';
		}
		// send output
		if (!$strErrorDesc) {
			$this->sendOutput(
				$responseData,
				array('Content-Type: application/json', 'HTTP/1.1 200 OK')
			);
		} else {
			$this->sendOutput(json_encode(array('error' => $strErrorDesc)), 
				array('Content-Type: application/json', $strErrorHeader)
			);
		}
	} // End of listAction
	public function showAllAction () {
		//print "taxInfo: ";
		//print_r($taxInfo);
		//print ".<br>\n";
	//	$this->sendOutput(json_encode($taxInfo), array('Content-Type: application/json', 'HTTP/1.1 200 OK'));
	} // End of showAllAction

	public function sendResp () {
		if ($this->errData) {
			$this->sendOutput(json_encode(array('error' => $this->errData)), 
				array('Content-Type: application/json', $strErrorHeader)
			);
		} else {
			$this->sendOutput(
				json_encode($this->outData),
				array('Content-Type: application/json', 'HTTP/1.1 200 OK')
			);
		}
	} // End of sendResp

	public function setOutData($od) {
		if (is_array($od)) {
			$this->outData = $od;
		}
	} // End of setOutData

	public function setErrData ($d) {
		if (is_string($d)) $this->errData = $d;
	} // End of setErrData

	public function setLogging($l) {
		if ($l === true || preg_match("/true/i", $l) || (is_numeric($l) && $l != 0)) {
			$this->logging= true;
		} else {
			$this->logging = false;
		}
	} // End of setLogging

	public function setDoIt($l) {
		if (!($l === true || preg_match("/true/i", $l) || (is_numeric($l) && $l != 0))) {
			$this->doIt = false;
		} else {
			$this->doIt = true;
		}
	} // End of setDoIt
} // End of UserController

