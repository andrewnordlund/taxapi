<?php
// All this from https://code.tutsplus.com/how-to-build-a-simple-rest-api-in-php--cms-37000t
require "inc/bootstrap.php";

$version = "0.0.3";

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
	combineBrackets($prov);
	calcTops ($prov);
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
	$outData["results"]["netWithBPARefund"] = round($amt - $ttaxPaid + $bpar + $pbpar, 4);


	// Canada
	// For now, forget about bpa
	$revTaxPaid = 0;
	$revRate = 0;
	for ($i = 0; $i < count($outData["canada"]["bracket"]); $i++) {
		if ($amt >= $outData["canada"]["bracket"][$i]["topNet"]) {
			// it's at least the next tax bracket
			$revTaxPaid = $revTaxPaid + $outData["canada"]["bracket"][$i]["maxTaxPaid"];
		} else {
			$diff = $amt - $outData["canada"]["bracket"][$i]["from"];
			$txp = ($diff / (1 - $outData["canada"]["bracket"][$i]["rate"]) - $diff);
			$revTaxPaid = $revTaxPaid + $txp;
			$revRate = $outData["canada"]["bracket"][$i]["rate"];
			break;
		}
	}
	$outdata["canada"]["reverse"]["net"] = intval($amt);
	$outData["canada"]["reverse"]["taxPaid"] = round($revTaxPaid, 4);
	$outData["canada"]["reverse"]["gross"] = $amt + round($revTaxPaid, 4);

	// Prov
	// For now, forget about bpa
	$prevTaxPaid = 0;
	$prevRate = 0;
	for ($i = 0; $i < count($outData[$prov]["bracket"]); $i++) {
		if ($amt >= $outData[$prov]["bracket"][$i]["topNet"]) {
			// it's at least the next tax bracket
			$prevTaxPaid = $prevTaxPaid + $outData[$prov]["bracket"][$i]["maxTaxPaid"];
		} else {
			$diff = $amt - $outData[$prov]["bracket"][$i]["from"];
			$txp = ($diff / (1 - $outData[$prov]["bracket"][$i]["rate"]) - $diff);
			$prevTaxPaid = $prevTaxPaid + $txp;
			$prevRate = $outData[$prov]["bracket"][$i]["rate"];
			break;
		}
	}
	$outdata[$prov]["reverse"]["net"] = intval($amt);
	$outData[$prov]["reverse"]["taxPaid"] = round($prevTaxPaid, 4);
	$outData[$prov]["reverse"]["gross"] = $amt + round($prevTaxPaid, 4);

	// Combined
	$trevTaxPaid = 0;
	$trevRate = 0;
	$tFrom = 0;
	for ($i = 0; $i < count($outData["combined"]["bracket"]); $i++) {
		if ($amt > $outData["combined"]["bracket"][$i]["topNet"]) {
			// It's at least the next tax bracket
			$trevTaxPaid = $trevTaxPaid + $outData["combined"]["bracket"][$i]["maxTaxPaid"];
		} else {
			// Find out how much tax paid in this bracket.
			//$diff = $amt;
			//if ($i > 0) {
			//	$diff = $amt - $outData["combined"]["bracket"][$i-1]["topNet"];
			//}
			//$diff = $amt - $outData["combined"]["bracket"][$i]["from"];
			//$txp = ($diff / (1-$outData["combined"]["bracket"][$i]["rate"]));
			//$txp = ($diff / (1 - $outData["combined"]["bracket"][$i]["rate"]) - $diff);
			//$trevTaxPaid = $outData["combined"]["bracket"][$i]["maxTotalTaxPaid"];
			$trevRate = $outData["combined"]["bracket"][$i]["rate"];
			$tFrom = $outData["combined"]["bracket"][$i]["from"];
			break;
		}
	}

	$gross = ($amt + $trevTaxPaid - ($trevRate * $tFrom))/(1-$trevRate);

	$outData["results"]["reverse"]["net"] = intval($amt);
	//$outData["results"]["reverse"]["taxPaid"] = round($revTaxPaid + $prevTaxPaid, 4);

	$outData["results"]["reverse"]["taxPaid"] = round($gross - $amt, 4);  //round($trevTaxPaid, 4);
	$outData["results"]["reverse"]["gross"] = round($gross, 4);

} // End of calculate

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
		//$nf = $f+1;
		//$np = $p+1;

		//if ($f+1 >= count($outData["canada"]["bracket"])) $lookingF = false;
		//if ($p+1 >= count($outData[$prov]["bracket"])) $lookingP = false;
		
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

function calcTops ($prov) {
	global $outData;

	$ju = array("canada", $prov, "combined");
	for ($j = 0; $j<count($ju); $j++) {
		$maxTotalTaxPaid = 0;
		for ($i = 0; $i < count($outData[$ju[$j]]["bracket"])-1; $i++) {
			$maxTaxPaid = ($outData[$ju[$j]]["bracket"][$i+1]["from"] - $outData[$ju[$j]]["bracket"][$i]["from"]) * $outData[$ju[$j]]["bracket"][$i]["rate"];
			$outData[$ju[$j]]["bracket"][$i]["maxTaxPaid"] = $maxTaxPaid;
			$maxTotalTaxPaid = $maxTotalTaxPaid + $maxTaxPaid;
			$outData[$ju[$j]]["bracket"][$i]["maxTotalTaxPaid"] = $maxTotalTaxPaid;
			$outData[$ju[$j]]["bracket"][$i]["topNet"] = $outData[$ju[$j]]["bracket"][$i+1]["from"] - $maxTotalTaxPaid;
		}
	}

} // End of calcTops

?>

