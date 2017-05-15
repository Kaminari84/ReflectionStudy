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
$fitbit_profile_id = NULL;
$fitbit_id = NULL;
$fitbit_secret = NULL;

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

if ($user_id) {
	logDebug("Go user ID somehow, retrieving the rest...");
	$fitbit_profile_id = getUserFitbitProfileID($user_id);
	$fitbit_id = getFitbitID($fitbit_profile_id);
	$fitbit_secret = getFitbitSecret($fitbit_profile_id);
	logDebug("User ID:".$user_id);
	logDebug("Fitbit profile ID:".$fitbit_profile_id);
	logDebug("Fitbit ID:".$fitbit_id);
	logDebug("Fitbit secret:".$fitbit_secret);
}

if ($code) {
	logDebug("Got code:".$code);
	$url = 'https://api.fitbit.com/oauth2/token';
	$data = array(	
		'client_id' => $fitbit_id, 
		'redirect_uri' => 'http://www.rkocielnik.com/ReflectionStudy/approveAccess.php',
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
		logDebug("Fitbit profile ID:".$fitbit_profile_id);
		logDebug("Fitbit ID:".$fitbit_id);
		logDebug("Fitbit secret:".$fitbit_secret);
		logDebug("Fitbit code:".$code);
		logDebug("User ID:".$user_id);

		logDebug("RESULT:".$result);
		$assoc = json_decode($result, true);
		#print_r($assoc);
		#print("Has access token:". array_key_exists("access_token", $assoc));
		if (array_key_exists("access_token", $assoc)) {
			logDebug("Access token:". $assoc['access_token']);
			logDebug("Refresh token:". $assoc['refresh_token']);
			setAccessTokens($fitbit_profile_id, $assoc['access_token'], $assoc['refresh_token']);

			print "<div style='margin-left:auto; marigin-right:auto'>Thank you so much! <br >Your approval has been recorderd. 
			You can always revoke access at any time by going to <b><i>https://dev.fitbit.com/apps</b></i>. 
			<br/>We will remind you about revoking your approval at the end of the study. <br /><br />
			Rafal Kocielnik, PhD student <br />
			rkoc@uw.edu <br />
			Human Centererd Design &amp; Engineering <br />
			University of Washington </div>";
		}

		$code = NULL;
		$fitbit_profile_id = NULL;
		$fitbit_id = NULL;
		$fitbit_secret = NULL;
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
		'redirect_uri' => 'http://www.rkocielnik.com/ReflectionStudy/approveAccess.php',
		'response_type' => 'code',
		'scope' => 'profile activity sleep weight heartrate settings');

	$newURL = "https://www.fitbit.com/oauth2/authorize?". http_build_query($data);
	logDebug($newURL);

	#log exchange
	logDebug("Logging Fitbit exchange in approveAccess get verification code");
	logFitbitExchange($user_id, "Request Verification Code | URL: ".$newURL.", DATA:".http_build_query($data), "REDIRECT to: http://www.rkocielnik.com/ReflectionStudy/approveAccess.php");

	header('Location: '.$newURL);
}


?>