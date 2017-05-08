<?php

include_once("userManager.php");
include_once("fitbit_profileManager.php");

logDebug("----FIT BIT EXCHANGES LOGGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);


function logFitbitExchange($user_id, $request, $response) {
	global $conn;
	logDebug("Logging Fitbit Exchange...");

	$timezone = getUserTimezone($user_id);

	date_default_timezone_set('America/Los_Angeles');
	$today_date = date('Y-m-d H:i:s');
	//$condition = rand(0,1);

	date_default_timezone_set($timezone);
	$local_today_date = date('Y-m-d H:i:s');

	#check if there is error in it
	$isError = 0;
	if (strpos($response, 'error') !== false) {
    	$isError = 1;
	}

	$sql = "INSERT INTO RS_fitbit_exchange(
		user_id,
		date,
		local_date,
		request,
		response,
		isError) VALUES 
			(\"$user_id\",
			\"$today_date\",
			\"$local_today_date\",
			'$request',
			'$response',
			\"$isError\")";

	logDebug("Running save SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("New record created successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}

	return $conn->insert_id;
}

function getLastExchangesForHours($user_id, $hours = 12) {
	date_default_timezone_set('America/Los_Angeles');
	$calls_since = date('Y-m-d H:i:s', strtotime("-".$hours." hours", time()));

	$sql = "SELECT * FROM RS_fitbit_exchange WHERE `user_id`=\"$user_id\" AND `date`>\"$calls_since\" ORDER BY `date` DESC";
	logDebug("Getting a list of fitbit exchanges in last ".$hours." for user: ".$user_id);

	return executeSimpleSelectQuery($sql);
}

function getLastErrorsForHours($user_id, $hours = 12) {
	date_default_timezone_set('America/Los_Angeles');
	$calls_since = date('Y-m-d H:i:s', strtotime("-".$hours." hours", time()));

	$sql = "SELECT * FROM RS_fitbit_exchange WHERE `user_id`=\"$user_id\" AND `date`>\"$calls_since\" AND `isError`=\"1\" ORDER BY `date` DESC";
	logDebug("Getting a list of fitbit exchange errors in last ".$hours." for user: ".$user_id);

	return executeSimpleSelectQuery($sql);
}

function getExchanges($user_id, $limit = 50) {
	$sql = "SELECT * FROM RS_fitbit_exchange WHERE `user_id`=\"$user_id\" ORDER BY `date` DESC LIMIT $limit";
	logDebug("Getting a list of fitbit exchanges for user: ".$user_id);

	return executeSimpleSelectQuery($sql);
}

### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request - action:". $action);


?>