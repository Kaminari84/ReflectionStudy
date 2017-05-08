<?php

include_once("fitbit_exchangeLogger.php");

logDebug("----FITBIT PROFILE MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);


function addFitbitProfile($fitbit_id, $fitbit_secret) {
	global $conn;

	logDebug("Attempting to add fitbit profile: ".$fitbit_id);
	//Check if fitbit profile already exists
	$result = getFitbitProfile($fitbit_id);
	if (count($result) == 0) {
		logDebug("Fitbit profile does not exist yet, adding...");

		$sql = "INSERT INTO RS_fitbit_user_profile (
			fitbit_id,
			consumer_secret) VALUES 
				(\"$fitbit_id\",
				\"$fitbit_secret\")";

		logDebug("Running save SQL: " . $sql);

		if ($conn->query($sql) === TRUE) {
		    logDebug("New record created successfully");
		} else {
		    logError("Error: " . $sql . "<br>", $conn->error);
		}
	} else {
		logDebug("Fitbit profile already exists, skipping...");
	}
}

function getFitbitProfile($fitbit_id) {
	$sql = "SELECT * FROM RS_fitbit_user_profile WHERE `fitbit_id`=\"$fitbit_id\"";
	logDebug("Getting fitbit profile for fitbit id ". $fitbit_id." ...");
	
	$response = [];
	$result = executeSimpleSelectQuery($sql);
	if (count($result) > 0) {
		$response = $result[0];
	}

	return $response;
}

/*function getFitbitProfileID($user_id) {
	$sql = "SELECT fitbit_id FROM RS_fitbit_user_profile WHERE `user_id`=\"$user_id\"";
	logDebug("Getting fitbit id for user ". $user_id." ...");
	
	$result = executeSimpleSelectQuery($sql)[0];
	$fitbit_id = $result['fitbit_id'];

	return $fitbit_id;
}*/

function getFitbitSecret($fitbit_id) {
	$sql = "SELECT consumer_secret FROM RS_fitbit_user_profile WHERE `fitbit_id`=\"$fitbit_id\"";
	logDebug("Getting fitbit secret for profile ". $fitbit_id." ...");

	$result = executeSimpleSelectQuery($sql)[0];
	$consumer_secret = $result['consumer_secret'];

	return $consumer_secret;
}

/*function getUserIDForFitbitID($fitbit_id) {
	$sql = "SELECT user_id FROM RS_fitbit_user_profile WHERE `fitbit_id`=\"$fitbit_id\"";
	logDebug("Getting user id for fitbit id ". $fitbit_id." ...");

	$result = executeSimpleSelectQuery($sql)[0];
	$user_id = $result['user_id'];

	return $user_id;
}*/

/*function getLastCallTime($user_id) {
	$sql = "SELECT last_call_time FROM RS_fitbit_user_profile WHERE `user_id`=\"$user_id\"";
	logDebug("Getting access tokens for user id ". $user_id." ...");

	$result = executeSimpleSelectQuery($sql)[0];

	return $result['last_call_time'];
}

function getNextCallTime($user_id) {
	$sql = "SELECT next_call_time FROM RS_fitbit_user_profile WHERE `user_id`=\"$user_id\"";
	logDebug("Getting access tokens for user id ". $user_id." ...");

	$result = executeSimpleSelectQuery($sql)[0];

	return $result['next_call_time'];
}*/

function setAccessTokens($fitbit_id, $access_token, $refresh_token) {
	global $conn;

	logDebug("Setting access tokens for fitbit profile: ".$fitbit_id."...");
	$sql = "UPDATE RS_fitbit_user_profile SET `access_token`=\"$access_token\", `refresh_token`=\"$refresh_token\"  WHERE `fitbit_id`=\"$fitbit_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}

/*function setLastCallTime($user_id, $lastCallTime) {
	global $conn;

	logDebug("Setting last call time for user ".$user_id."...");
	$sql = "UPDATE RS_fitbit_user_profile SET `last_call_time`=\"$lastCallTime\" WHERE `user_id`=\"$user_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setNextCallTime($user_id, $nextCallTime) {
	global $conn;

	logDebug("Setting next call time for user ".$user_id."...");
	$sql = "UPDATE RS_fitbit_user_profile SET `next_call_time`=\"$nextCallTime\" WHERE `user_id`=\"$user_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}*/

function getAccessTokens($fitbit_id) {
	$sql = "SELECT access_token, refresh_token FROM RS_fitbit_user_profile WHERE `fitbit_id`=\"$fitbit_id\"";
	logDebug("Getting access tokens for fitbit profile ". $fitbit_id." ...");

	$result = executeSimpleSelectQuery($sql)[0];

	return $result;
}

function clearAccessTokens($fitbit_id) {
	setAccessTokens($fitbit_id, NULL, NULL);
}

function refreshAccessToken($fitbit_id, $user_id) {
	logDebug("Attempting to refresh an expired access token...");
	$tokens = getAccessTokens($fitbit_id);
	$fitbit_secret = getFitbitSecret($fitbit_id);

	$refresh_token = $tokens['refresh_token'];

	logDebug("Refresh token for fitbit profile: ".$fitbit_id." -> ".$refresh_token);

	$url = 'https://api.fitbit.com/oauth2/token';
	$data = array(	
		'grant_type' => 'refresh_token',
		'refresh_token' => $refresh_token
	);

	$auth = $fitbit_id.":".$fitbit_secret;
	$auth = base64_encode($auth);

	// use key 'http' even if you send the request to https://...
	$options = array(
	    'http' => array(
	        'header'  => "Authorization: Basic ".$auth."\r\n". 
	        			 "Content-Type: application/x-www-form-urlencoded\r\n",
	        'method'  => 'POST',
	        'content' => http_build_query($data)
	    )
	);

	$context  = stream_context_create($options);
	$result = file_get_contents($url, false, $context);

	$success = 0;

	#log exchange
	logDebug("Logging Fitbit exchange in refreshAccessToken");
	logFitbitExchange($user_id, "Referesh Tokens | URL: ".$url.", CONTEXT:".print_r($options, TRUE), $result);

	if ($result === FALSE) { 
		print("ERROR!");
	} else {
		logDebug("Fitbit ID:".$fitbit_id);
		logDebug("Fitbit secret:".$fitbit_secret);

		logDebug("RESULT:".$result);
		$assoc = json_decode($result, true);

		if (array_key_exists("errors", $assoc)) {
			$error_list = $assoc["errors"];
			if (count($error_list) == 1) {
				logDebug("Just one error!");
				$error = $error_list[0];
				logDebug("Error type:". $error["errorType"]);	
			} else {
				logError("We are in trouble, more than one error when requesting FITBIT data!",69);
			}
		} else {
			$success = 1;
			#print_r($assoc);
			#print("Has access token:". array_key_exists("access_token", $assoc));
			if (array_key_exists("access_token", $assoc)) {
				logDebug("Access token:". $assoc['access_token']);
				logDebug("Refresh token:". $assoc['refresh_token']);
				setAccessTokens($fitbit_id, $assoc['access_token'], $assoc['refresh_token']);
			}
		}
	}

	return $success;
}

/*function setAccess($fitbit_id, $isAccess) {
	global $conn;

	logDebug("Setting access tokens for user ".$_id."...");
	$sql = "UPDATE RS_fitbit_user_profile SET `access_approved`=\"$isAccess\"  WHERE `fitbit_id`=\"$fitbit_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}*/


### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request - action:". $action);

if ($action != NULL && $action == "addFitbitProfile") {
	logDebug("Add Fitbit Profile - FITBIT PROFILE MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$fitbit_id = isset($_GET['fitbit_id']) ? $_GET['fitbit_id'] : NULL;
	$fitbit_secret = isset($_GET['fitbit_secret']) ? $_GET['fitbit_secret'] : NULL;

	logDebug("Adding fitbit profile ".$fitbit_id."...");
	addFitbitProfile($fitbit_id, $fitbit_secret);
} 
/*elseif ($action != NULL && $action == "setFitbitTokens") {
	logDebug("Setting Fitbit Tokens - FITBIT PROFILE MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$fitbit_id = isset($_GET['fitbit_id']) ? $_GET['fitbit_id'] : NULL;
	$access_token = isset($_GET['access_token']) ? $_GET['access_token'] : NULL;
	$refresh_token = isset($_GET['refresh_token']) ? $_GET['refresh_token'] : NULL;

	logDebug("Setting fitbit access tokens for profile:".$fitbit_id."...");
	setAccessTokens($fitbit_id, $access_token, $refresh_token);

	print('{"status": "OK"}');
} elseif ($action != NULL && $action == "clearFitbitTokens") {
	logDebug("Setting Fitbit Tokens - FITBIT PROFILE MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$fitbit_id = isset($_GET['fitbit_id']) ? $_GET['fitbit_id'] : NULL;

	logDebug("Setting fitbit access tokens for profile:".$fitbit_id."...");
	clearAccessTokens($fitbit_id);

	print('{"status": "OK"}');
} 
elseif ($action != NULL && $action == "revokeFitbitAccess") {
	logDebug("Revoking fitbit access - FITBIT PROFILE MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$fitbit_id = isset($_GET['fitbit_id']) ? $_GET['fitbit_id'] : NULL;

	$user_id = getUserIDForFitbitID($fitbit_id);

	logDebug("Revoking fitbit access for ".$user_id."...");

	setAccess($user_id, 0);

	print('{"status": "OK"}');
}
elseif ($action != NULL && $action == "approveFitbitAccess") {
	logDebug("Approving fitbit access - FITBIT PROFILE MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$fitbit_id = isset($_GET['fitbit_id']) ? $_GET['fitbit_id'] : NULL;
	
	$user_id = getUserIDForFitbitID($fitbit_id);

	logDebug("Approving fitbit access for ".$user_id."...");

	setAccess($user_id, 1);

	print('{"status": "OK"}');
} */

?>