<?php

include_once("userManager.php");
include_once("fitbit_profileManager.php");
include_once("fitbit_exchangeLogger.php");

logDebug("----FITBIT DEVICE MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

$SERVER = 'api.fitbit.com';
$BASIC_URL = 'https://api.fitbit.com/1/user/';

$DEVICES_URL = '/devices';

function addDevicesForUser($user_id, $device_array) {
	global $conn;

	logDebug("Trying to add fitbit devices for user...");

	foreach ($device_array as $n => $values) {
		$device_id = $values['device_id'];
		$battery = $values['battery'];
		$device_version = $values['device_version'];
		$last_sync_time = $values['last_sync_time'];
		$type = $values['type'];

		logDebug("Checking for device id:" . $device_id);
		
		//First check if there is entry for this date
		$sql = "SELECT id FROM RS_fitbit_device WHERE `device_id`=\"$device_id\" AND `user_id`=\"$user_id\"";
		$result = executeSimpleSelectQuery($sql);

		$sql2 = "";
		if (count($result) > 0) {
			$id = $result[0]['id'];
			$sql2 .= "UPDATE RS_fitbit_device SET `battery`=\"$battery\", `last_sync_time`=\"$last_sync_time\" WHERE `id`=\"$id\"";
		} else {
			$sql2 .= "INSERT INTO RS_fitbit_device(
				`user_id`,
				`device_id`,
				`battery`,
				`device_version`,
				`last_sync_time`,
				`type`) VALUES (
					\"$user_id\",
					\"$device_id\",
					\"$battery\",
					\"$device_version\",
					\"$last_sync_time\",
					\"$type\")";
		}

		logDebug("Running SQL: ".$sql2);

		if ($conn->query($sql2) == FALSE) {
			logError("Error: " . $sql2 . "<br>", $conn->error);
		}
	}
}

function getUserDevices($user_id) {
	$sql = "SELECT * FROM RS_fitbit_device WHERE `user_id`=\"$user_id\"";
	logDebug("Getting fitbit id for user ". $user_id." ...");
	
	$result = executeSimpleSelectQuery($sql);

	return $result;
}

function getLastSyncTimeForUser($user_id) {
	$sql = "SELECT max(last_sync_time) as max_sync_time FROM RS_fitbit_device WHERE `user_id`=\"$user_id\"";
	logDebug("Getting fitbit id for user ". $user_id." ...");
	
	$max_sync_time = mktime(0, 0, 0, 1, 1, 2000);
	$result = executeSimpleSelectQuery($sql);
	if (count($result) > 0) {
		$max_sync_time = $result[0]['max_sync_time'];
	}

	return $max_sync_time;
}

function callFitbitAPIForDevices($user_id) {
	global $BASIC_URL, $DEVICES_URL;

	$repeat = 0;
	$device_data_array = [];
	do {
		logDebug("In callFitbitAPIForDevices...");
		//scopes: 1d, 7d, 30d, 1w, 1m, 3m, 6m, 1y, max
		## Get activity steps
	    // url = BASIC_URL + '-' + ACTIVITY_URL
	    // activity = get_API(url, access_token)
	    // logging.info(activity)

	    $url = $BASIC_URL . "-" . $DEVICES_URL . ".json";
	    #$url = "https://api.fitbit.com/1/user/-/activities/tracker/steps/date/today/max.json";
	    logDebug("Url for requesting FITBIT API devices:". $url);

	    $fitbit_id = getUserFitbitID($user_id);
	    logDebug("Got user fitbit id:".$fitbit_id);
	    $access_token = getAccessTokens($fitbit_id)['access_token'];
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

		logDebug("Raw result from Fitbit API request for data:". substr($result,0,20));

		#log exchange
		logDebug("Logging Fitbit exchange in get devices from Fitbit");
		logFitbitExchange($user_id, "Get Devices Data | URL: ".$url.", CONTEXT:".print_r($options, TRUE), $result);

		$assoc = json_decode($result, true);

		if (array_key_exists("errors", $assoc)) {
			$error_list = $assoc["errors"];
			if (count($error_list) == 1) {
				logDebug("Just one error!");
				$error = $error_list[0];
				logDebug("Error type:". $error["errorType"]);
				if ($error["errorType"] == "expired_token") {
					logDebug("Just expired token, refreshing!!!");
					$fitbit_id = getUserFitbitID($user_id);
					logDebug("Got user fitbit id:".$fitbit_id);
					refreshAccessToken($fitbit_id, $user_id);
					$result_response = "expired_token"; //this will not be returned as we will try again
					$repeat = 1;
					continue;
				} elseif (($error["errorType"] == "system") && (strcasecmp($error["message"],"Too Many Requests") == 0) ) {
					logDebug("We are calling API too many times, let's slow down a but!");
					$result_response = "too_many_requests";
				} else {
					logError("We are in trouble, unhandled error type when getting FITIBIT devices: ".$error["errorType"]."!",69);
				}
			} else {
				logError("We are in trouble, more than one error when requesting FITBIT devices!",69);
			}
		} else {
			//Don't repeat as we got data this time!
			$repeat = 0;
			LogDebug("Result seems devices info, decomposing the return JSON:");
			foreach ($assoc as $n => $device_params) {
				$battery = $device_params['battery'];
				$device_version = $device_params['deviceVersion'];
				$device_id = $device_params['id'];
				$last_sync_time = date("Y-m-d H:i:s", strtotime($device_params['lastSyncTime']));
				$type = $device_params['type'];

				logDebug("Device details:".$n."-> ID:".$device_id.", Type:".$type.", Version:".$device_version.", Battery:".$battery.", LastSync:".$last_sync_time);
				
				$device_data_array[] = [
					"device_id" => $device_id, 
					"battery" => $battery, 
					"device_version" => $device_version,
					"last_sync_time" => $last_sync_time,
					"type" => $type
				];
			}
			$result_response = "no_problems";
		}
	} while ($repeat>0);

	return ["data" => $device_data_array, "result_response" => $result_response];
}

### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request in fitbit_deviceManager - action:". $action);

if ($action != NULL && $action == "callFitbitAPIForDevices") {
	logDebug("Calling Fitbit API for Devices - FITBIT DEVICE MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;

	logDebug("Adding device data for ".$user_id."...");

	$result = callFitbitAPIForDevices($user_id);
	if (count($result['data']) > 0) {
		logDebug("Length of data to add: ". count($result['data']));

		addDevicesForUser($user_id, $result['data']);
	}

	print('{"success": "OK"}');
}

?>