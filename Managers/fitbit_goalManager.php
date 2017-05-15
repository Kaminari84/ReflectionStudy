<?php

include_once("userManager.php");
include_once("fitbit_profileManager.php");
include_once("fitbit_exchangeLogger.php");

logDebug("----FITBIT DATA MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

$SERVER = 'api.fitbit.com';
$BASIC_URL = 'https://api.fitbit.com/1/user/';

$ACTIVITY_DAILY_GOAL_URL = '/activities/goals/daily.json';
$ACTIVITY_WEEKLY_GOAL_URL = '/activities/goals/weekly.json';

$WEIGHT_GOAL_URL = '/body/log/weight/goal.json';
$FAT_GOAL_URL = '/body/log/fat/goal.json';

$WATER_GOAL_URL = '/foods/log/water/goal.json';
$FOOD_GOAL_URL = '/foods/log/goal.json';

$SLEEP_GOAL_URL = '/sleep/goal.json';

$source_url_mapping = ["activity_daily" => $ACTIVITY_DAILY_GOAL_URL, 
					   "activity_weekly" => $ACTIVITY_WEEKLY_GOAL_URL,
					   "weight" => $WEIGHT_GOAL_URL,
					   "fat" => $FAT_GOAL_URL,
					   "water" => $WATER_GOAL_URL,
					   "food" => $FOOD_GOAL_URL,
					   "sleep" => $SLEEP_GOAL_URL];

function getAvailableGoalSources() {
	global $source_url_mapping;

	return array_keys($source_url_mapping);
}

function getFitbitGoalForUser($user_id, $source = NULL) {
	$sql = "SELECT * FROM RS_fitbit_goal WHERE `user_id`=\"$user_id\"";
	if  ($source != NULL) {
		$sql .= " AND `source`=\"$source\"";
	}

	logDebug("getFitbitGoalForUser " . $sql);
	$result = executeSimpleSelectQuery($sql);

	return $result;
}

function addFitbitGoalForUser($user_id, $source, $json) {
	global $conn;

	logDebug("Trying to add fitbit goal for user...");
		
	//First check if there is entry for this date
	$sql = "SELECT id FROM RS_fitbit_goal WHERE `user_id`=\"$user_id\" AND `source`=\"$source\"";
	$result = executeSimpleSelectQuery($sql);

	$sql2 = "";
	if (count($result) > 0) {
		$id = $result[0]['id'];
		$sql2 .= "UPDATE RS_fitbit_goal SET `json`=\"$json\"";
	} else {
		$sql2 .= "INSERT INTO RS_fitbit_goal(
			`user_id`,
			`source`,
			`json`
			) VALUES (
				\"$user_id\",
				\"$source\",
				'$json')";
	}

	logDebug("Running SQL: ".$sql2);

	if ($conn->query($sql2) == FALSE) {
		logError("Error: " . $sql2 . "<br>", $conn->error);
	}
}

function callFitbitAPIForGoal($user_id, $source) {
	global $source_url_mapping, $BASIC_URL;

	$json = "";
	$result_response = "";
	$repeat = 0;
	$max_repeats = 10;
	do {
		logDebug("In callFitbitAPIForGoal...");

		$spec_url = $source_url_mapping[$source];
	    $url = $BASIC_URL . "-" . $spec_url;
	    logDebug("Url for requesting FITBIT API goals:". $url. ", source:".$source);

	    $fitbit_profile_id = getUserFitbitProfileID($user_id);
	    logDebug("Got fitbit profile id:".$fitbit_profile_id);
	    $fitbit_id = getFitbitID($fitbit_profile_id);
	    logDebug("Got fitbit id:".$fitbit_id);
	    $access_token = getAccessTokens($fitbit_profile_id)['access_token'];
	    logDebug("Access token when accessing FITBIT API data:". $access_token);

		$options = array(
		    'http' => array(
		        'header'  => "Authorization: Bearer ".$access_token."\r\n". 
		        			 "Content-Type: application/x-www-form-urlencoded\r\n",
		        'method'  => 'GET'
		    )
		);

		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);
		logDebug("Response:".$http_response_header[0]);

		logDebug("Raw result from Fitbit API request for goal:". substr($result,0,20));

		#log exchange
		logDebug("Logging Fitbit exchange in get data from Fitbit");
		logFitbitExchange($user_id, "Get Fitbit Goal | URL: ".$url.", CONTEXT:".print_r($options, TRUE), $result);
		$max_repeats = $max_repeats - 1;
		if ($max_repeats < 0) {
			logDebug("ERROR: Reached max repeats, had to stop!");
			break;
		}

		$assoc = json_decode($result, true);

		if (array_key_exists("errors", $assoc)) {
			$error_list = $assoc["errors"];
			if (count($error_list) == 1) {
				logDebug("Just one error!");
				$error = $error_list[0];
				logDebug("Error type:". $error["errorType"]);
				if ($error["errorType"] == "expired_token") {
					logDebug("Just expired token, refreshing!!!");
					$fitbit_profile_id = getUserFitbitProfileID($user_id);
					$fitbit_id = getFitbitID($fitbit_profile_id);
					logDebug("Got user fitbit id:".$fitbit_id);
					$success = refreshAccessToken($fitbit_profile_id, $user_id);
					if ($success == 1) {
						logDebug("Success in refreshing token!");
						$result_response = "expired_token"; //this will not be returned as we will try again
						$repeat = 1;
						continue;
					} else {
						$repeat = 0;
						$result_response = "failed_refreshing_token";
						logDebug("FAILURE in refreshing token!");
						break;
					}
				} elseif (($error["errorType"] == "system") && (strcasecmp($error["message"],"Too Many Requests") == 0) ) {
					logDebug("We are calling API too many times, let's slow down a but!");
					$result_response = "too_many_requests";
				} else {
					logError("We are in trouble, unhandled error type when getting FITIBIT data: ".$error["errorType"]."!",69);
				}
			} else {
				logError("We are in trouble, more than one error when requesting FITBIT data!",69);
			}
		} else {
			$result_response = "no_problems";
			$json = $result;
			
		}
	} while ($repeat>0);

	return ["json" => $json, "result_response" => $result_response];
}

### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request - action:". $action);

if ($action != NULL && $action == "callFitbitAPIForGoal") {
	logDebug("Calling Fitbit API for Goal - FITBIT GOAL MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
	$source = isset($_GET['source']) ? $_GET['source'] : NULL;

	logDebug("Adding fitbit goal for ".$user_id."...");

	if ($source == "all") {
		logDebug("Requested all goal sources...");
		$sources = getAvailableGoalSources();
		logDebug("We have ".count($sources)." goal sources, getting all...");
		foreach ($sources as $key => $value) {
			logDebug("Getting goal for source: ".$value);
			$result = callFitbitAPIForGoal($user_id, $value);
			if (strlen($result['json']) > 0) {
				logDebug("Length of goal to add: ". strlen($result['json']));
				addFitbitGoalForUser($user_id, $value, $result['json']);
			}
		}
	} else {
		$result = callFitbitAPIForGoal($user_id, $source);
		if (strlen($result['json']) > 0) {
			logDebug("Length of goal to add: ". strlen($result['json']));
			addFitbitDataForUser($user_id, $source, $result['json']);
		}
	}

	print('{"success": "OK"}');
}

?>