<?php

$taxFile = __DIR__ . "/../data/taxinfo.json";
$taxInfo = null;
if (file_exists($taxFile)) {
	$taxInfo = file_get_contents($taxFile, true);
	$taxInfo = json_decode($taxInfo, true);
} else {
	print " file not exists.<br>\n";
}
//print_r ($taxInfo); //=>["canada"]=>["2024"]);
?>
