<?php

// Require the bundled autoload file - the path may need to change
// based on where you downloaded and unzipped the SDK
require_once __DIR__ . '/../twilio-php-master/Twilio/autoload.php';

// Use the REST API Client to make requests to the Twilio REST API
use Twilio\Rest\Client;

include_once("errorReporting.php");

// Your Account SID and Auth Token from twilio.com/console
$sid = ***REMOVED***;
$token = ***REMOVED***;
$service_number = "***REMOVED***";
$client = new Client($sid, $token);

function sendSMS($number, $message) {
	global $client, $service_number;

    logDebug("Sending SMS (From:".$service_number.", To:".$number.", message:".$message.")");

    $client->messages->create($number,
        array(
            'from' => $service_number,
            'body' => $message
        )
    );
}

function sendMMS($number, $message, $img_url) {
    global $client, $service_number;

    logDebug("Sending MMS (From:".$service_number.", To:".$number.", message:".$message.", img_url:".$img_url.")");

    $client->messages->create($number,
        array(
            'subject' => "Reflection study",
            'from' => $service_number,
            'body' => $message,
            'mediaUrl' => $img_url
        )
    );
}

function getMessageLog($number = "") {
	$entries = array();

	// Loop over the list of messages and echo a property for each one
    foreach ($client->account->messages as $message) {
        echo "From: {$message->from}\nTo: {$message->to}\nBody: " . $message->body;
        echo "<br />";

        $isUsed = 0;
        if ($number == "") {
        	$isUsed = 1;
        } elseif ($number == $message->from) {
        	$isUsed = 1;
        }

        if ($isUsed == 1) {
        	$entries[$n] = array("from"=>$message->from, 
        		"to"=>$message->to, 
	        	"body"=>$message->body);
        }
    }
    return $entries;
}

### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request in Mobile Manager - action:". $action);

if ($action != NULL && $action == "sendCustomMessage") {
    logDebug("Sending custom message to user..");

    $user_number = isset($_GET['user_number']) ? $_GET['user_number'] : NULL;
    $message_text = isset($_GET['message_text']) ? $_GET['message_text'] : NULL;

    sendSMS($user_number, $message_text);

    logDebug("Custom message sent!");
}

?>