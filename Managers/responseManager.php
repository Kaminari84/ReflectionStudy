<?php

include_once("userManager.php");
include_once("studyManager.php");

logDebug("----RESPONSE MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

function addUserResponse($user_id, $text, $intent="") {
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
	$log_entry = getUserDayStudyLog($user_id, -1, true);
	$log_id = $log_entry[0]['id'];
	logDebug("Got study log id:".$log_id);

	$sql = "INSERT INTO RS_response(
		user_id,
		log_id,
		date,
		local_date,
		text,
		intent) VALUES 
			(\"$user_id\",
			\"$log_id\",
			\"$today_date\",
			\"$local_today_date\",
			\"$clean_text\",
			\"$intent\")";

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
	logDebug("Getting a list of responses for user: ".$user_id);

	return executeSimpleSelectQuery($sql);	
}

function getUserResponsesForLog($user_id, $log_id, $limit=50) {
	$sql = "SELECT * FROM RS_response WHERE `user_id`=\"$user_id\" AND `log_id`=\"$log_id\" ORDER BY `date` DESC LIMIT $limit";
	logDebug("Getting responses for log: ".$log_id." for user: ".$user_id);

	return executeSimpleSelectQuery($sql);	
}

function getUserResponsesToMsg($user_id, $log_id) {
	logDebug("Getting user responses to msg for user id: ".$user_id.", log id: ".$log_id);

	$day_study_log_params = getUserDayStudyLogByID($user_id, $log_id);
	$msg_sent_time = $day_study_log_params['msg_sent_time'];
	$followup_sent_time = $day_study_log_params['followup_sent_time'];
	$log_local_date = $day_study_log_params['local_date'];

	logDebug("User message sent time:".$msg_sent_time);
	logDebug("User followup sent time:".$followup_sent_time);
	LogDebug("Log local date:".$log_local_date);
	logDebug("User log id:".$day_study_log_params['id']);

	$user_responses = [];
	if ($msg_sent_time > 0) {
		$edate = $followup_sent_time;
		if ($edate == 0) {
			$edate = date("Y-m-d", strtotime($log_local_date))." 23:59:00"; 
		}

		logDebug("Message has already been sent!");
		$user_responses = getUserResponsesforLogBetween($user_id, $log_id, $msg_sent_time, $edate);	
	}

	return $user_responses;
}

function getUserResponsesToFollowup($user_id, $log_id) {
	logDebug("Getting user responses to follow up for user id: ".$user_id.", log id: ".$log_id);

	$day_study_log_params = getUserDayStudyLogByID($user_id, $log_id);
	$followup_sent_time = $day_study_log_params['followup_sent_time'];
	$log_local_date = $day_study_log_params['local_date'];

	logDebug("User sent time:".$followup_sent_time);
	LogDebug("Log local date:".$log_local_date);
	logDebug("User log id:".$day_study_log_params['id']);

	$user_responses = [];
	if ($followup_sent_time > 0) {
		$edate = date("Y-m-d", strtotime($log_local_date))." 23:59:00"; 

		logDebug("Followup has already been sent!");
		$user_responses = getUserResponsesforLogBetween($user_id, $log_id, $followup_sent_time, $edate);	
	}

	return $user_responses;
}

####INTERNAL####

function getUserResponsesforLogBetween($user_id, $log_id, $start_local_date, $end_local_date) {
	$sql = "SELECT * FROM RS_response WHERE `user_id`=\"$user_id\" AND `log_id`=\"$log_id\" AND `local_date`>\"$start_local_date\" AND `local_date`<=\"$end_local_date\" ORDER BY `date`";
	logDebug("Getting responses for log: ".$log_id." for user: ".$user_id." from: ".$start_local_date." to: ".$end_local_date);

	return executeSimpleSelectQuery($sql);	
}



?>