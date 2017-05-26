<?php

include_once("fitbit_exchangeLogger.php");

logDebug("----FITBIT PROFILE MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);


function addFitbitProfile($fitbit_id, $fitbit_secret, $fitbit_user_id) {
	global $conn;

	logDebug("Attempting to add fitbit profile: ".$fitbit_id);
	$sql = "INSERT INTO RS_fitbit_user_profile (
		fitbit_id,
		consumer_secret,
		fitbit_user_id) VALUES 
			(\"$fitbit_id\",
			\"$fitbit_secret\",
			\"$fitbit_user_id\")";

	logDebug("Running save SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("New record created successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}

	return $conn->insert_id;
}

function getFitbitProfileByFitbitUserId($fitbit_user_id) {
	$sql = "SELECT * FROM RS_fitbit_user_profile WHERE `fitbit_user_id`=\"$fitbit_user_id\"";
	logDebug("Getting fitbit profile by fitbit user id ". $fitbit_user_id." ...");

	return executeSimpleSelectQuery($sql);
}

function getFitbitProfile($fitbit_profile_id) {
	$sql = "SELECT * FROM RS_fitbit_user_profile WHERE `id`=\"$fitbit_profile_id\"";
	logDebug("Getting fitbit profile for id ". $fitbit_profile_id." ...");
	
	$response = [];
	$result = executeSimpleSelectQuery($sql);
	if (count($result) > 0) {
		$response = $result[0];
	}

	return $response;
}

function getFitbitID($fitbit_profile_id) {
	$sql = "SELECT fitbit_id FROM RS_fitbit_user_profile WHERE `id`=\"$fitbit_profile_id\"";
	logDebug("Getting fitbit id for fitbit profile id ". $fitbit_profile_id." ...");
	
	$result = executeSimpleSelectQuery($sql)[0];
	$fitbit_id = $result['fitbit_id'];

	return $fitbit_id;
}

function getFitbitSecret($fitbit_profile_id) {
	$sql = "SELECT consumer_secret FROM RS_fitbit_user_profile WHERE `id`=\"$fitbit_profile_id\"";
	logDebug("Getting fitbit secret for profile id ". $fitbit_profile_id." ...");

	$result = executeSimpleSelectQuery($sql)[0];
	$consumer_secret = $result['consumer_secret'];

	return $consumer_secret;
}

function getAccessTokens($fitbit_profile_id) {
	$sql = "SELECT access_token, refresh_token FROM RS_fitbit_user_profile WHERE `id`=\"$fitbit_profile_id\"";
	logDebug("Getting access tokens for fitbit profile ". $fitbit_profile_id." ...");

	$result = executeSimpleSelectQuery($sql)[0];

	return $result;
}

### SETTERS ####
/*function getUserIDForFitbitID($fitbit_id) {
	$sql = "SELECT user_id FROM RS_fitbit_user_profile WHERE `fitbit_id`=\"$fitbit_id\"";
	logDebug("Getting user id for fitbit id ". $fitbit_id." ...");

	$result = executeSimpleSelectQuery($sql)[0];
	$user_id = $result['user_id'];

	return $user_id;
}*/

function setAccessTokens($fitbit_profile_id, $access_token, $refresh_token, $expires_in, $scope, $token_type, $fitbit_user_id) {
	global $conn;

	logDebug("Setting access tokens for fitbit profile id: ".$fitbit_profile_id."...");
	$sql = "UPDATE RS_fitbit_user_profile SET 
		`access_token`=\"$access_token\", 
		`refresh_token`=\"$refresh_token\",
		`expires_in`=\"$expires_in\",
		`scope`=\"$scope\",
		`token_type`=\"$token_type\",
		`fitbit_user_id`=\"$fitbit_user_id\"
		WHERE `id`=\"$fitbit_profile_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function clearAccessTokens($fitbit_profile_id) {
	setAccessTokens($fitbit_profile_id, NULL, NULL, 0, NULL, NULL);
}

function refreshAccessToken($fitbit_profile_id, $user_id) {
	logDebug("Attempting to refresh an expired access token...");
	$tokens = getAccessTokens($fitbit_profile_id);
	$fitbit_id = getFitbitID($fitbit_profile_id);
	$fitbit_secret = getFitbitSecret($fitbit_profile_id);

	$refresh_token = $tokens['refresh_token'];

	logDebug("Refresh token for fitbit profile: ".$fitbit_profile_id." -> ".$refresh_token);

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
				$access_token = $assoc['access_token'];
				$refresh_token = $assoc['refresh_token'];
				$expires_in = (int)$assoc['expires_in'];
				$scope = $assoc['scope'];
				$token_type = $assoc['token_type'];
				$fitbit_user_id = $assoc['user_id'];

				logDebug("Access token:". $access_token);
				logDebug("Refresh token:". $refresh_token);
				logDebug("Expires in:". $expires_in);
				logDebug("Scope:". $scope);
				logDebug("Token type:". $token_type);
				logDebug("Fitbit User_id:". $fitbit_user_id);

				setAccessTokens($fitbit_profile_id, $access_token, $refresh_token, $expires_in, $scope, $token_type, $fitbit_user_id);
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

/*if ($action != NULL && $action == "addFitbitProfile") {
	logDebug("Add Fitbit Profile - FITBIT PROFILE MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$fitbit_id = isset($_GET['fitbit_id']) ? $_GET['fitbit_id'] : NULL;
	$fitbit_secret = isset($_GET['fitbit_secret']) ? $_GET['fitbit_secret'] : NULL;

	logDebug("Adding fitbit profile ".$fitbit_id."...");
	$fitbit_profile_id = addFitbitProfile($fitbit_id, $fitbit_secret);

	echo $fitbit_profile_id;
} */
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