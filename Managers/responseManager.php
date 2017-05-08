<?php

include_once("userManager.php");
include_once("studyManager.php");

logDebug("----RESPONSE MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

function addUserResponse($user_id, $text) {
	global $conn;
	logDebug("Logging user (".$user_id.") response...");

	$timezone = getUserTimezone($user_id);

	date_default_timezone_set('America/Los_Angeles');
	$today_date = date('Y-m-d H:i:s');

	date_default_timezone_set($timezone);
	$local_today_date = date('Y-m-d H:i:s');

	//Clear the raw user response frompotentially dangerous characters
	$clean_text = str_replace("\"", "", $text);
	$clean_text = str_replace("'", "", $clean_text);

	//Get the appropriate log id
	$log_id = getLogIDForResponseDate($user_id, $local_today_date);
	logDebug("Got study log id:".$log_id);

	$sql = "INSERT INTO RS_response(
		user_id,
		log_id,
		date,
		local_date,
		text) VALUES 
			(\"$user_id\",
			\"$log_id\",
			\"$today_date\",
			\"$local_today_date\",
			\"$clean_text\")";

	logDebug("Running save SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("New record created successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}

	return $conn->insert_id;
}

function getAllUserResponses($user_id, $limit = 100) {
	$sql = "SELECT * FROM RS_response WHERE `user_id`=\"$user_id\" ORDER BY `date` DESC LIMIT $limit";
	logDebug("Getting a list of fitbit exchanges for user: ".$user_id);

	return executeSimpleSelectQuery($sql);	
}

function getUserResponsesForLog($user_id, $log_id, $limit=50) {
	$sql = "SELECT * FROM RS_response WHERE `user_id`=\"$user_id\" AND `log_id`=\"$log_id\" ORDER BY `date` DESC LIMIT $limit";
	logDebug("Getting a list of fitbit exchanges for user: ".$user_id);

	return executeSimpleSelectQuery($sql);	
}

?>