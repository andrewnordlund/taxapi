<?php
// All this from https://code.tutsplus.com/how-to-build-a-simple-rest-api-in-php--cms-37000t
require __DIR__ . "/inc/bootstrap.php";
$logging=!true;
$version = "1.0.0
";

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
if (isset($uri[3])) {
	$amt = null;
	$year = date("Y");
	$prov = null;
	
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
		} elseif (preg_match("/^\\$?([\d,]+(\.\d\d?)?)$/", $uri[$i], $amnt)) {
			$amt = str_replace(",", "", $amnt[1]);
			if ($logging) print "Setting \$amt to $amt.<br>\n";
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
		calculate($amt, $prov, $logging);
	} else {
		if ($logging) print "Not calculating amounts because \$amt is $amt.<br>\n";
	}

//if ((isset($uri[3]) && $uri[3] != 'user') || !isset($uri[4])) {
} else {
	if ($logging) print "Gonna show about page instead.<br>\n";
	/*
	if (preg_match("/info/", $uri[3])) {
		// do stuff
	} else {
	*/
	//header("HTTP/1.1 404 Not Found");
	//exit();
	//}
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
		calcTaxes($amt, $prov, $logging);
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

function calcTaxes ($amt, $jur, $logging=false) {
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
			if ($logging) print "In tax bracket $i.<Br>\n";
			$bracket = $i+1;
			$mtr = $outData[$jur]["bracket"][$i]["rate"];
			if ($i == 0) {
				$taxPaid = $amt * $outData[$jur]["bracket"][$i]["rate"];
			} else {
				$taxPaid = $outData[$jur]["bracket"][$i-1]["maxTotalTaxPaid"] + ($outData[$jur]["bracket"][$i]["rate"] * ($amt - ($outData[$jur]["bracket"][$i]["from"] + 0.01)));
			}
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
			if ($outData["canada"]["bracket"][$f+1]["from"] < $outData[$prov]["bracket"][$p+1]["from"]) {
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
		$outData[$jur]["bracket"][$i]["maxTaxPaid"] = round($maxTaxPaid, 4);
		$maxTotalTaxPaid = $maxTotalTaxPaid + $maxTaxPaid;
		$outData[$jur]["bracket"][$i]["maxTotalTaxPaid"] = round($maxTotalTaxPaid, 4);
		$outData[$jur]["bracket"][$i]["topNet"] = round($outData[$jur]["bracket"][$i+1]["from"] - $maxTotalTaxPaid, 4);
		if ($jur != "combined") $outData[$jur]["maxBPARefund"] = $outData[$jur]["bpa"] * $outData[$jur]["bracket"][0]["rate"];
	}

} // End of calcTops

?>

