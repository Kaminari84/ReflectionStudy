<?php

include_once("messageManager.php");
include_once("userManager.php");
include_once("mobileManager.php");
include_once("fitbit_profileManager.php");
include_once("fitbit_dataManager.php");
include_once("patternFillManager.php");

logDebug("----STUDY MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

function generateMessagesForUser() {
	logDebug("Generating messages for user");

	$messages = getAllMessages();
	$two_weeks_idx = [1,2,3,5,6, 8,9,11,12,13, 15,16,17,20];

	$selectedMessages = array();
	for ($i=0; $i<count($messages); $i++) {
		if (in_array($messages[$i]['id'],$two_weeks_idx)) {
			$selectedMessages[] = $messages[$i]['id'];
		}
	}

	return $selectedMessages;
}

function assignMessagesToSlots($msgCount, $messages) {
	$selectedMessages = $messages;

	$slots = array();
	while(count($slots)<$msgCount) {
		logDebug("Selecting one random message first");
		$max = count($messages)-1;
		logDebug("Rand range: <0-".$max.">");
		$sn = rand(0,$max);

		$slots[] = $messages[$sn];
		//echo "Rand <0-".$max."> : ".$sn."->".$messages[$sn]."<br />";

		array_splice($messages, $sn, 1);

		//refill message countainer
		if (count($messages) == 0) {
			//echo "Refilling messages - count before:".count($messages)."<br />";;
			$numSel = floor(count($selectedMessages)/2);
			if (count($selectedMessages)%2==1) {
				$numSel+1;
			}
			//echo "Count selected messages: ".count($selectedMessages).", num sel:".$numSel."<br />";
			
			//go back
			for ($i=0; $i<$numSel; $i++) {
				//echo "Adding message: ".($slots[count($slots)-count($selectedMessages)+$i])." <br />";
				$messages[] = $slots[count($slots)-count($selectedMessages)+$i];
			}
			//echo "Refilling messages - count after:".count($messages)."<br />";; 
			//print_r($messages);
		}
	}

	logDebug("Length of slots: ".count($slots));

	return $slots; //contains message id
}

function getMessageLogsSent($user_id) {
	global $conn;

	logDebug("Getting appropriate log id for response time ...");
	$sql = "SELECT * FROM RS_study_log WHERE `user_id`=\"$user_id\" AND `msg_sent_time`>=\"2015-01-01\" ORDER BY `local_date` ASC";
	logDebug("getMessageLogsSent - selecting: " . $sql);

	$result = executeSimpleSelectQuery($sql);
	
	return $result;
}

function getUserDayStudyLog($user_id, $local_date = -1, $create = true) {
	global $conn;

	date_default_timezone_set('America/Los_Angeles');
	$date = date("Y-m-d H:i:s", time());

	if ($local_date == -1) {
		date_default_timezone_set(getUserTimezone($user_id));
		$local_date = date("Y-m-d", time());
	}

	//Find today's entry
	$entry_id = -1;
	$try = 0; 
	$result = [];
	while ($entry_id == -1) {
		$sql = "SELECT * FROM RS_study_log WHERE `user_id`=\"$user_id\" AND `local_date`=\"$local_date\"";
		//logDebug("getUserDayStudyLogID - selecting: " . $sql);

		$result = executeSimpleSelectQuery($sql);
		if (count($result)>0) {
			$entry_id = $result[0]['id'];
		}

		if ($create == true && $entry_id == -1) {
			$sql = "INSERT INTO RS_study_log(user_id, date, local_date) VALUES (\"$user_id\", \"$date\",\"$local_date\" )";
			
			logDebug("getUserDayStudyLogID - Running save SQL: " . $sql);

			if ($conn->query($sql) === TRUE) {
			    logDebug("New record created successfully");
			} else {
			    logError("Error: " . $sql . "<br>", $conn->error);
			}
		} else {
			break;
		}

		$try++;
		if ($try > 10) {
			logDebug("Gave up, 10 tries!");
			break;
		}
	}

	return $result;
}

/*function getLogIDForResponseDate($user_id, $local_response_date) {
	global $conn;

	$log_id = NULL;
	logDebug("Getting appropriate log id for response time ...");
	$sql = "SELECT * FROM RS_study_log WHERE `user_id`=\"$user_id\" AND `local_date`<=\"$local_response_date\" ORDER BY `local_date` ASC";
	logDebug("getLogIDForResponseDate - selecting: " . $sql);

	$result = executeSimpleSelectQuery($sql);
	if (count($result)>0) {
		$log_id = $result[0]['id'];
	}

	return $log_id;
}*/

function getUserDayStudyLogByID($user_id, $log_id) {
	logDebug("Getting day study log by id: ".$log_id);
	$sql = "SELECT * FROM RS_study_log WHERE `user_id`=\"$user_id\" AND `id`=\"$log_id\" ORDER BY `local_date` ASC";
	logDebug("getUserDayStudyLogByID - selecting: " . $sql);

	return executeSimpleSelectQuery($sql)[0];
}

function setFutureDayMessageMappingForUser($user_id, $dayMessageMapping) {
	global $conn;

	logDebug("Adding future user day message mapping...");

	//get the acceptable times for this user
	$min_time = getUserMinTime($user_id);
	$max_time = getUserMaxTime($user_id);

	logDebug("Min time acceptable for user:".$min_time);
	logDebug("Max time acceptable for user:".$max_time);

	//date_default_timezone_set('America/Los_Angeles');
	//$date = date("Y-m-d", time());

		//map the acceptable times to user timezone
	$min_time_int = strtotime($min_time);
	$max_time_int = strtotime($max_time);

	$min_time_str = date("H:i:s", $min_time_int);
	$max_time_str = date("H:i:s", $max_time_int);

	logDebug("Local start time str: ".$min_time_str.", num: ".strtotime($min_time));
	logDebug("Local end time str: ".$max_time_str.", num: ".strtotime($max_time));

	#date_default_timezone_set(getUserTimezone($user_id));
	$local_date = date("Y-m-d", time());
	logDebug("Local date:".$local_date);

	logDebug("Loop through future dates to add message mappings...");
	for ($ad=0; $ad<count($dayMessageMapping); $ad++) {
		$act_date = date('Y-m-d', strtotime($ad." days", strtotime($local_date)));
		logDebug("Act date:".$act_date);

		$planned_time_int = rand($min_time_int, $max_time_int);
		logDebug("Random planned time int:".$planned_time_int);
		$planned_time_str = date('H:i', $planned_time_int);
		logDebug("Random planned time str:".$planned_time_str);

		logDebug("Min mapped: ".date('H:i', $min_time_int).", Max mapped: ".date('H:i', $max_time_int));

		$log_entry = getUserDayStudyLog($user_id, $act_date, true);
		$entry_id = $log_entry[0]['id'];

		$m_id = $dayMessageMapping[$ad];

		logDebug("ad=".$ad.",M=".$m_id);

		$sql = "UPDATE RS_study_log SET `msg_id`=\"$m_id\", `planned_time`=\"$planned_time_str\" WHERE `id`=\"$entry_id\"";

		logDebug("setFutureDayMessageMappingForUser: " . $sql);

		if ($conn->query($sql) === TRUE) {
	    	logDebug("New record created successfully");
		} else {
	    	logError("Error: " . $sql . "<br>", $conn->error);
		}
	} 
}

function setMsgSentTimeForUserLog($user_id, $log_id, $sent_time) {
	global $conn;

	logDebug("Setting the msg sent status for log: ".$log_id." to ".$sent_time);
	$sql = "UPDATE RS_study_log SET `msg_sent_time`=\"$sent_time\" WHERE `user_id`=\"$user_id\" AND `id`=\"$log_id\"";
	logDebug("setMsgSentTimeForUserLog: " . $sql);

	if ($conn->query($sql) === TRUE) {
    	logDebug("Update successful");
	} else {
    	logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setRmd1SentTimeForUserLog($user_id, $log_id, $sent_time) {
	global $conn;

	logDebug("Setting the rmd 1 sent status for log: ".$log_id." to ".$sent_time);
	$sql = "UPDATE RS_study_log SET `rmd1_sent_time`=\"$sent_time\" WHERE `user_id`=\"$user_id\" AND `id`=\"$log_id\"";
	logDebug("setRmd1SentTimeForUserLog: " . $sql);

	if ($conn->query($sql) === TRUE) {
    	logDebug("Update successful");
	} else {
    	logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setFollowupSentTimeForUserLog($user_id, $log_id, $sent_time) {
	global $conn;

	logDebug("Setting the followup sent status for log: ".$log_id." to ".$sent_time);
	$sql = "UPDATE RS_study_log SET `followup_sent_time`=\"$sent_time\" WHERE `user_id`=\"$user_id\" AND `id`=\"$log_id\"";
	logDebug("setMsgSentTimeForUserLog: " . $sql);

	if ($conn->query($sql) === TRUE) {
    	logDebug("Update successful");
	} else {
    	logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setRmd2SentTimeForUserLog($user_id, $log_id, $sent_time) {
	global $conn;

	logDebug("Setting the rmd 2 sent status for log: ".$log_id." to ".$sent_time);
	$sql = "UPDATE RS_study_log SET `rmd2_sent_time`=\"$sent_time\" WHERE `user_id`=\"$user_id\" AND `id`=\"$log_id\"";
	logDebug("setRmd1SentTimeForUserLog: " . $sql);

	if ($conn->query($sql) === TRUE) {
    	logDebug("Update successful");
	} else {
    	logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setDialogueCompleteSentTimeForUserLog($user_id, $log_id, $sent_time) {
	global $conn;

	logDebug("Setting the dialogue complete sent status for log: ".$log_id." to ".$sent_status);
	$sql = "UPDATE RS_study_log SET `dialogue_complete_sent_time`=\"$sent_time\" WHERE `user_id`=\"$user_id\" AND `id`=\"$log_id\"";
	logDebug("setDialogueCompleteSentTimeForUserLog: " . $sql);

	if ($conn->query($sql) === TRUE) {
    	logDebug("Update successful");
	} else {
    	logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setChartDataForUserLog($user_id, $log_id, $chart_data, $chart_img) {
	global $conn;

	logDebug("Setting the chart data status for log: ".$log_id." -> data: ".$chart_data.", chart_img". $chart_img);
	$sql = "UPDATE RS_study_log SET `chart_data`=\"$chart_data\", `chart_img`=\"$chart_img\" WHERE `user_id`=\"$user_id\" AND `id`=\"$log_id\"";
	logDebug("setChartDataForUserLog: " . $sql);

	if ($conn->query($sql) === TRUE) {
    	logDebug("Update successful");
	} else {
    	logError("Error: " . $sql . "<br>", $conn->error);
	}
}


### COMMUNICATING WTH USER ####

function requestFitbitAccessApproval($user_id) {
	logDebug("Sending message to request Fitbit access approval...");
	$mobile_number = getUserMobileNumber($user_id);

	$message = "This is a request from the Reflective Prompts University of Washington study for a temporary access approval to your Fitbit data. Please follow the link (you will need to log into your Fitbit account) to grant temporary access approval: https://www.rkocielnik.com/ReflectionStudy/approveAccess.php?user_id=".$user_id;

	sendSMS($mobile_number, $message);
}

function getUserActivityChart($user_id, $source, $scope, $start_date, $end_date, $filename, $target="Mobile") {
	logDebug("Generating chart for user data: ".$user_id);

	if (strcasecmp($target, "JSON") == 0) {
		$filename = "test_".$filename;
	}
	$today = date('Y-m-d');

	logDebug("Filename:".$filename);
	logDebug("Source:".$source);
	logDebug("Scope:".$scope);
	logDebug("Start date:".$start_date);
	logDebug("End date:".$end_date);

	//get the data
	$data = getFitbitDataForUser($user_id, $source, $start_date, $end_date);

	$data_str = "";
	$label_str = "";
	logDebug("Received data:");

	//Ensure there are 0 in the data for the dates for which there were no entries in DB
	$act_date = $start_date;
	$d = 0;
	while (strtotime($act_date) < strtotime("-1 days", strtotime($end_date))) {
		$act_date = date('Y-m-d', strtotime($d." days", strtotime($start_date)));
		logDebug("Act date:".$act_date);

		//check if data for this date is in the returned list of values
		$found = 0;
		foreach ($data as $n => $values) {
			$fitbit_date =  date('Y-m-d', strtotime($values["fitbit_date"]));
			logDebug("N:".$n.", fitbit-date:". $fitbit_date);

			if (strcmp($fitbit_date, $act_date) == 0) {
				logDebug("Val:".$values[$source]);
				$data_str .= round($values[$source]).",";
				$found = 1;
			}

			//foreach ($values as $key => $value) {
			//	logDebug("Key:".$key.", Value:".$value);
			//}
		}

		if ($found == 0) {
			logDebug("Adding default 0 for ".$act_date);
			$data_str .= "0,";
		}

		//generate label
		$today_label = date('D', strtotime($d." days", strtotime($start_date)));
		if ($d<7) {
			$label_str .= $today_label.",";
		}

		$d++;
	}

	$data_str = rtrim($data_str,",");
	logDebug("Data string to send:". $data_str);

	//construct URL
	$url = "http://motivators.hcde.uw.edu/render?";
	$url .= "source=".strtolower($source);
	$url .= "&time=".strtolower($scope);
	$url .= "&data=".$data_str;
	$url .= "&labels=".$label_str;
	$url .= "&start_date=".$start_date;
	$url .= "&end_date=".date('Y-m-d', strtotime("-1 days", strtotime($end_date) ));
	$url .= "&filename=".$filename;
	$url .= "&today=".$today;

	logDebug("Constructed URL:".$url);

	//call the chart renderer service
	$result = file_get_contents($url);
	logDebug("Response:".$result);

	$chart_img_url = "http://motivators.hcde.uw.edu/".$filename;

	$arr = ["source" => $source, "scope" => $scope, "data" => $data_str, "image" => $chart_img_url];

	if (strcasecmp($target,"Mobile") == 0) {
		return $arr;
	} elseif (strcasecmp($target, "Array") == 0) {
		return $arr;
	} elseif (strcasecmp($target, "JSON") == 0) {
		return json_encode($arr);
	}
}

function sendTestMessageToUser($user_id, $msg_id, $start_date, $end_date, $target = "Mobile") {
	logDebug("Trying to sent an MMS to user: ".$user_id." for channel: ".$target);

	#get message properties
	$raw_msg_params = getTestMessageForID($msg_id);
	
	$source = strtolower($raw_msg_params['source']);
	$scope = strtolower($raw_msg_params['scope']);
	$filename = "img_".$user_id."_".$msg_id."_".date('Y_m_d_H_i_s').".png";
	$number = getUserMobileNumber($user_id);
	$user_name = getUserName($user_id);
	
	$goal_scope = ['daily','weekly','long-term'];
	if (array_key_exists("goal_scope", $raw_msg_params)) {
		$goal_scope = $raw_msg_params['goal_scope'];
	}
	$user_goal = getMatchingUserGoal($user_id, $goal_scope);

	logDebug("Filename:".$filename);
	logDebug("Source:".$source);
	logDebug("Scope:".$scope);
	logDebug("Start date:".$start_date);
	logDebug("End date:".$end_date);
	logDebug("User mobile number:".$number);
	logDebug("User name:".$user_name);
	logDebug("Matching user goal:".print_r($user_goal,TRUE));
	logDebug("Raw message text:".$raw_msg_params['text']);

	#fill message pattern
	$msg_params = fillMessagePattern($raw_msg_params, $user_name, $user_goal);

	logDebug("Message text after pattern fill:".$msg_params['text']);
	logDebug("Message luis url:".$msg_params['luis_url']);

	#construct the response array
	$arr = ["number" => $number, "text" => $msg_params['text']];
	if (array_key_exists("luis_url", $msg_params)) {
		$arr["luis_url"] = $msg_params["luis_url"];
	}

	if (strcasecmp($source, "none") !=  0) {
		logDebug("Message with scope, generate chart for data!");
		#get the activity chart for message
		$chart_img_params = getUserActivityChart($user_id, $source, $scope, $start_date, $end_date, $filename, "Mobile");
		$arr['image'] = $chart_img_params['image'];
		$arr['data'] = $chart_img_params['data'];
	} 

	#return in the format desired
	if (strcasecmp($target,"Mobile") == 0) {
		if (!array_key_exists("image", $arr)) {
			sendSMS($arr['number'], $arr['text']);
		} else {
			//Send the MMS with chart
			sendMMS($arr['number'], $arr['text'], $arr['image']);
		}
		return $arr;
	} elseif (strcasecmp($target, "Array") == 0) {
		return $arr;
	} elseif (strcasecmp($target, "JSON") == 0) {
		return json_encode($arr);
	}


	/*if (strcmp($source, "none") ==  0) {
		logDebug("Message without the scope, no image needed!");
		if (strcasecmp($target,"Mobile") == 0) {
			sendSMS($number, $msg_params['text']);
		} elseif (strcasecmp($target, "Array") == 0) {
			$arr = ["number" => $number, "text" => $msg_params['text']];
			return $arr;
		} elseif (strcasecmp($target, "JSON") == 0) {
			$arr = ["number" => $number, "text" => $msg_params['text']];
			return json_encode($arr);
		}
	} else {
		logDebug("Message with scope, generate chart for data!");

		#get the activity chart for message
		$chart_img_url = getUserActivityChart($user_id, $source, $scope, $start_date, $end_date, $filename, "Mobile");

		if (strcasecmp($target,"Mobile") == 0) {
			//Send the MMS with chart
			sendMMS($number, $msg_params['text'], $chart_img_url);
		} elseif (strcasecmp($target, "Array") == 0) {
			$arr = ["number" => $number, "text" => $msg_params['text'], "image" => $chart_img_url];
			return $arr;
		} elseif (strcasecmp($target, "JSON") == 0) {
			$arr = ["number" => $number, "text" => $msg_params['text'], "image" => $chart_img_url];
			return json_encode($arr);
		}
	}*/
}

function sendTestFollowUpMessageToUser($user_id, $msg_id, $intent, $original_msg = "", $target = "Mobile") {
	logDebug("Trying to sent follow up message to user: ".$user_id." for channel: ".$target);

	#get message properties
	$raw_msg_params = getTestFollowUpForMessageID($msg_id, $intent);

	$number = getUserMobileNumber($user_id);
	$user_name = getUserName($user_id);

	if (count($raw_msg_params) == 0) {
		$msg_params = getReplyConfirmationMessage($user_name, $original_msg);
	} else {
		logDebug("User mobile number:".$number);
		logDebug("User name:".$user_name);
		logDebug("Raw message intent match:".$raw_msg_params['intent_match']);
		logDebug("Raw message text:".$raw_msg_params['text']);

		#fill message pattern
		$msg_params = fillFollowUpMessagePattern($raw_msg_params);

		logDebug("Message text after pattern fill:".$msg_params['text']);
	}

	#construct the response array
	$arr = ["number" => $number, "text" => $msg_params['text']];

	#return in the format desired
	if (strcasecmp($target,"Mobile") == 0) {
		sendSMS($arr['number'], $arr['text']);
	} elseif (strcasecmp($target, "Array") == 0) {
		return $arr;
	} elseif (strcasecmp($target, "JSON") == 0) {
		return json_encode($arr);
	}
}

/*function sendMessagetoUser($user_id, $msg_id, $start_date, $end_date, $target = "Mobile") {
	logDebug("Trying to sent an MMS to user: ".$user_id);

	#get message properties
	$message_values = getMessageForID($msg_id);
	$source = strtolower($message_values['source']);
	$scope = strtolower($message_values['scope']);
	$filename = "img_".$user_id."_".$msg_id."_".date('Y_m_d_H_i_s').".png";
	if (strcasecmp($target, "JSON") == 0) {
		$filename = "test_".$filename;
	}
	$today = date('Y-m-d');

	logDebug("Filename:".$filename);
	logDebug("Source:".$source);
	logDebug("Scope:".$scope);
	logDebug("Start date:".$start_date);
	logDebug("End date:".$end_date);

	$number = getUserMobileNumber($user_id);
	$message_text = $message_values['text'];

	logDebug("User phone number:".$number);
	logDebug("Message text:".$message_text);

	if (strcmp($source, "none") ==  0) {
		logDebug("Message without the scope, no image needed!");
		if (strcasecmp($target,"Mobile") == 0) {
			sendSMS($number, $message_text);
		} elseif (strcasecmp($target, "JSON") == 0) {
			$arr = ["number" => $number, "text" => $message_text];
			return json_encode($arr);
		}
	} else {
		logDebug("Message with scope, generate chart for data!");

		//get the data
		$data = getFitbitDataForUser($user_id, $source, $start_date, $end_date);

		$data_str = "";
		$label_str = "";
		logDebug("Received data:");

		//Ensure there are 0 in the data for the dates for which there were no entries in DB
		$act_date = $start_date;
		$d = 0;
		while (strtotime($act_date) < strtotime("-1 days", strtotime($end_date))) {
			$act_date = date('Y-m-d', strtotime($d." days", strtotime($start_date)));
			logDebug("Act date:".$act_date);

			//check if data for this date is in the returned list of values
			$found = 0;
			foreach ($data as $n => $values) {
				$fitbit_date =  date('Y-m-d', strtotime($values["fitbit_date"]));
				logDebug("N:".$n.", fitbit-date:". $fitbit_date);

				if (strcmp($fitbit_date, $act_date) == 0) {
					logDebug("Val:".$values[$source]);
					$data_str .= round($values[$source]).",";
					$found = 1;
				}

				//foreach ($values as $key => $value) {
				//	logDebug("Key:".$key.", Value:".$value);
				//}
			}

			if ($found == 0) {
				logDebug("Adding default 0 for ".$act_date);
				$data_str .= "0,";
			}

			//generate label
			$today_label = date('D', strtotime($d." days", strtotime($start_date)));
			if ($d<7) {
				$label_str .= $today_label.",";
			}

			$d++;
		}

		$data_str = rtrim($data_str,",");
		logDebug("Data string to send:". $data_str);
	
		//construct URL
		$url = "http://motivators.hcde.uw.edu:8000/render?";
		$url .= "source=".strtolower($source);
		$url .= "&time=".strtolower($scope);
		$url .= "&data=".$data_str;
		$url .= "&labels=".$label_str;
		$url .= "&start_date=".$start_date;
		$url .= "&end_date=".date('Y-m-d', strtotime("-1 days", strtotime($end_date) ));
		$url .= "&filename=".$filename;
		$url .= "&today=".$today;

		logDebug("Constructed URL:".$url);

		//call the chart renderer service
		$result = file_get_contents($url);
		logDebug("Response:".$result);

		$chart_img_url = "http://motivators.hcde.uw.edu:8000/".$filename;

		if (strcasecmp($target,"Mobile") == 0) {
			//Send the MMS with chart
			sendMMS($number, $message_text, $chart_img_url);
		} elseif (strcasecmp($target, "JSON") == 0) {
			$arr = ["number" => $number, "text" => $message_text, "image" => $chart_img_url];
			return json_encode($arr);
		}
	}
}*/

### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request in Study Manager - action:". $action);

if ($action != NULL && $action == "assignMessagesToUser") {
	logDebug("Trying to set connect to DB in assignMessagesToUser...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
	$future_days = isset($_GET['future_days']) ? $_GET['future_days'] : NULL;

	logDebug("Assigning messages for user...");
	logDebug("User ID:".$user_id);
	logDebug("Future days:".$future_days);

	//generate message slots with assignemnts
	$messages = generateMessagesForUser();  
    $message_slots = assignMessagesToSlots(($future_days*1), $messages);
	logDebug("Number of message slots: ".count($message_slots));
	
	//setDayMessageMappingForUser($user_id, $dayMessageMapping[0], $dayMessageMapping[1], $dayMessageMapping[2], $dayMessageMapping[3]);
	logDebug("Setting future day - message assignment...");
	setFutureDayMessageMappingForUser($user_id, $message_slots);

	logDebug("Messages assigned");
} elseif ($action != NULL && $action == "requestFitbitAccessApproval") {
	logDebug("Trying to sent a message to the user to request Fitbit access approval...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;

	requestFitbitAccessApproval($user_id);

	logDebug("Request access approval message sent!");
} elseif ($action != NULL && $action == "sendMessagetoUser") {
	logDebug("Trying to sent a reflective message to the user...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
	$msg_id = isset($_GET['msg_id']) ? $_GET['msg_id'] : NULL;
	$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : NULL;
	$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : NULL;
	$target = isset($_GET['target']) ? $_GET['target'] : NULL;

	logDebug("Trying to send a reflective message to user:".$user_id.",msg_id:".$msg_id.",start_date:".$start_date.",end_date:".$end_date);

	if ($target) {
		echo sendTestMessagetoUser($user_id, $msg_id, $start_date, $end_date, $target);
	} else {
		echo sendTestMessagetoUser($user_id, $msg_id, $start_date, $end_date);
	}

	logDebug("After mesage sent!");
} elseif ($action != NULL && $action == "generateActivityChart") {
	logDebug("Trying to generate activity chart for user...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
	$source = isset($_GET['source']) ? $_GET['source'] : NULL;
	$scope = isset($_GET['scope']) ? $_GET['scope'] : NULL;
	$filename = isset($_GET['filename']) ? $_GET['filename'] : NULL;
	$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : NULL;
	$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : NULL;
	$target = isset($_GET['target']) ? $_GET['target'] : NULL;

	logDebug("Trying to get activity chart for user:".$user_id.",start_date:".$start_date.",end_date:".$end_date);

	if ($target) {
		echo getUserActivityChart($user_id, $source, $scope, $start_date, $end_date, $filename, $target);
	} else {
		echo getUserActivityChart($user_id, $source, $scope, $start_date, $end_date, $filename);
	}

	logDebug("After chart generated!");
} elseif ($action != NULL && strcasecmp($action, "getMessageFollowUp") == 0) {
	logDebug("Trying to get message followup for user...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
	$msg_id = isset($_GET['msg_id']) ? $_GET['msg_id'] : NULL;
	$intent = isset($_GET['intent']) ? $_GET['intent'] : NULL;
	$original_msg = isset($_GET['original_msg']) ? $_GET['original_msg'] : NULL;

	logDebug("Getting follow up for - user_id: ". $user_id. ", msg_id:". $msg_id . ", original msg:".$original_msg.", intent:".$intent);

	echo sendTestFollowUpMessageToUser($user_id, $msg_id, $intent, $original_msg, $target = "JSON");

	logDebug("After getting follow up message!");
} elseif ($action != NULL && $action == "getReplyConfirmationMessage") {
	logDebug("Calling getReplyConfirmationMessage - Message Manager");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$user_name = isset($_GET['user_name']) ? $_GET['user_name'] : NULL;
	$original_msg = isset($_GET['original_msg']) ? $_GET['original_msg'] : NULL;

	$msg_params = getReplyConfirmationMessage($user_name, $original_msg);
	logDebug("Message params:".print_r($msg_params, TRUE));

	$json = json_encode($msg_params);
	logDebug("Message json:".$json);

	echo $json;
} elseif ($action != NULL && $action == "getDialogueCompleteMessage") {
	logDebug("Calling getDialogueCompleteMessage - Message Manager");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$user_name = isset($_GET['user_name']) ? $_GET['user_name'] : NULL;

	$msg_params = getDialogueEndThankYou($user_name);
	logDebug("Message params:".print_r($msg_params, TRUE));

	$json = json_encode($msg_params);
	logDebug("Message json:".$json);

	echo $json;
} 


?>