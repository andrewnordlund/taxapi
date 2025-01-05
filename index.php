<?php
// All this from https://code.tutsplus.com/how-to-build-a-simple-rest-api-in-php--cms-37000t
require __DIR__ . "/inc/bootstrap.php";
$logging=!true;
$version = "1.0.1";

$uri = parse_url($_SERVER['REQUEST_URI']); //, PHP_URL_PATH);

//$uri = explode( '/', $uri );
$uri = explode('/', trim($uri['path'], '/'));
if ($logging)print "uri: " . var_dump($uri) . ".<br>\n";
//print_r($uri);
/* 
   taxapi/{prov}/info?amn=12345
   if info is left blank, just return a JSON of tax info (brackets, personal amount)
   if info is not blank return the above with calculations

 */
$action = "nothing";
$outData = array("version"=>$version); //$taxInfo;
$errData = null;
$prov = null;
$year = date("Y");
if (isset($uri[3])) {
	$amt = null;
	
	for ($i = 2; $i < count($uri); $i++) {
		if ($logging) print "Checking " . $uri[$i] . ".<Br>\n";
		if (preg_match("/^20[1-2]\d$/", $uri[$i])) {
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
				if ($logging) print "It's a province: $prov.<br>\n";
				//break;
			}
		} elseif (preg_match("/^\\$?([\d,]+(\.\d\d?\d?\d?)?)$/", $uri[$i], $amnt)) {
			$amt = str_replace(",", "", $amnt[1]);
			if ($logging) print "Setting \$amt to $amt.<br>\n";
		} elseif (preg_match("/^amt=(\d+(\.\d\d?\d?\?)?)/i", $uri[$i], $amnt)) {
			$amt = $amnt[1];
		}
	}
	$outData["canada"] = $taxInfo["canada"][$year];
	$outData["canada"]["year"] = $year;
	//if (strtolower($uri[3]) == "on") {
	//}
	if ($prov) {
		$outData[$prov] = array();
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
		if ($prov == "on") $outData[$prov]["ohp"] = $taxInfo[$prov]["ohp"];
		$outData["combined"] = array();
	}
	
	$res = null;
	if ($logging) print "Going to calculate for year: $year, province: $prov, and amount: $amt.<br>\n";
	if ($logging) print "Going to calculate tops for Canada.<br>\n";

	$outData["canada"]["maxBPARefund"] = round($outData["canada"]["bpa"] * $outData["canada"]["bracket"][0]["rate"], 4);
	$outData["canada"]["reverse"] = array();
	$outData["canada"]["reverse"]["bpaRefund"] = min(($amt * $outData["canada"]["bracket"][0]["rate"])/(1-$outData["canada"]["bracket"][0]["rate"]), $outData["canada"]["maxBPARefund"]);
	calcTops ("canada");

	if ($prov) {
		$outData[$prov]["maxBPARefund"] = round($outData[$prov]["bpa"] * $outData[$prov]["bracket"][0]["rate"], 4);
		$outData[$prov]["reverse"] = array();
		$outData[$prov]["reverse"]["bpaRefund"] = min(($amt * $outData[$prov]["bracket"][0]["rate"])/(1-$outData[$prov]["bracket"][0]["rate"]), $outData[$prov]["maxBPARefund"]);

		// We have to do this here before we sully the provincial numbers with possible OPH numbers
		$outData["combined"]["maxBPARefund"] = $outData["canada"]["maxBPARefund"] + $outData[$prov]["maxBPARefund"];
		$outData["combined"]["reverse"] = array();
		$outData["combined"]["reverse"]["bpaRefund"] = min(min(($amt * $outData["canada"]["bracket"][0]["rate"])/(1-$outData["canada"]["bracket"][0]["rate"]), $outData["canada"]["maxBPARefund"]) + min(($amt * $outData[$prov]["bracket"][0]["rate"])/(1-$outData[$prov]["bracket"][0]["rate"]), $outData[$prov]["maxBPARefund"]), $outData["combined"]["maxBPARefund"]);

		if ($prov == "on") {
			if ($logging) print "Going to combine Ontario tax brackets with OHP.<Br>\n";
			$combined = combineBrackets($outData[$prov]["ohp"], $outData[$prov]["bracket"], $logging);
			if ($logging) {
				var_dump($combined);
			}
			$outData[$prov]["bracket"] = $combined;
		}
		if ($logging) print "Going to combine brackets for provice $prov.<br>\n";
		$combined = combineBrackets($outData["canada"]["bracket"], $outData[$prov]["bracket"]);
		if ($logging) {
			var_dump($combined);
			print "<br>\nAnd now seeing if it's ontario.<br>\n";
		}
		$outData["combined"]["bracket"] = $combined;
		if ($logging) print "Going to calculate tops for $prov.<br>\n";
		calcTops ($prov);
		if ($logging) print "Going to calculate tops for combined.<br>\n";
		calcTops ("combined");
	}
	if ($logging) print "Done calculating tops.  Now to calculate amounts.<br>\n";
	
	if ($amt) {
		calculate($amt, $prov, $logging);
	} else {
		if ($logging) print "Not calculating amounts because \$amt is $amt.<br>\n";
	}

//if ((isset($uri[3]) && $uri[3] != 'user') || !isset($uri[4])) {
} else {
	if ($logging) print "Gonna show about page instead.<br>\n";
	print getAboutPage();
}
require PROJECT_ROOT_PATH . "/Controller/API/UserController.php";

$objFeedController = new UserController($outData);
if ($errData) $objFeedController->setErrData($errData);
//$strMethodName = $action . 'Action';
//$objFeedController->{$strMethodName}();
$objFeedController->sendResp();

function calculate($amt, $prov=null, $logging=false) {
	global $outData;

	if ($logging) print "Calculating for \$" . $amt . " in $prov.<br>\n";
	// Federal
	calcTaxes($amt, "canada", $logging);
	// Provincial
	if ($prov) {
		if ($prov == "on") $outData[$prov]["OHPremium"] = calcOHPFromGross ($amt, $logging);
		calcTaxes($amt, $prov, $logging);

		calcTaxes($amt, "combined", $logging);
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
	$outData["results"]["gross"] = round($amt, 2);
	$outData["results"]["net"] = $outData["combined"]["net"];

	if (isset($outData["combined"]["premium"])) $outData ["results"]["premium"] = $outData["combined"]["premium"];
	$outData["results"]["subtotalTaxPaid"] = $outData["combined"]["subtotalTaxPaid"];
	$outData["results"]["marginalRate"] = $outData["combined"]["marginalRate"];
	$outData["results"]["averageRate"] = $outData["combined"]["averageRate"];
	$outData["results"]["bpaRefund"] = $outData["combined"]["bpaRefund"];
	$outData["results"]["taxPaid"] = $outData["combined"]["taxPaid"];


	// Canada
	//$outData["canada"]["net"] = round($amt - $outData["canada"]["taxPaid"], 4);
	//$outData["canada"]["netWithBPARefund"] = round($amt - $outData["canada"]["taxPaid"] + $outData["canada"]["bpaRefund"], 4);
	$gross = calcReverse($amt, "canada");
	$outData["canada"]["reverse"]["net"] = round($amt,2);
	$outData["canada"]["reverse"]["gross"] = round($gross, 4);
	$outData["canada"]["reverse"]["taxPaid"] = round($gross - $amt, 4);

	//$rAmt = min($outData["canada"]["taxPaid"], $outData["canada"]["maxBPARefund"]);
	//$outData["canada"]["reverse"]["includingBPA"]["net"] = round($amt - $rAmt, 4);
	
	//$gross = calcReverse($amt-$rAmt, "canada");
	//$outData["canada"]["reverse"]["includingBPA"]["gross"] = round($gross, 4);
	//$outData["canada"]["reverse"]["includingBPA"]["taxPaid"] = round($gross - ($amt - $rAmt), 4);


	if ($prov) {
		// Prov
		//$outData[$prov]["net"] = round($amt - $outData[$prov]["taxPaid"], 4);
		//$outData[$prov]["netWithBPARefund"] = round($amt - $outData[$prov]["taxPaid"] + $outData[$prov]["bpaRefund"], 4);
		$gross = calcReverse($amt, $prov);
		$outData[$prov]["reverse"]["net"] = round($amt, 2);
		$outData[$prov]["reverse"]["gross"] = round($gross, 4);
		$outData[$prov]["reverse"]["taxPaid"] = round($gross - $amt, 4);

		//$rAmt = min($outData[$prov]["taxPaid"], $outData[$prov]["maxBPARefund"]);
		//$outData[$prov]["reverse"]["includingBPA"]["net"] = round($amt - $rAmt, 4);
	
		//$gross = calcReverse($amt-$rAmt, $prov);
		//$outData[$prov]["reverse"]["includingBPA"]["gross"] = round($gross, 4);
		//$outData[$prov]["reverse"]["includingBPA"]["taxPaid"] = round($gross - ($amt - $rAmt), 4);

		
		// Combined
		$gross = calcReverse($amt, "combined");
		$outData["combined"]["reverse"]["net"] = round($amt, 2);
		$outData["combined"]["reverse"]["gross"] = round($gross, 4);
		$outData["combined"]["reverse"]["taxPaid"] = round($gross - $amt, 4);
		$outData["results"]["reverse"]["net"] = round($amt, 2);
		$outData["results"]["reverse"]["gross"] = round($gross, 4);
		$outData["results"]["reverse"]["taxPaid"] = round($gross - $amt, 4);

		// For combined, you have to figure out the total BPA refund
		//$rfAmt = min($outData["canada"]["taxPaid"], $outData["canada"]["maxBPARefund"]);
		//$rpAmt = min($outData[$prov]["taxPaid"], $outData[$prov]["maxBPARefund"]);
		//$rAmt = $rfAmt + $rpAmt;
		//$outData["results"]["reverse"]["includingBPA"]["net"] = round($amt - $rAmt, 4);
	
		//$gross = calcReverse($amt-$rAmt, "combined");
		//$outData["results"]["reverse"]["includingBPA"]["gross"] = round($gross, 4);
		//$outData["results"]["reverse"]["includingBPA"]["taxPaid"] = round($gross - ($amt - $rAmt), 4);
	} else {
		$outData["results"]["reverse"]["net"] = $outData["canada"]["reverse"]["net"];
		$outData["results"]["reverse"]["gross"] = $outData["canada"]["reverse"]["gross"];
		$outData["results"]["reverse"]["taxPaid"] = $outData["canada"]["reverse"]["taxPaid"];

		//$outData["results"]["reverse"]["includingBPA"]["net"] = $outData["canada"]["reverse"]["includingBPA"]["net"];
	
		//$outData["results"]["reverse"]["includingBPA"]["gross"] = $outData["canada"]["reverse"]["includingBPA"]["gross"];
		//$outData["results"]["reverse"]["includingBPA"]["taxPaid"] = $outData["canada"]["reverse"]["includingBPA"]["taxPaid"];
	}

} // End of calculate

function calcTaxes ($amt, $jur, $logging=false) {
	global $outData;
	$taxPaid = 0;
	$bracket = 0;
	$mtr = 0;
	$avgTR = 0;
	$looking = true;
	$prem = null;
	$prevTaxPaid = 0;
	$marginalTaxes = 0;
	$bpar = 0;
	//$logging = true;
	for ($i = count($outData[$jur]["bracket"])-1; $i>=0; $i--) {
		if ($amt > $outData[$jur]["bracket"][$i]["from"]) {
			// it's the one
			if ($logging) print "In tax bracket $i.<Br>\n";
			$bracket = $i+1;
			$mtr = $outData[$jur]["bracket"][$i]["rate"];
			if ($i == 0) {
				$taxPaid = $amt * $outData[$jur]["bracket"][$i]["rate"];
				$marginalTaxes = $taxPaid;
			} else {
				$marginalTaxes = ($outData[$jur]["bracket"][$i]["rate"] * ($amt - ($outData[$jur]["bracket"][$i]["from"] + 0.01)));
				$prevTaxPaid = $outData[$jur]["bracket"][$i-1]["maxTotalTaxPaid"];
				$taxPaid = $marginalTaxes + $prevTaxPaid;
			}
			$subTotalTaxPaid = $taxPaid;
			if (isset($outData[$jur]["bracket"][$i]["premium"])) {
				$prem = $outData[$jur]["bracket"][$i]["premium"];
				$subTotalTaxPaid += $prem;
			}
			break;
		}
	}
	
	$outData[$jur]["taxBracket"] = $bracket;
	$outData[$jur]["marginalTaxes"] = round($marginalTaxes, 4);
	$outData[$jur]["nonMarginalTaxes"] = $prevTaxPaid;
	if ($prem) $outData[$jur]["premium"] = $prem;
	$outData[$jur]["subtotalTaxPaid"] = round($subTotalTaxPaid, 4);
	$outData[$jur]["subtotalTaxPaid"] = round($subTotalTaxPaid, 4);
	$outData[$jur]["marginalRate"] = round($mtr, 5);
	$outData[$jur]["subtotalAverageRate"] = round($subTotalTaxPaid/$amt, 5);
	$outData[$jur]["subtotalNet"] = round($amt - $subTotalTaxPaid, 4);

	if ($jur == "combined") {
		global $prov;
		$bpar = ($outData["canada"]["bpaRefund"] + $outData[$prov]["bpaRefund"]);
	} else {
		$bpar = min($outData[$jur]["bpa"]*$outData[$jur]["bracket"][0]["rate"], $taxPaid);
	}
	$outData[$jur]["bpaRefund"] = $bpar;
	
	
	$taxPaid = $subTotalTaxPaid - $bpar;
	$outData[$jur]["net"] = round($amt - $taxPaid, 4);
	$outData[$jur]["taxPaid"] = round($taxPaid, 4);
	$outData[$jur]["averageRate"] = round($taxPaid/$amt, 5);
	


} // End of calcTaxes


function calcReverse ($amt, $jur) {
	global $outData;
	$prevTaxPaid = 0;
	$revRate = 0;
	$From = 0;
	$bpar = $outData[$jur]["reverse"]["bpaRefund"];
	$prem = null;
	for ($i = count($outData[$jur]["bracket"])-1; $i>=0; $i--) {
		if ($i == 0) {
			$revRate = $outData[$jur]["bracket"][$i]["rate"];
			$From = $outData[$jur]["bracket"][$i]["from"];
			$prevTaxPaid = $outData[$jur]["bracket"][$i-1]["maxTotalTaxPaid"];
			if (isset($outData[$jur]["bracket"][$i]["premium"])) $prem = $outData[$jur]["bracket"][$i]["premium"];
		} else {
			if ($amt > $outData[$jur]["bracket"][$i-1]["topNet"]) {
				// it's the one
				$revRate = $outData[$jur]["bracket"][$i]["rate"];
				$From = $outData[$jur]["bracket"][$i]["from"];
				$prevTaxPaid = $outData[$jur]["bracket"][$i-1]["maxTotalTaxPaid"];
				if (isset($outData[$jur]["bracket"][$i]["premium"])) $prem = $outData[$jur]["bracket"][$i]["premium"];
				break;
			}
		}
	}

	if ($prem) {
		$outData[$jur]["reverse"]["premium"] = $prem;
	} else {
		$prem = 0;
	}
	$gross = ($amt + $prevTaxPaid - ($revRate * $From) - $bpar + $prem)/(1-$revRate);

	return $gross;

} // End of calcReverse

function combineBrackets ($b1, $b2, $logging=false) {
	global $outData;

	if($logging) print "b1 has " . count($b1) . " and b2 has " . count($b2) . " brackets.<br>\n";
	$combo = array();

	$looking = true;
	$looking1 = true;
	$looking2 = true;

	$b1Idx = 0;
	$b2Idx = 0;
	$failsafe = 0;

	$b1Rate = $b1[$b1Idx]["rate"];
	$b2Rate = $b2[$b2Idx]["rate"];

	$from = 0;
	$rt = $b1Rate + $b2Rate;
	$prem = null;
	$hasPrem = false;

	array_push($combo, array("rate"=>$rt, "from"=>$from));
	if (isset($b1[0]["premium"]) || isset($b2[0]["premium"])) {
		$prem = 0;
		$hasPrem=true;
		if (isset($b1[$b1Idx]["premium"])) $prem += $b1[$b1Idx]["premium"];
		if (isset($b2[$b2Idx]["premium"])) $prem += $b2[$b2Idx]["premium"];
		$combo[0]["premium"] = $prem;
	}
	if ($logging) print "Starting....<br>\n";
	do {
		if ($looking1 && $looking2) {
			if ($logging) {
				print "b1Idx: $b1Idx, b2Idx: $b2Idx.<Br>\n";
				print "b1 From: " . $b1[$b1Idx+1]["from"] . ".<br>\n";
				print "b2 From: " . $b2[$b2Idx+1]["from"] . ".<br>\n";
			}
			if ($b1[$b1Idx+1]["from"] < $b2[$b2Idx+1]["from"]) {
				$b1Idx++;
				$from = $b1[$b1Idx]["from"];
				$b1Rate = $b1[$b1Idx]["rate"];
				if (isset($b1[$b1Idx]["premium"])) $prem = $b1[$b1Idx]["premium"];
				if ($b1Idx+1 == count($b1)) {
					$looking1 = false;
				}
			} else {
				$b2Idx++;
				$from = $b2[$b2Idx]["from"];
				$b2Rate = $b2[$b2Idx]["rate"];
				if (isset($b2[$b2Idx]["premium"])) $prem = $b2[$b2Idx]["premium"];
				if ($b2Idx+1 == count($b2)) {
					$looking2 = false;
				}
			}
		} else {
			if ($looking1) {
				$b1Idx++;
				$from = $b1[$b1Idx]["from"];
				$b1Rate = $b1[$b1Idx]["rate"];
				if (isset($b1[$b1Idx]["premium"])) $prem = $b1[$b1Idx]["premium"];
				if ($b1Idx+1 == count($b1)) $looking1 = false;
			} else {
				$b2Idx++;
				$from = $b2[$b2Idx]["from"];
				$b2Rate = $b2[$b2Idx]["rate"];
				if (isset($b2[$b2Idx]["premium"])) $prem = $b2[$b2Idx]["premium"];
				if ($b2Idx+1 == count($b2)) $looking2 = false;
			}
		}
		$rt = $b1Rate + $b2Rate;
		$thisOne = Array("rate"=>round($rt, 5), "from"=>round($from, 4));
		if ($hasPrem) $thisOne["premium"] = $prem;
		array_push($combo, $thisOne);
		if ($b1Idx > count($b1) && $b2Idx > count($b2)) $looking = false;
		if ($looking1 == false && $looking2 == false) $looking = false;
		$failsafe++;
		if ($failsafe > 30) $looking = false;
	} while ($looking);

	return $combo;

} // End of combineBrackets

function calcTops ($jur) {
	global $outData;

	$maxTotalTaxPaid = 0;
	for ($i = 0; $i < count($outData[$jur]["bracket"])-1; $i++) {
		// Calculate maximum taxes in _this_ bracket ((next floor - this floor) * rate)
		$maxTaxPaid = ($outData[$jur]["bracket"][$i+1]["from"] - $outData[$jur]["bracket"][$i]["from"] + 0.01) * $outData[$jur]["bracket"][$i]["rate"];
		// Record that amount in the "copybook".  Hey, shuddup; I'm a COBOL programemr by day.
		$outData[$jur]["bracket"][$i]["maxTaxPaid"] = round($maxTaxPaid, 4);
		// Add that amount to the current maxTotalTaxPaid.
		$maxTotalTaxPaid = $maxTotalTaxPaid + $maxTaxPaid;
		// Record that in the "copybook"
		$outData[$jur]["bracket"][$i]["maxTotalTaxPaid"] = round($maxTotalTaxPaid, 4);


		// Oh no.  I think this is wrong.  You really should incorporate BPA and refund amounts.
		// So, it's the floor of the next bracket minus totalTaxPaid + BPARefund - premium, if there is one
		// First of all, do we have BPARefund right now?
		// Second of all, do we know the premium now?
		$topNet = $outData[$jur]["bracket"][$i+1]["from"] - $maxTotalTaxPaid + min($outData[$jur]["maxBPARefund"], $maxTotalTaxPaid);
		if (isset($outData[$jur]["bracket"][$i]["premium"])) $topNet = $topNet - $outData[$jur]["bracket"][$i]["premium"];
		$outData[$jur]["bracket"][$i]["topNet"] = round($topNet, 4);

	}

} // End of calcTops

function calcOHPFromGross ($amt, $logging) {
	global $outData;

	$prem = 0;
	$rate = 0;
	$tprem = 0;
	$looking = true;

	for ($i = count($outData["on"]["ohp"])-1; $i>=0; $i--) {

		if ($amt > $outData["on"]["ohp"][$i]["from"]) {
			$prem = $outData["on"]["ohp"][$i]["premium"];
			$rate = $outData["on"]["ohp"][$i]["rate"];
			$tprem = ($amt - $outData["on"]["ohp"][$i]["from"] * $rate) + $prem;
			break;

		}

/*
		if ($outData["on"]["ohp"][$i]["premium"] > 1) $prem = $outData["on"]["ohp"][$i]["premium"];
		if ($amt > $outData["on"]["ohp"][$i+1]["from"]) {
		} else {
			if ($outData["on"]["ohp"][$i]["premium"] > 1) {
				// Keep going
			} else {
				$rate = $outData["on"]["ohp"][$i]["premium"];
				$amount = $amt - $outData["on"]["ohp"][$i]["from"];
				$prem = $prem + ($rate * $amount);
			}
			$looking = false;
		}
		*/
	}

	return $prem;

} // End of calcOHPFromGross


function getAboutPage() {
	$outData = "<!DOCTYPE html>
<html lang=\"en\">
	<head>
		<meta charset=\"utf-8\">
		<title>Nordburg Tax API</title>
		<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
		
		<meta name=\"dcterms.title\" content=\"Nordburg Tax API\">\n";
	$outData .= "\t\t<!-- Bootstrap CSS -->
		<link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\" integrity=\"sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH\" crossorigin=\"anonymous\">\n";

	/*$outData .= "\t\t<!-- Font Awesome -->
		<link href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css\" rel=\"stylesheet\" integrity=\"sha384-nuHCjNuZX5ZIwux2wKr/+zvVqeOmBqGNeMzhC/6ps1O38tq1xjnlBxFwt3sE7gBd\" crossorigin=\"anonymous\">\n";
		*/
	
	$outData .= "\t</head>\n\t<body class=\"body\">\n";
	$outData .= "\t\t<header>\n\t\t\t<div class=\"container\">\n\t\t\t\t<div class=\"row\"><h1>Nordburg Tax API</h1></div</div></header>\n";
	$outData .= "\t\t<main>\n";
	$outData .= "\n\t\t\t<div class=\"container\">\n\t\t\t\t<div class=\"row\">\t\t\t<p>The Nordburg Tax API allows you to get Canadian income tax data estimates in JSON so you can see information about tax brakets, marginal and average tax rates, etc.</p>\n";
	$outData .= "\t\t\t<h3>Disclaimer</h3>\n";
	$outData .= "\t\t\t<p>I built this/am building this to learn to make a RESTful API.  It is <em>extremely</em> proof-of-concept.  I'm not 100% clear on how the Basic Personal Amount works, so that part may be <em>way</em> off. Please do not use for anything important!</p>\n";
	$outData .= "\t\t\t<h2>Usage</h2>\n";
	$outData .= "\t\t\t<p>To use this, you need to provide a year (2024 or later), a province code, and a dollar amount.  Example: <code>/taxapi/index.php/2024/on/40000</code> will give you information about income taxes in Ontario for $40&nbsp;000.00 in 2024.  The information it gives includes:</p>\n";
	$outData .= "\t\t\t<ul class=\"ms-4\">\n";
	$outData .= "\t\t\t\t<li>All tax brackets for Canada</li>\n";
	$outData .= "\t\t\t\t<li>All tax brackets for Ontario</li>\n";
	$outData .= "\t\t\t\t<li>Tax paid in each bracket up to the marginal bracket</li>\n";
	$outData .= "\t\t\t\t<li>Total tax paid</li>\n";
	$outData .= "\t\t\t\t<li>Marginal tax rate</li>\n";
	$outData .= "\t\t\t\t<li>Average tax rate</li>\n";
	$outData .= "\t\t\t\t<li>Basic Personal Amount</li>\n";
	$outData .= "\t\t\t\t<li>The net amount</li>\n";
	$outData .= "\t\t\t\t<li>Assuming that the amount provided is the net amount, it calculates what the required gross amount would be.</li>\n";
	$outData .= "\t\t\t</ul>\n";
	$outData .= "\t\t\t<p>See it in action: <a href=\"/taxapi/index.php/2024/on/40000\">/taxapi/index.php/2024/on/40000</a>.</p>\n";
	$outData .= "\t\t\t<details class=\" border border-primary rounded\">\n";
	$outData .= "\t\t\t<summary class=\"text-decoration-underline\" style=\"color: blue\">JSON Output</summary>\n";
	$outData .= "\t\t\t\t<div class=\"mt-3\">\n";
	$outData .= "\t\t\t<code><pre>\n";
	$outData .= "{...
	\"canada\": {
		\"name\":\"Canada\",
		\"bracket\":[
			{
				\"from\":0,
				\"rate\":0.15,
				\"maxTaxPaid\":8381.4015,
				\"maxTotalTaxPaid\":8381.4015,
				\"topNet\":47494.5985
			},
			{
				\"from\":55876,
				\"rate\":0.205,
				\"maxTaxPaid\":11458.8871,
				\"maxTotalTaxPaid\":19840.2886,
				\"topNet\":91932.7115},			
	...
	}
	...
	\"on\":{
		\"name\":\"Ontario\",
		\"bracket\":[
			{
				\"from\":0,
				\"rate\":0.0505,
				\"maxTaxPaid\":2598.0235,
				\"maxTotalTaxPaid\":2598.0235,
				\"topNet\":48847.9765},
			}
	...
	}
	\"results\":{
		\"gross\":40000,
		\"net\":31980,
		\"taxPaid\":8020,
		\"marginalRate\":0.2005,
		\"averageRate\":0.2005,
		\"bpaRefund\":2981.8995,
		\"netWithBPARefund\":34961.8995,
		\"reverse\":{
			\"gross\":50031.2695,
			\"taxPaid\":10031.2695,
			\"includingBPA\":{
				\"net\":37018.1005,
				\"gross\":46301.5641,
				\"taxPaid\":9283.4636
			}
		}
	}
}";
	$outData .= "\t\t\t</pre></code>\n";
	$outData .= "\t\t\t</div>\n";
	$outData .= "\t\t\t</details>\n";
	//$thisPage->addToContent("<p></p>\n");
	//$thisPage->addToContent("<p></p>\n");
	//$thisPage->addToContent("<p></p>\n");
	$outData .= "\t\t\t<h3>Province Codes</h3>\n";
	$outData .= "\t\t\t<ol class=\"ms-4\">\n";
	$outData .= "\t\t\t<li>canada (for just information on Canadian income tax, without provincial data)</li>\n";
	$outData .= "\t\t\t<li>nwfl</li>\n";
	$outData .= "\t\t\t<li>pei</li>\n";
	$outData .= "\t\t\t<li>ns</li>\n";
	$outData .= "\t\t\t<li>nb</li>\n";
	$outData .= "\t\t\t<li>qc</li>\n";
	$outData .= "\t\t\t<li>on</li>\n";
	$outData .= "\t\t\t<li>mn</li>\n";
	$outData .= "\t\t\t<li>sk</li>\n";
	$outData .= "\t\t\t<li>ab</li>\n";
	$outData .= "\t\t\t<li>bc</li>\n";
	$outData .= "\t\t\t<li>nwt</li>\n";
	$outData .= "\t\t\t<li>nu</li>\n";
	$outData .= "\t\t\t<li>yk</li>\n";
	$outData .= "\t\t\t</ul>\n";
	$outData .= "\t\t</div></div></main>\n";
	$outData .= "\t\t<footer class=\"small border-top\">\n";
	$outData .= "\n\t\t\t<div class=\"container\">\n\t\t\t\t<div class=\"row\">\t\t<h2 class=\"visually-hidden\">Footer</h2>\n";
	$outData .= "\t\t<ol>\n";
	$outData .= "\t\t\t<li><a href=\"https://github.com/andrewnordlund/taxapi/\">GitHub Repo</a></li>\n";
	$outData .= "\t\t\t<li>Find an something wrong?  Missing feature? <a href=\"https://github.com/andrewnordlund/taxapi/issues\">Submit an Issue</a>.</li>\n";
	$outData .= "\t\t</ol>\n";
	$outData .= "</div></div>\t\t</footer>\n";
	$outData .= "\t</body>\n";
	$outData .= "</html>\n";

	//$outData
	//$outData = $thisPage->toString();
	//if ($logging) print "Page is now: " . $outData . "<br>\n";
	//$outData = 

	return $outData;

} // End of getAboutPage

?>

