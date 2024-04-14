<?php
// All this from https://code.tutsplus.com/how-to-build-a-simple-rest-api-in-php--cms-37000t
require "inc/bootstrap.php";

$version = "0.0.4";

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
	$outData["canada"] = $taxInfo["canada"][$year];
	$outData["canada"]["year"] = $year;
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
	
	$res = null;
	calcTops ("canada");

	if ($prov) {
		combineBrackets($prov);
		calcTops ($prov);
		calcTops ("combined");
	}
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

function calculate($amt, $prov=null) {
	global $outData;

	//print "Calculating for \$" . $amt . " in $prov.<br>\n";
	// Federal
	calcTaxes($amt, "canada");
	// Provincial
	if ($prov) {
		calcTaxes($amt, $prov);
	}

	$ttaxPaid = $outData["canada"]["taxPaid"];
	$ttr = $outData["canada"]["marginalRate"];
	$tbpar = $outData["canada"]["bpaRefund"];
	if ($prov) {
		$ttaxPaid += $outData[$prov]["taxPaid"];
		$ttr += $outData[$prov]["marginalRate"];
		$tbpar += $outData[$prov]["bpaRefund"];
	}
	$outData["results"] = array();
	$outData["results"]["gross"] = intval($amt);
	$outData["results"]["net"] = round($amt - $ttaxPaid, 4);
	$outData["results"]["taxPaid"] = round($ttaxPaid, 4);
	$outData["results"]["marginalRate"] = round($ttr, 5);
	$outData["results"]["averageRate"] = round($ttaxPaid / $amt, 5);
	$outData["results"]["bpaRefund"] = round($tbpar, 4);
	$outData["results"]["netWithBPARefund"] = round($amt - $ttaxPaid + $tbpar, 4);


	// Canada
	$gross = calcReverse($amt, "canada");
	$outdata["canada"]["reverse"]["net"] = intval($amt);
	$outData["canada"]["reverse"]["gross"] = round($gross, 4);
	$outData["canada"]["reverse"]["taxPaid"] = round($gross - $amt, 4);

	$rAmt = min($outData["canada"]["taxPaid"], $outData["canada"]["maxBPARefund"]);
	$outData["canada"]["reverse"]["includingBPA"]["net"] = round($amt - $rAmt, 4);
	
	$gross = calcReverse($amt-$rAmt, "canada");
	$outData["canada"]["reverse"]["includingBPA"]["gross"] = round($gross, 4);
	$outData["canada"]["reverse"]["includingBPA"]["taxPaid"] = round($gross - ($amt - $rAmt), 4);


	if ($prov) {
		// Prov
		$gross = calcReverse($amt, $prov);
		$outdata[$prov]["reverse"]["net"] = intval($amt);
		$outData[$prov]["reverse"]["gross"] = round($gross, 4);
		$outData[$prov]["reverse"]["taxPaid"] = round($gross - $amt, 4);

		$rAmt = min($outData[$prov]["taxPaid"], $outData[$prov]["maxBPARefund"]);
		$outData[$prov]["reverse"]["includingBPA"]["net"] = round($amt - $rAmt, 4);
	
		$gross = calcReverse($amt-$rAmt, $prov);
		$outData[$prov]["reverse"]["includingBPA"]["gross"] = round($gross, 4);
		$outData[$prov]["reverse"]["includingBPA"]["taxPaid"] = round($gross - ($amt - $rAmt), 4);

		
		// Combined
		$gross = calcReverse($amt, "combined");
		$outdata["results"]["reverse"]["net"] = intval($amt);
		$outData["results"]["reverse"]["gross"] = round($gross, 4);
		$outData["results"]["reverse"]["taxPaid"] = round($gross - $amt, 4);

		// For combined, you have to figure out the total BPA refund
		$rfAmt = min($outData["canada"]["taxPaid"], $outData["canada"]["maxBPARefund"]);
		$rpAmt = min($outData[$prov]["taxPaid"], $outData[$prov]["maxBPARefund"]);
		$rAmt = $rfAmt + $rpAmt;
		$outData["results"]["reverse"]["includingBPA"]["net"] = round($amt - $rAmt, 4);
	
		$gross = calcReverse($amt-$rAmt, "combined");
		$outData["results"]["reverse"]["includingBPA"]["gross"] = round($gross, 4);
		$outData["results"]["reverse"]["includingBPA"]["taxPaid"] = round($gross - ($amt - $rAmt), 4);
	} else {
		$outdata["results"]["reverse"]["net"] = $outdata["canada"]["reverse"]["net"];
		$outData["results"]["reverse"]["gross"] = $outData["canada"]["reverse"]["gross"];
		$outData["results"]["reverse"]["taxPaid"] = $outData["canada"]["reverse"]["taxPaid"];

		$outData["results"]["reverse"]["includingBPA"]["net"] = $outData["canada"]["reverse"]["includingBPA"]["net"];
	
		$outData["results"]["reverse"]["includingBPA"]["gross"] = $outData["canada"]["reverse"]["includingBPA"]["gross"];
		$outData["results"]["reverse"]["includingBPA"]["taxPaid"] = $outData["canada"]["reverse"]["includingBPA"]["taxPaid"];
	}

} // End of calculate

function calcTaxes ($amt, $jur) {
	global $outData;
	$taxPaid = 0;
	$bracket = 0;
	$mtr = 0;
	$avgTR = 0;
	for ($i = 0; $i < count($outData[$jur]["bracket"])-1; $i++) {
		if ($amt > $outData[$jur]["bracket"][$i+1]["from"]) {
			// it's at least the next tax bracket.
		} else {
			// it's this one
			//print "In tax bracket $i.<Br>\n";
			$bracket = $i+1;
			$taxPaid = $outData[$jur]["bracket"][$i]["maxTaxPaid"] + ($outData[$jur]["bracket"][$i]["rate"] * ($amt - ($outData[$jur]["bracket"][$i]["from"] + 0.01)));
			$mtr = $outData[$jur]["bracket"][$i]["rate"];
			
			break;
		}
	}
	$outData[$jur]["taxBracket"] = $bracket;
	$outData[$jur]["taxPaid"] = round($taxPaid, 4);
	$outData[$jur]["marginalRate"] = round($mtr, 5);
	$outData[$jur]["averageRate"] = round($taxPaid/$amt, 5);

	$bpar = min($outData[$jur]["bpa"]*$outData[$jur]["bracket"][0]["rate"], $taxPaid);
	$outData[$jur]["bpaRefund"] = $bpar;

} // End of calcTaxes


function calcReverse ($amt, $jur) {
	global $outData;
	$revTaxPaid = 0;
	$revRate = 0;
	$From = 0;
	for ($i = 0; $i < count($outData[$jur]["bracket"]); $i++) {
		if ($amt > $outData[$jur]["bracket"][$i]["topNet"]) {
			// It's at least the next tax bracket
			$revTaxPaid = $revTaxPaid + $outData[$jur]["bracket"][$i]["maxTaxPaid"];
		} else {
			$revRate = $outData[$jur]["bracket"][$i]["rate"];
			$From = $outData[$jur]["bracket"][$i]["from"];
			break;
		}
	}

	$gross = ($amt + $revTaxPaid - ($revRate * $From))/(1-$revRate);

	return $gross;

} // End of calcReverse

function combineBrackets ($prov) {
	global $outData;

	$combo =array();
	$f = 0;
	$p = 0;
	$looking = true;
	$lookingF = true;
	$lookingP = true;
	//$rt = $outData["canada"]["bracket"][$f]["rate"] + $outData[$prov]["bracket"][$p];

	$frate = $outData["canada"]["bracket"][$f]["rate"];
	$prate = $outData[$prov]["bracket"][$p]["rate"];
	$rt = $frate + $prate;
	$from = 0;
	$failsafe = 0;
	array_push($combo, array("rate"=>$rt, "from"=>$from));
	do {
		
		if ($lookingP && $lookingF) {
			if ($outData["canada"]["bracket"][$f+1] < $outData[$prov]["bracket"][$p+1]) {
				$f++;
				$from = $outData["canada"]["bracket"][$f]["from"];
				$frate = $outData["canada"]["bracket"][$f]["rate"];
				if ($f+1 == count($outData["canada"]["bracket"])) {
					$lookingF = false;
				}
			} else {
				$p++;
				$from = $outData[$prov]["bracket"][$p]["from"];
				$prate = $outData[$prov]["bracket"][$p]["rate"];
				//} else {
				if ($p+1 == count($outData[$prov]["bracket"])) {
					//print "Setting lookingP to false";
					$lookingP = false;
				}
			}
		} else {
			if ($lookingP) {
				// $lookingF must be false.  So you've hit the top fTaxbracket
				$p++;
				$from = $outData[$prov]["bracket"][$p]["from"];
				$prate = $outData[$prov]["bracket"][$p]["rate"];
				if ($p+1 == count($outData[$prov]["bracket"])) {
					$lookingP = false;
				}
			} else {
				if ($lookingF) {
					// $lookingP must be false.  So you've hit the top pTaxBracket
					$f++;
					$from = $outData["canada"]["bracket"][$f]["from"];
					$frate = $outData["canada"]["bracket"][$f]["rate"];
					//} else {
					if ($f+1 == count($outData["canada"]["bracket"])) {
						$lookingF = false;
					}
				}
			}
		}
		$rt = $frate + $prate;
		array_push($combo, array("rate"=>round($rt, 5), "from"=>round($from, 4)));
		
		if ($f >= count($outData["canada"]["bracket"]) && $p >= count($outData[$prov]["bracket"])) $looking = false;
		if ($lookingP == false && $lookingF == false) $looking = false;
		$failsafe++;
		if ($failsafe > 20) $looking = false;
	} while ($looking);

	$outData["combined"]["bracket"] = $combo;

} // End of combineBrackets

function calcTops ($jur) {
	global $outData;

	$maxTotalTaxPaid = 0;
	for ($i = 0; $i < count($outData[$jur]["bracket"])-1; $i++) {
		$maxTaxPaid = ($outData[$jur]["bracket"][$i+1]["from"] - $outData[$jur]["bracket"][$i]["from"] + 0.01) * $outData[$jur]["bracket"][$i]["rate"];
		$outData[$jur]["bracket"][$i]["maxTaxPaid"] = $maxTaxPaid;
		$maxTotalTaxPaid = $maxTotalTaxPaid + $maxTaxPaid;
		$outData[$jur]["bracket"][$i]["maxTotalTaxPaid"] = $maxTotalTaxPaid;
		$outData[$jur]["bracket"][$i]["topNet"] = $outData[$jur]["bracket"][$i+1]["from"] - $maxTotalTaxPaid;
		if ($jur != "combined") $outData[$jur]["maxBPARefund"] = $outData[$jur]["bpa"] * $outData[$jur]["bracket"][0]["rate"];
	}

} // End of calcTops

?>

