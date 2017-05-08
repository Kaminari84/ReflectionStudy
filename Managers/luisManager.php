<?php

include_once("errorReporting.php");

logDebug("----LUIS MANAGER LOAD----");


function getIntent($url, $text, $return_type="Array") {
	//construct URL
	/*$data = array(	
		'q' => $text, 
	);*/

	$url = $url . urlencode($text); //http_build_query($data);
	logDebug("Constructed URL:".$url);

	//call the chart renderer service
	$result = file_get_contents($url);
	logDebug("Response:".$result);

	$assoc = json_decode($result, true);
	$intent =  $assoc['topScoringIntent']['intent'];
	$score = $assoc['topScoringIntent']['score'];

	$arr = ["intent" => $intent, "score" => $score];

	if (strcasecmp($return_type, "Array") == 0) {
		return $arr;
	} elseif (strcasecmp($return_type, "JSON") == 0) {
		return json_encode($arr);
	}
}

### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request in Study Manager - action:". $action);

if ($action != NULL && $action == "getIntent") {
	logDebug("Trying to make call to LUIS...");

	$url = isset($_GET['url']) ? $_GET['url'] : NULL;
	$text = isset($_GET['text']) ? $_GET['text'] : NULL;

	logDebug("Getting intent for...");
	logDebug("URL:".$url);
	logDebug("Text:".$text);

	logDebug("Making a call...");
	print getIntent($url, $text, "JSON");

	logDebug("Call done");
} 

?>

