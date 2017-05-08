<?php

//if (strpos(getcwd(),"ReflectionStudy") === false) {
//	chdir("public_html/ReflectionStudy/auto_logs/");
//}

include_once("Managers/userManager.php");

$logPath = "log_ReflectionStudy_SUL.txt";
$logFile = fopen($logPath, "a");

// current directory
logDebug("INIT DIR:".getcwd());
//chdir("public_html");
// current directory
//logDebug("CHANGE DIR:".getcwd());

logDebug("----STATE UPDATE LOOP----");

include_once("Managers/mobileManager.php");
include_once("Managers/responseManager.php");
include_once("Managers/fitbit_dataManager.php");
include_once("Managers/fitbit_profileManager.php");
include_once("Managers/fitbit_deviceManager.php");
include_once("Managers/studyManager.php");

// Check if normal loop or response from Twilio
$sms_from = isset($_REQUEST['From']) ? $_REQUEST['From'] : NULL;
if ($sms_from) {
	logDebug("SMS response triggered loop");
} else {
	logDebug("Cron general triggered loop");
}

date_default_timezone_set('America/Los_Angeles');
$now = time();

logDebug("ACT_TIME:".date("M-d-Y H:i:s", $now));

$STUDY_START_MSG = "Welcome to Reflection Study! You will receive 1 reflective prompt during the day between";
$STUDY_START_MSG_2 = ". Please read the prompt carefully and send a response.";

$STUDY_END_MSG =" This is the end of the Reflection Study. We will send you a survey link shortly.";

#when the new day starts
$DAY_END_TIME_H = 23; $DAY_END_TIME_M = 00;

function transitionState($actState, $time, $stopMessage) {
	global $DAY_END_TIME;

	logDebug("Transition State function ...");

	$status = [	"stateChanged" => 0, 
				"prevState" => $actState, 
				"nextState" => $actState];

	logDebug("Time offset: " . date("M-d-Y H:i:s", $time));
	logDebug("Current State: <".$actState.">");

	switch ($actState) {
		case "START_STUDY":
			//if ($time >= $DAY_WELCOME_TIME && $time < $DAY_END_TIME) {
				$status["stateChanged"] = 1;
				$status["nextState"] = "IN_STUDY";
				logDebug("Changing state from ".$actState. " to ".$status["nextState"]." ...");
			//}
			break;
		case "IN_STUDY":
			if ($stopMessage == true) {
				$status["stateChanged"] = 1;
				$status["nextState"] = "END_STUDY";
				logDebug("Changing state from ".$actState. " to ".$status["nextState"]." ...");
			}
			break;
	}

	return $status;
}

function transitionMState($actMState, $time) {
	global $STUDY_START_MSG, $STUDY_START_MSG_2, $DAY_END_TIME, $DAY_MSG_TIME;

	logDebug("Transition MState function ...");

	$status = [	"stateChanged" => 0, 
				"prevState" => $actMState, 
				"nextState" => $actMState];

	logDebug("Time offset: " . date("M-d-Y H:i:s", $time));
	logDebug("Current M state: <".$actMState.">");

	switch ($actMState) {
		case "DAY_START":
			logDebug("Checking transitions for state: DAY_START");
			logDebug("DAY_MSG_TIME:".date("M-d-Y H:i:s", $DAY_MSG_TIME));
			logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));
	
			if ($time >= $DAY_MSG_TIME && $time < $DAY_END_TIME) {
				$status["stateChanged"] = 1;
				$status["nextState"] = "DAY_MSG_SENT";
				logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
			} 
			break;
		case "DAY_MSG_SENT":
			logDebug("Checking transitions for state: DAY_MSG_SENT");
			logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));

			if ($time >= $DAY_END_TIME) {
				$status["stateChanged"] = 1;
				$status["nextState"] = "DAY_START";
				logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
			}
			break;
	}

	return $status;
}

function exitState($state) {
	logDebug("Exit State function ...");
}

function exitMState($mstate) {
	logDebug("Exit MState function ...");
}

function enterState($state, $user_id, $number, $email) {
	global $STUDY_START_MSG, $STUDY_START_MSG_2, $DAY_MSG_TIME;

	logDebug("Enter State function ...");
	logDebug("Enter State activity for state ".$state. " ...");
			
	switch ($state) {
		case "ENROLLED":
			logDebug("In ENROLLED");
			
			break;
		case "START_STUDY":
			logDebug("In STUDY_START");
			
			break;
		case "IN_STUDY":
			logDebug("In IN_STUDY");
			$minTime = getUserMinTime($user_id);
			$maxTime = getUserMaxTime($user_id);

			$message_text = $STUDY_START_MSG . " " . $minTime . " and " . $maxTime . $STUDY_START_MSG_2;

			sendSMS($number, $message_text);
			#sendEmail($email, "Fitness Challenges Study - UW", $STUDY_START_MSG);
			$user_timezone = getUserTimezone($user_id);

			//Want to start with a full day
			if ($user_timezone !== null) {
	    		logDebug("Setting user timezone: ".$user_timezone);
	    		date_default_timezone_set($user_timezone);

				logDebug("USER time:".date("M-d-Y H:i:s", $now));
	    	} else {
				date_default_timezone_set('America/Los_Angeles');
			}
			break;
		case "END_STUDY":
			logDebug("In END_STUDY");
			//This is actually handled by the SMS response part
			break;
	}
}

function enterMState($mstate, $user_id, $number, $email, $name) {
	global $STUDY_START_MSG, $STUDY_START_MSG_2, $STUDY_END_MSG;

	logDebug("Enter MState function ...");
	logDebug("Enter MState activity for state ".$mstate. " ...");

	switch ($mstate) {
		case "DAY_START":
			logDebug("In DAY_START");
			//Is there a need to generate new message mappings?
			//logDebug("Day message assignment status:".getDayStudyMappingForUser($user_id, "m1"));
			
			break;
		case "DAY_MSG_SENT":
			logDebug("In DAY_MSG_SENT");
			$log_entry = getUserDayStudyLog($user_id, -1, false);

            $msgID = $log_entry[0]['msg_id'];
            $isSent = $log_entry[0]['sent'];
            logDebug("MSG ID:".$msgID.", isSent:", $isSent);
            
            //Sending only if this message has not been sent yet
            if ($isSent == 0) {
            	logDebug("Not sent yet, decided to send now!");
            	//Set status for already sent
            	setSentStatusForUserLog($user_id, $log_entry[0]['id'], 1);
            	//Get ID for the message
	            $message_entry = getMessageForID($msgID);

	            $d=7;
	            if (strcasecmp($message_entry['scope'], "2weeks") == 0) {
	            	$d=14;
	            }

	            date_default_timezone_set(getUserTimezone($user_id));
				$edate = date('Y-m-d', time());
	            $sdate = date('Y-m-d', strtotime("-".$d." days", strtotime($edate)));
	            logDebug("Start date:" . $sdate . ", End date:" . $edate);
	            
	            logDebug("Trying to send messsage to user ".$user_id.", msg_id:".$message_entry['id']);
	            sendMessagetoUser($user_id, $message_entry['id'], $sdate, $edate);

				#$msg_text = (($cond==0) ? $DAY_MSG[0][$mn] : getMessageForID($mn)['message']);

				#$message = "Hey ".$name.", ".$msg_text." ".$DAY_MSG_EXERCISE[$ex];
				#sendEmail($email, date("l")." challenge 1!", $message);
				#sendSMS($number, $message);
	        }
			break;
	}
}

function inState($state, $user_id) {
	logDebug("In State function: $state");

	switch ($state) {
		case "START_STUDY":
			logDebug("In START_STUDY");
		case "IN_STUDY":
			logDebug("In IN_STUDY");

			//Check if we have access to fitbit data
			logDebug("Getting fitbit access status for user:".$user_id);
			$fitbit_id = getUserFitbitID($user_id);
			$fitbit_profile = getFitbitProfile($fitbit_id);
			logDebug("Current access token is:".$fitbit_profile['access_token']);

			//do we need to ask for access approval?
			if ($fitbit_profile['access_token'] == NULL) {
				logDebug("Access token empty!");
					
				$last_call_date = strtotime(getFitbitLastCallTime($user_id));
			    $next_call_date = strtotime("+2 hours", $last_call_date);
			    
			    logDebug("Last call time:".date('Y-m-d H:i:s',$last_call_date).", next call time:".date('Y-m-d H:i:s',$next_call_date));

				if (time() > $next_call_date) {
					logDebug("Time to ask for access approval again!");
					setFitbitLastCallTime($user_id, date('Y-m-d H:i:s',time()));

					//reguest approval
					requestFitbitAccessApproval($user_id);
				}
			} else {
				//check if it is time to call Fitbit for data update
				$last_call_date = strtotime(getFitbitLastCallTime($user_id));
				$next_call_date = strtotime(getFitbitNextCallTime($user_id));

				logDebug("Last call time:".date('Y-m-d H:i:s',$last_call_date).", next call time:".date('Y-m-d H:i:s',$next_call_date));

				if (time() > $next_call_date) {
					logDebug("Time to call fitbit for data update!");

					//random delay between 2 and 4 hours, helps with multipe users connected to the same fitbit account
					srand(make_seed());
					$call_delay = rand(2*60, 4*60); 
					setFitbitLastCallTime($user_id, date('Y-m-d H:i:s',time()));

					// Getting Fitbit data
					logDebug("Requested all sources of Fitbit in update loop...");
					$sources = getAvailableDataSources();
					logDebug("We have ".count($sources)." sources, getting all...");
					
					$error = 0;
					foreach ($sources as $key => $value) {
						logDebug("Getting data for source: ".$value);
						$result = callFitbitAPIForData($user_id, $value, "1m");
						if (count($result["data"]) > 0) {
							logDebug("Length of data to add: ". count($result["data"]));
							addFitbitDataForUser($user_id, $value, $result["data"]);
						} elseif (strcasecmp($result["result_response"], "too_many_requests") == 0) {
							$call_delay = rand(7*60, 12*60);
							$error = 1;
							logDebug("WARNING: We called too many times, let's call in: ".$call_delay." minutes.");
							break;
						} else {
							$call_delay = rand(7*60, 12*60);
							$error = 1;
							logDebug("ERROR: UNKNOWN PROBLEM!");
							break;
						}
					}

					if ($error == 0) {
						logDebug("Trying to get the device data!");
						#get the devices status data
						logDebug("Getting devices for user...");
						$result = callFitbitAPIForDevices($user_id);
						if (count($result["data"]) > 0) {
							logDebug("Length of devices data to add: ". count($result["data"]));
							addDevicesForUser($user_id, $result["data"]);
						} elseif (strcasecmp($result["result_response"], "too_many_requests") == 0) {
							$call_delay = rand(7*60, 12*60);
							$error = 1;
							logDebug("WARNING: We called too many times, let's call in: ".$call_delay." minutes.");
						}
					}

					$next_call_date = strtotime($call_delay." minutes", time());	
					logDebug("Setting next call to: ".date('Y-m-d H:i:s', $next_call_date));
					setFitbitNextCallTime($user_id, date('Y-m-d H:i:s', $next_call_date));				
				}
			}
		break;
	}

}

function inMState($mstate) {
	logDebug("In MState function ...");

}

function processSMSMessages($state, $mstate) {
	global $sms_from;

	logDebug("Processing SMS messages <".$state.">,<".$mstate.">...");

	switch ($mstate) {
		case "DAY_MSG_SENT":
		case "DAY_START":
			$user_entry = getUserByMobileNumber($sms_from);
			if(count($user_entry)>0) {
				$contents = $_REQUEST['Body'];
				$user_name = $user_entry[0]['name'];
				logDebug("Contents: ".$contents.", ". $user_name);

				// now greet the sender
				header("content-type: text/xml");
				echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
				echo "<Response>";
				echo "<Message>";
				echo $user_name.", thanks for your feedback! You sent us: ".$contents;
				echo "</Message>";
				echo "</Response>";

				//Add to the messages
				addUserResponse($user_entry[0]['id'], $contents);
			}

			break;
	}
}

function isStopMessage($user_id) {
	$messages = getAllUserResponses($user_id);
	
	foreach ($messages as $id => $message) {
		if (strcasecmp($message["text"], "stop") == 0) {
			return true;
		}
	}

	return false;
}

logDebug("Trying to connect to DB in StateUpdateLoop...");
connectToDB();

//No request from user message
$users = array();
if (!$sms_from) {
	logDebug("Looping through all users...");
	$users = getUserList();
//Request from specifc user SMS response
} else {
	$user_entry = getUserByMobileNumber($sms_from);
	logDebug("Request from one user...");
	if(count($user_entry)>0) {
		logDebug("Processing messages of one user:".$sms_from."->".$user_entry[0]['name']);
		$users = $user_entry;

		processSMSMessages($users[0]["state"], $users[0]["m_state"]);
	}
}

logDebug("Going through user list to update their states");

foreach ($users as $n => $user) {
	logDebug("****Updating user [".$n."]->".$user["id"].", ".$user["number"].", ".$user["timezone"]."****");

	$user_id = $user["id"];
	$stopMessage = isStopMessage($user_id);
	$user_timezone = $user["timezone"];

	//Indicate we are updating this user now
	date_default_timezone_set('America/Los_Angeles');
	setLastUpdate($user_id, date("Y-m-d H:i:s", time()));

	if ($user_timezone !== null) {
		logDebug("Setting user timezone: ".$user_timezone);
		date_default_timezone_set($user_timezone);
		$now = time();

		logDebug("USER time:".date("M-d-Y H:i:s", $now));
	}

	logDebug("Getting user log entry in user main loop");

	$log_entry = getUserDayStudyLog($user_id, -1, false);
	$planned_time = $log_entry[0]['planned_time'];
	logDebug("Raw planned time for user: ".$planned_time);
	$pieces = explode(":", $planned_time);
	$msg_hour = $pieces[0];
	$msg_minute = $pieces[1];
	$msg_second = $pieces[2];

	logDebug("Message time components -> H:".$msg_hour.", M:".$msg_minute.", S:".$msg_second);

	$DAY_END_TIME = mktime($DAY_END_TIME_H, $DAY_END_TIME_M, 0, date("m"), date("d"), date("Y"));
	$DAY_MSG_TIME = mktime($msg_hour, $msg_minute, 0, date("m"), date("d"), date("Y"));

	logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));
	logDebug("DAY_MSG_TIME:".date("M-d-Y H:i:s", $DAY_MSG_TIME));

	//state transitons
	$status = transitionState($user["state"], $now, $stopMessage);
	if ($status["stateChanged"] == 1) {
		setUserState($user_id, $status["nextState"]);
	}

	$m_status = [	"stateChanged" => 0, 
					"prevState" => $user["m_state"], 
					"nextState" => $user["m_state"] ];

	if ($status["nextState"] == "IN_STUDY") {
		$m_status = transitionMState($user["m_state"],$now);
		if ($m_status["stateChanged"] == 1) {
			setUserMState($user_id, $m_status["nextState"]);
		}
	}

	logDebug("State changed: ".$status["stateChanged"].", MState changed: ".$m_status["stateChanged"]);

	//state exit functions
	if ($status["stateChanged"] == 1) {
		exitState($status["prevState"]);
	}
	if ($m_status["stateChanged"] == 1) {
		exitMState($m_status["prevState"]);
	}

	//state entry functions
	if ($status["stateChanged"] == 1) {
		enterState($status["nextState"], $user_id, $user["number"], $user["e_mail"]);
	}
	if ($m_status["stateChanged"] == 1) {
		enterMState($m_status["nextState"], $user_id, $user["number"], $user["e_mail"], $user["name"]);
	}

	//state loop functions
	if ($status["stateChanged"] == 0) {
		inState($status["nextState"], $user_id);
	}
	if ($m_status["stateChanged"] == 0) {
		inMState($m_status["nextState"]);
	}

}

logDebug("Update finished!");


?>