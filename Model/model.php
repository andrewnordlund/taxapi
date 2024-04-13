<?php

$taxFile = __DIR__ . "/../data/taxinfo.json";
$taxInfo = null;
try {
	//if (file_exists($taxFile)) {
	$taxInfo = file_get_contents($taxFile, true);
	$taxInfo = json_decode($taxInfo, true);
}
catch (Exception $e) {
	echo $e->getMessage() . "\n";
}
//print_r ($taxInfo); //=>["canada"]=>["2024"]);
?>
