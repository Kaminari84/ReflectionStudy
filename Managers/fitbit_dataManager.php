<?php

include_once("userManager.php");
include_once("fitbit_profileManager.php");
include_once("fitbit_exchangeLogger.php");

logDebug("----FITBIT DATA MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

$SERVER = 'api.fitbit.com';
$BASIC_URL = 'https://api.fitbit.com/1/user/';

$STEPS_URL = '/activities/tracker/steps/date/today/';
$CALORIES_URL = '/activities/tracker/calories/date/today/';
$DISTANCE_URL = '/activities/tracker/distance/date/today/';
$FLOORS_URL = '/activities/tracker/floors/date/today/';

$HR_URL = '/activities/heart/date/today/';

$WEIGHT_URL = '/body/weight/date/today/';
$SLEEP_URL = '/sleep/minutesAsleep/date/today/';

$source_url_mapping = ["steps" => $STEPS_URL, 
					   "calories" => $CALORIES_URL,
					   "distance" => $DISTANCE_URL,
					   "floors" => $FLOORS_URL,
					   "heart-rate" => $HR_URL,
					   "weight" => $WEIGHT_URL,
					   "sleep_minutes" => $SLEEP_URL];

function getAvailableDataSources() {
	global $source_url_mapping;

	return array_keys($source_url_mapping);
}

function getFitbitDataForUser($user_id, $source = NULL, $start_date = NULL, $end_date = NULL) {
	$sql = "";
	if  ($source != NULL) {
		$sql .= "SELECT id, user_id, fitbit_date, ".$source;
	} else {
		$sql .= "SELECT *";
	}

	$sql .= " FROM RS_fitbit_data WHERE `user_id`=\"$user_id\"";
	
	if  ($start_date != NULL) {
		//$lower = date('Y-m-d H:i:s', $start_date - 1);
		$sql .= " AND `fitbit_date` >= \"$start_date\" ";
	}
	if  ($end_date != NULL) {
		//$upper = date('Y-m-d H:i:s', $end_date - 1);
		$sql .= " AND `fitbit_date` <= \"$end_date\" ";
	}
	$sql .= "ORDER BY fitbit_date DESC";

	logDebug("getFitbitDataForUser " . $sql);
	$result = executeSimpleSelectQuery($sql);

	return $result;
}

function addFitbitDataForUser($user_id, $source, $data_array) {
	global $conn;

	foreach ($data_array as $n => $values) {
		$date = $values[0];
		$value = $values[1];
		$d_parse = date_parse($date);
		if ($d_parse["year"] < 2006) {
			break;
		}
		logDebug("Checking entry for date:".$date. ", value:".$value);
		//First check if there is entry for this date
		$sql = "SELECT id FROM RS_fitbit_data WHERE `fitbit_date`=\"$date\" AND `user_id`=\"$user_id\"";
		$result = executeSimpleSelectQuery($sql);

		$sql2 = "";
		if (count($result) > 0) {
			$id = $result[0]['id'];
			$sql2 .= "UPDATE RS_fitbit_data SET `$source`=\"$value\" WHERE `id`=\"$id\"";
		} else {
			$sql2 .= "INSERT INTO RS_fitbit_data(`user_id`,`fitbit_date`,`$source`) VALUES (\"$user_id\",\"$date\",\"$value\")";
		}

		if ($conn->query($sql2) == FALSE) {
			logError("Error: " . $sql2 . "<br>", $conn->error);
		}

	}
}

function callFitbitAPIForData($user_id, $source, $scope = "1m") {
	global $source_url_mapping, $BASIC_URL, $STEPS_URL;

	$data_array = [];
	$result_response = "";
	$repeat = 0;
	$max_repeats = 10;
	do {
		logDebug("In callFitbitAPIForData...");
		//scopes: 1d, 7d, 30d, 1w, 1m, 3m, 6m, 1y, max
		## Get activity steps
	    // url = BASIC_URL + '-' + ACTIVITY_URL
	    // activity = get_API(url, access_token)
	    // logging.info(activity)

		$spec_url = $source_url_mapping[$source];
	    $url = $BASIC_URL . "-" . $spec_url . $scope . ".json";
	    #$url = "https://api.fitbit.com/1/user/-/activities/tracker/steps/date/today/max.json";
	    logDebug("Url for requesting FITBIT API data:". $url. ", source:".$source.", scope:".$scope);

	    $fitbit_profile_id = getUserFitbitProfileID($user_id);
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

		logDebug("Raw result from Fitbit API request for data:". substr($result,0,20));

		#log exchange
		logDebug("Logging Fitbit exchange in get data from Fitbit");
		logFitbitExchange($user_id, "Get Fitbit Data | URL: ".$url.", CONTEXT:".print_r($options, TRUE), $result);
		$max_repeats = $max_repeats - 1;
		if ($max_repeats < 0) {
			logDebug("Reached max repeats, had to stop!");
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
			$data_key_list = explode("/",$spec_url);
			$data_key = "";
			for ($i=0; $i<count($data_key_list)-3; $i++) {
				$data_key .= $data_key_list[$i] . "-";
			}
			$data_key = trim($data_key, "-");
			logDebug("Constructed data key for the data: ". $data_key);

			//Don't repeat as we got data this time!
			$repeat = 0;
			LogDebug("Result seems data, decomposing the return JSON:");
			if (array_key_exists($data_key, $assoc)) {
				$list = $assoc[$data_key];
				foreach ($list as $n => $values) {
					$dateTime = $values['dateTime'];
					$value = $values['value'];
					logDebug("DATA point:".$n."->".$dateTime.", ".$value);
					$data_array[] = [$dateTime, $value];
				}
				$result_response = "no_problems";
			} else {
				logError("We are in trouble, there is no ".$data_key, 69);
			}
		}
	} while ($repeat>0);

	return ["data" => $data_array, "result_response" => $result_response];
}

### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request - action:". $action);

if ($action != NULL && $action == "callFitbitAPIForData") {
	logDebug("Calling Fitbit API for Data - FITBIT DATA MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
	$source = isset($_GET['source']) ? $_GET['source'] : NULL;
	$scope = isset($_GET['scope']) ? $_GET['scope'] : NULL;

	logDebug("Adding fitbit data for ".$user_id."...");
	#$id = addFitbitProfile($user_id, $fitbit_id, $fitbit_secret);
	#$data = [["2017-04-11", 34],
	#		 ["2017-04-12", 59],
	#		 ["2017-04-13", 199],
	#		 ["2017-04-14", 12],
	#		 ["2017-04-15", 34],
	#		 ["2017-04-16", 99],
	#		 ["2017-04-17", 19],
	#		 ["2017-04-18", 22]];
	if ($source == "all") {
		logDebug("Requested all sources...");
		$sources = getAvailableDataSources();
		logDebug("We have ".count($sources)." sources, getting all...");
		foreach ($sources as $key => $value) {
			logDebug("Getting data for source: ".$value);
			$result = callFitbitAPIForData($user_id, $value, $scope);
			if (count($result['data']) > 0) {
				logDebug("Length of data to add: ". count($result['data']));
				addFitbitDataForUser($user_id, $value, $result['data']);
			}
		}
	} else {
		$result = callFitbitAPIForData($user_id, $source, $scope);
		if (count($result['data']) > 0) {
			logDebug("Length of data to add: ". count($result['data']));
			addFitbitDataForUser($user_id, $source, $result['data']);
		}
	}

	print('{"success": "OK"}');
}

?>