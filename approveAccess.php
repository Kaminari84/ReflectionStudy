<?php
include_once("Managers/errorReporting.php");
$logPath = "log_FitbitAccess.txt";
$logFile = fopen($logPath, "a");

logDebug("----FITBIT ACCESS LOAD----");

include_once("Managers/userManager.php");
include_once("Managers/fitbit_profileManager.php");
include_once("Managers/fitbit_exchangeLogger.php");


connectToDB();

//$FITBIT_ID = "***REMOVED***";
//$CONSUMER_SECRET = "***REMOVED***";

$code = isset($_GET['code']) ? $_GET['code'] : NULL;
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
#$fitbit_profile_id = NULL;
$fitbit_id = "***REMOVED***";
$fitbit_secret = "***REMOVED***";

session_start();

if (!$user_id) {
	//Get fitbit id from session
	logDebug("No user_id from GET!");
	$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
} else {
	//Set fitbit id to session for later
	logDebug("There is user id from GET, saving to session!");
	$_SESSION['user_id'] = $user_id;
}

/*if ($user_id) {
	logDebug("Go user ID somehow, retrieving the rest...");
	#$fitbit_profile_id = getUserFitbitProfileID($user_id);
	$fitbit_id = "***REMOVED***"; #getFitbitID($fitbit_profile_id);
	$fitbit_secret = "***REMOVED***"; #getFitbitSecret($fitbit_profile_id);
	logDebug("User ID:".$user_id);
	#logDebug("Fitbit profile ID:".$fitbit_profile_id);
	logDebug("Fitbit ID:".$fitbit_id);
	logDebug("Fitbit secret:".$fitbit_secret);
}*/

if ($code) {
	logDebug("Got code:".$code);
	$url = 'https://api.fitbit.com/oauth2/token';
	$data = array(	
		'client_id' => $fitbit_id, 
		'redirect_uri' => 'https://www.rkocielnik.com/ReflectionStudy/approveAccess.php',
		'grant_type' => 'authorization_code',
		'code' => $code);

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
	
	#log exchange
	logDebug("Logging Fitbit exchange in approveAccess get initial tokens");
	logFitbitExchange($user_id, "Get Initial Tokens | URL: ".$url.", CONTEXT:".print_r($options, TRUE), $result);

	if ($result === FALSE) { 
		print("ERROR!");
	} else {
		#logDebug("Fitbit profile ID:".$fitbit_profile_id);
		logDebug("Fitbit ID:".$fitbit_id);
		logDebug("Fitbit secret:".$fitbit_secret);
		logDebug("Fitbit code:".$code);
		logDebug("User ID:".$user_id);

		logDebug("RESULT:".$result);
		$assoc = json_decode($result, true);
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
			logDebug("Fitbit user id:". $fitbit_user_id);

			#get the fitbit profile id, or create new one
			$response = getFitbitProfileByFitbitUserId($fitbit_user_id);
			#there is provile already
			if (count($response) > 0) {
				logDebug("Fitbit profile for this user account already exists, sharing!");
				$fitbit_profile_id = $response[0]['id'];
			#create profile because there is none yet
			} else {
				logDebug("Fitbit profile for this user account does not exist yet, creating!");

				#crate the new profile
				$fitbit_profile_id = addFitbitProfile($fitbit_id, $fitbit_secret, $fitbit_user_id);
			}

			#attach the profile to the user
			setUserFitbitProfileID($user_id, $fitbit_profile_id);

			#fill in the token rights
			setAccessTokens($fitbit_profile_id, $access_token, $refresh_token, $expires_in, $scope, $token_type, $fitbit_user_id);

			print "<div style='margin-left:auto; marigin-right:auto; text-align:center'>Thank you so much! <br >Your approval has been recorderd. 
			You can revoke access at any time by going to the <a href='https://www.fitbit.com/user/profile/apps'>apps list in your Fitbit profile</a>. 
			<br/>We will remind you about revoking your approval at the end of the study. <br /><br />
			Rafal Kocielnik, PhD student <br />
			ReflectionPrompts@gmail.com <br />
			Human Centererd Design &amp; Engineering <br />
			University of Washington </div>";
		}

		$code = NULL;
		$fitbit_profile_id = NULL;
		$user_id = NULL;

		// remove all session variables
		session_unset(); 

		// destroy the session 
		//session_destroy(); 
	}
} else {
	logDebug("No code yet, have to get it!");
	$url = 'https://www.fitbit.com/oauth2/authorize';
	$data = array(	
		'client_id' => $fitbit_id, 
		'redirect_uri' => 'https://www.rkocielnik.com/ReflectionStudy/approveAccess.php',
		'response_type' => 'code',
		'scope' => 'activity heartrate nutrition profile settings sleep weight');

	$newURL = "https://www.fitbit.com/oauth2/authorize?". http_build_query($data);
	logDebug($newURL);

	#log exchange
	logDebug("Logging Fitbit exchange in approveAccess get verification code");
	logFitbitExchange($user_id, "Request Verification Code | URL: ".$newURL.", DATA:".http_build_query($data), "REDIRECT to: https://www.rkocielnik.com/ReflectionStudy/approveAccess.php");

	header('Location: '.$newURL);
}


?>