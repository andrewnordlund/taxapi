<?php
// All this from https://code.tutsplus.com/how-to-build-a-simple-rest-api-in-php--cms-37000t
require "inc/bootstrap.php";

$version = "0.1.2";

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$uri = explode( '/', $uri );
//print_r($uri);
/* 
   taxapi/{prov}/info?amn=12345
   if info is left blank, just return a JSON of tax info (brackets, personal amount)
   if info is not blank return the above with calculations

 */
$action = "showAll";
$outData = array("version"=>$version); //$taxInfo;
if (isset($uri[3])) {
	$amt = null;
	$year = date("Y");
	$prov = null;
	
	for ($i = 3; $i < count($uri); $i++) {
		if (preg_match("/^\d\d\d\d$/", $uri[$i])) {
			if (array_key_exists($uri[$i], $taxInfo["canada"])) {
				$year = $uri[$i];
				//break;
			}
	//	}
	//}

	//for ($i = 3; $i < count($uri); $i++) {
		} elseif (preg_match("/^\w{2,3}$/", $uri[$i])) {
			//print "Could be a province: " . $uri[$i] . "<br>\n";
			if (array_key_exists($uri[$i], $taxInfo)) {
				$prov = $uri[$i];
			//	print "It's a province: $prov.<br>\n";
				//break;
			}
		} elseif (preg_match("/^amt=(\d+(\.\d\d?)?)/i", $uri[$i], $amnt)) {
			$amt = $amnt[1];
		}
	}
	//if (strtolower($uri[3]) == "on") {
	//}
	if ($prov) {
		$pyear = $year;
		if (array_key_exists($year, $taxInfo[$prov])) {
			$outData[$prov] = $taxInfo[$prov][$year];
		} elseif (in_array(date("Y"), $taxInfo[$prov])) {
			$outData[$prov] = $taxInfo[$prov][date("Y")];
			$pyear = date("Y");
		} else {
			$pyear = array_key_first($taxInfo[$prov]);
			$outData[$prov] = $taxInfo[$prov][$pyear];
			
		}
		$outData[$prov]["year"] = $pyear;
	}
	$outData["canada"] = $taxInfo["canada"][$year];
	$outData["canada"]["year"] = $year;
	
	$res = null;


	if ($amt) {
		calculate($amt, $prov);
	}

//if ((isset($uri[3]) && $uri[3] != 'user') || !isset($uri[4])) {
} else {
	/*
	if (preg_match("/info/", $uri[3])) {
		// do stuff
	} else {
	*/
	//header("HTTP/1.1 404 Not Found");
	//exit();
	//}
	$outData = $taxInfo;
}
require PROJECT_ROOT_PATH . "/Controller/API/UserController.php";

$objFeedController = new UserController($outData);
//$strMethodName = $action . 'Action';
//$objFeedController->{$strMethodName}();
$objFeedController->sendResp();

function calculate($amt, $prov) {
	global $outData;

	//print "Calculating for \$" . $amt . " in $prov.<br>\n";
	// Federal
	$taxPaid = 0;
	$bracket = 0;
	$mtr = 0;
	$avgTR = 0;
	for ($i = 0; $i < count($outData["canada"]["bracket"])-1; $i++) {
		if ($amt > $outData["canada"]["bracket"][$i+1]["from"]) {
			// it's at least the next tax bracket.
			//print "Not in tax bracket $i.<br>\n";
			$taxPaid = $taxPaid + $outData["canada"]["bracket"][$i]["rate"] * ($outData["canada"]["bracket"][$i+1]["from"] - $outData["canada"]["bracket"][$i]["from"]);
		} else {
			// it's this one
			//print "In tax bracket $i.<Br>\n";
			$bracket = $i+1;
			$taxPaid = $taxPaid + $outData["canada"]["bracket"][$i]["rate"] * ($amt - $outData["canada"]["bracket"][$i]["from"]);
			$mtr = $outData["canada"]["bracket"][$i]["rate"];
			
			break;
		}
	}
	$outData["canada"]["taxBracket"] = $bracket;
	$outData["canada"]["taxPaid"] = round($taxPaid, 4);
	$outData["canada"]["marginalRate"] = round($mtr, 5);
	$outData["canada"]["averageRate"] = round($taxPaid/$amt, 5);

	$bpar = min($outData["canada"]["bpa"]*$outData["canada"]["bracket"][0]["rate"], $taxPaid);
	$outData["canada"]["bpaRefund"] = $bpar;

	// Provincial
	$pbracket = 0;
	$ptaxPaid = 0;
	$pmtr = 0;
	$pavgTR = 0;
	if ($prov) {
		for ($i = 0; $i < count($outData[$prov]["bracket"])-1; $i++) {
			if ($amt > $outData[$prov]["bracket"][$i+1]["from"]) {
				// it's at least the next tax bracket.
				$ptaxPaid = $ptaxPaid + $outData[$prov]["bracket"][$i]["rate"] * ($outData[$prov]["bracket"][$i+1]["from"] - $outData[$prov]["bracket"][$i]["from"]);
			} else {
				// it's this one
				$pbracket = $i+1;
				$ptaxPaid = $ptaxPaid + $outData[$prov]["bracket"][$i]["rate"] * ($amt - $outData[$prov]["bracket"][$i]["from"]);
				$pmtr = $outData[$prov]["bracket"][$i]["rate"];
				break;
			}
		}
		$outData[$prov]["taxBracket"] = $pbracket;
		$outData[$prov]["taxPaid"] = round($ptaxPaid, 4);
		$outData[$prov]["marginalRate"] = round($pmtr, 5);
		$outData[$prov]["averateRate"] = round($ptaxPaid / $amt, 5);

		$pbpar = min($outData[$prov]["bpa"]*$outData[$prov]["bracket"][0]["rate"], $ptaxPaid);
		$outData[$prov]["bpaRefund"] = $pbpar;

	}

	$ttaxPaid = $ptaxPaid + $taxPaid;
	$outData["results"] = array();
	$outData["results"]["gross"] = intval($amt);
	$outData["results"]["net"] = round($amt - $ttaxPaid, 4);
	$outData["results"]["taxPaid"] = round($ttaxPaid, 4);
	$outData["results"]["marginalRate"] = round($mtr + $pmtr, 5);
	$outData["results"]["averageRate"] = round($ttaxPaid / $amt, 5);
	$outData["results"]["bpaRefund"] = $bpar + $pbpar;
	$outData["results"]["netWithBPARefund"] = round($amt - $ttaxPaid + $bpar + $pbpar);
} // End of calculate


?>

