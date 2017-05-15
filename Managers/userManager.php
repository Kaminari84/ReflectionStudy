<?php

include_once("dbManager.php");

logDebug("----USER MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);


function addUser($user_number, $user_email, $user_name, $timezone, $min_time, $max_time, $fitbit_profile_id) {
	global $conn;

	date_default_timezone_set('America/Los_Angeles');
	$today_date = date('Y-m-d H:i:s');
	//$condition = rand(0,1);

	date_default_timezone_set($timezone);
	$local_today_date = date('Y-m-d H:i:s');

	$uuid = uniqid();

	$sql = "INSERT INTO RS_user (
		id,
		number,
		e_mail,
		timezone, 
		name, 
		state, 
		m_state,  
		start_date, 
		end_date,
		local_start_date,
		local_end_date,
		min_msg_time,
		max_msg_time,
		fitbit_profile_id) VALUES 
			(\"$uuid\",
			\"$user_number\",
			\"$user_email\",
			\"$timezone\", 
			\"$user_name\", 
			\"ENROLLED\",
			\"DIALOGUE_COMPLETE\",
			\"$today_date\", 
			\"\",
			\"$local_today_date\",
			\"\",
			\"$min_time\",
			\"$max_time\",
			\"$fitbit_profile_id\")";

	logDebug("Running save SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("New record created successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}

	return $uuid;//$conn->insert_id;
}

function setUserState($user_id, $state) {
	global $conn;

	logDebug("Setting user state...");

	$sql = "UPDATE RS_user SET `state`=\"$state\" WHERE `id`=\"$user_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setUserMState($user_id, $m_state) {
	global $conn;

	logDebug("Setting user state...");

	$sql = "UPDATE RS_user SET `m_state`=\"$m_state\" WHERE `id`=\"$user_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setLastUpdate($user_id, $date) {
	global $conn;
	logDebug("Setting user last update date...");

	$sql = "UPDATE RS_user SET `last_update_date`=\"$date\" WHERE `id`=\"$user_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setFitbitLastCallTime($user_id, $date) {
	global $conn;
	logDebug("Setting user fitbit last call time...");

	$sql = "UPDATE RS_user SET `last_fitbit_call_time`=\"$date\" WHERE `id`=\"$user_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}

function setFitbitNextCallTime($user_id, $date) {
	global $conn;
	logDebug("Setting user fitbit next call time...");

	$sql = "UPDATE RS_user SET `next_fitbit_call_time`=\"$date\" WHERE `id`=\"$user_id\"";

	logDebug("Running update SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("Record updated successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}
}

### GETTERS ###

### HANDLING USER GOALS ###
function getAllUserGoals($user_id) {
	logDebug("Getting all user goals...");
	$sql = "SELECT * FROM RS_user_goal WHERE `user_id`=\"$user_id\"";

	$result = executeSimpleSelectQuery($sql);

	return $result;
}

function getMatchingUserGoal($user_id, $time_scopes) {
	logDebug("Getting matching user goals for user: ".$user_id.", match:".print_r($time_scopes, TRUE));

	$sql = "SELECT * FROM RS_user_goal WHERE `user_id`=\"$user_id\" AND `source`=\"manual\" AND `time_scope` IN (";

	#add matching time scopes
	foreach ($time_scopes as $n => $scope) {
		$sql .= "\"$scope\",";
	}
	$sql = rtrim($sql, ",");
	$sql .= ")";

	$result = executeSimpleSelectQuery($sql);
	if (count($result) > 0) {
		srand(make_seed());
		$goal_no = rand(0,count($result)-1);

		return $result[$goal_no];
	} else {
		#revert back to fitbit user goals
		$sql = str_replace("manual", "fitbit", $sql);

		$result = executeSimpleSelectQuery($sql);
		if (count($result) > 0) {
			
			srand(make_seed());
			$goal_no = rand(0,count($result)-1);

			return $result[$goal_no];
		} else {
			return [];
		}

	}

	/*$goals = [	
		[ "text" => "making sure that I am conscious of being active", "goal_scope" => "abstract" ],
		[ "text" => "keeping up with progress on running", "goal_scope" => "abstract" ],
		[ "text" => "loosing weight", "goal_scope" => "long_term" ],
		[ "text" => "geting more active and increasing your stamina", "goal_scope" => "long_term" ],
		[ "text" => "loosing weight and toning your body", "goal_scope" => "long_term" ],
				"getting more physically active",
				"being more active",
				"getting enough sleep",
				"fitting more regular exercise into your daily routine",
				"only eating when hungry, and only until full and mostly nutritionally quality foods",
				"moving more on an hour to hour basis",
				"walking more",
				"putting better things in your body",
				"maintaining activity level",
				"being more healthy",
				"sleeping well"
			 ];*/


	#srand(make_seed());
	#$goal_no = rand(0,count($goals)-1);

	#return $goals[$goal_no];
}

function addUserGoal($user_id, $source, $text, $area, $time_scope) {
	global $conn;

	logDebug("Adding new user goal: ".$text.", source:".$source.", area:".$area.", time scope:".$time_scope);

	$sql = "INSERT INTO RS_user_goal (
		user_id,
		source,
		text, 
		area, 
		time_scope) VALUES 
			(\"$user_id\",
			\"$source\",
			\"$text\",
			\"$area\", 
			\"$time_scope\")";

	logDebug("Running save SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
	    logDebug("New record created successfully");
	} else {
	    logError("Error: " . $sql . "<br>", $conn->error);
	}

	return $conn->insert_id;
}

#-- HANDLING USER GOALS --#

function getUserTimezone($user_id) {
	$timezone = 'America/Los_Angeles';

	logDebug("Getting user timezone...");
	$sql = "SELECT timezone FROM RS_user WHERE id=\"$user_id\"";

	$result = executeSimpleSelectQuery($sql)[0];
	$timezone = $result["timezone"];

	return $timezone;
}

function getUserName($user_id) {
	logDebug("Getting user name...");
	$sql = "SELECT name FROM RS_user WHERE id=\"$user_id\"";

	logDebug("getUserName: " . $sql);
	$result = executeSimpleSelectQuery($sql)[0];
	$name = $result["name"];

	return $name;
}

function getUserFitbitProfileID($user_id) {
	logDebug("Getting user fitbitID...");
	$sql = "SELECT fitbit_profile_id FROM RS_user WHERE id=\"$user_id\"";

	logDebug("getUserFitbitProfileID: " . $sql);
	$result = executeSimpleSelectQuery($sql)[0];
	$fitbit_profile_id = $result["fitbit_profile_id"];

	return $fitbit_profile_id;
}

function getFitbitLastCallTime($user_id) {
	logDebug("Getting fitbit last call time for user...");
	$sql = "SELECT last_fitbit_call_time FROM RS_user WHERE id=\"$user_id\"";

	logDebug("getFitbitLastCallTime: " . $sql);
	$result = executeSimpleSelectQuery($sql)[0];
	$last_call = $result["last_fitbit_call_time"];

	return $last_call;
}

function getFitbitNextCallTime($user_id) {
	logDebug("Getting fitbit next call time for user...");
	$sql = "SELECT next_fitbit_call_time FROM RS_user WHERE id=\"$user_id\"";

	logDebug("getFitbitNextCallTime: " . $sql);
	$result = executeSimpleSelectQuery($sql)[0];
	$next_call = $result["next_fitbit_call_time"];

	return $next_call;
}

function getUserMobileNumber($user_id) {
	logDebug("Getting user mobile number...");
	$sql = "SELECT number FROM RS_user WHERE id=\"$user_id\"";

	logDebug("getUserMobileNumber: " . $sql);
	$result = executeSimpleSelectQuery($sql)[0];
	$number = $result['number'];

	return $number;
}

function getUserByMobileNumber($number) {
	logDebug("Getting user mobile number...");
	$sql = "SELECT * FROM RS_user WHERE `number`=\"$number\"";

	logDebug("getUserByMobileNumber: " . $sql);
	$result = executeSimpleSelectQuery($sql);

	return $result;
}

function getUserMinTime($user_id) {
	logDebug("Getting user min time...");
	$sql = "SELECT min_msg_time FROM RS_user WHERE id=\"$user_id\"";

	logDebug("getUserMinMsgTime: " . $sql);
	$result = executeSimpleSelectQuery($sql)[0];
	$time = $result["min_msg_time"];

	return $time;
}

function getUserMaxTime($user_id) {
	logDebug("Getting user max time...");
	$sql = "SELECT max_msg_time FROM RS_user WHERE id=\"$user_id\"";

	logDebug("getUserMaxMsgTime: " . $sql);
	$result = executeSimpleSelectQuery($sql)[0];
	$time = $result["max_msg_time"];

	return $time;
}

function getUserStartDay($user_id) {
	date_default_timezone_set(getUserTimezone($user_id));
	$local_start_date = date("Y-m-d", time())."00:00:00";

	$sql = "SELECT MIN(local_date) as min_date FROM RS_study_log WHERE `user_id`=\"$user_id\"";

	logDebug("getUserStartDay: " . $sql);
	$result = executeSimpleSelectQuery($sql);
	if (count($result) > 0) {
		$local_start_date = $result[0]['min_date'];
	}

	return $local_start_date;
}

function getUserEndDay($user_id) {
	date_default_timezone_set(getUserTimezone($user_id));
	$local_end_date = date("Y-m-d", time())."00:00:00";

	$sql = "SELECT MAX(local_date) as max_date FROM RS_study_log WHERE `user_id`=\"$user_id\"";

	logDebug("getUserEndDay: " . $sql);
	$result = executeSimpleSelectQuery($sql);
	if (count($result) > 0) {
		$local_end_date = $result[0]['max_date'];
	}

	return $local_end_date;
}

function calcUserStudyDays($user_id) {
	$study_days = 0;
	$date_user = getUserStartDay($user_id);//getUserEndDay($user_id);
    
    if (strlen($date_user) == 0) { 
    	$date_user = date('Y-m-d ',time())."00:00:00"; 
    }
   	
   	$date_start = $date_user;
    $date_end = getUserEndDay($user_id);

    /*
        $d = 0;
        $act_date = $date_start;

        $study_days = 0;
        while (strtotime($act_date) < strtotime($date_end)) {
        	$act_date = date('Y-m-d', strtotime($d." days", strtotime($date_start)));
        	$entry_id = getUserDayStudyLogID($user_id, $act_date, false);

            if ($entry_id != -1) {
            	$count = 0;
                for ($chal = 1; $chal<=4; $chal++) {
                	$compl = getDayStudyMappingForUser($user_id, "c".$chal, $act_date);
                  	if ($compl != -1) { 
                    	$count++;
                  	} 
                }
               	if ($count>0) {
                  	$study_days++;
                }
            }
            $d++;
        }*/
    
    return $study_days;
}

function getUserList() {
	$sql = "SELECT * FROM RS_user";
	logDebug("Getting a list of users...");

	return executeSimpleSelectQuery($sql);
}

### HANDLING RESTFUL API REQUESTS ###

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

logDebug("Got request - action:". $action);

if ($action != NULL && $action == "addUser") {
	logDebug("Add User - USER MANAGER");
	logDebug("Trying to connect to DB...");
	connectToDB();

	$user_number = isset($_GET['user_number']) ? $_GET['user_number'] : NULL;
	$user_email = isset($_GET['user_email']) ? $_GET['user_email'] : NULL;
	$user_name = isset($_GET['user_name']) ? $_GET['user_name'] : NULL;
	$user_timezone = isset($_GET['user_timezone']) ? $_GET['user_timezone'] : NULL;
	$user_min_time = isset($_GET['user_min_time']) ? $_GET['user_min_time'] : NULL;
	$user_max_time = isset($_GET['user_max_time']) ? $_GET['user_max_time'] : NULL;
	$fitbit_profile_id = isset($_GET['fitbit_profile_id']) ? $_GET['fitbit_profile_id'] : NULL;

	logDebug("Adding user...");
	$user_id = addUser($user_number, $user_email, $user_name, $user_timezone, $user_min_time, $user_max_time, $fitbit_profile_id);

	print('{"user_id": "'.$user_id.'"}');
} elseif ($action != NULL && $action == "startStudyForUser") {
	logDebug("Trying to connect to DB...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;

	logDebug("Starting study for user - User ID:".$user_id);

	setUserState($user_id, "START_STUDY");
	logDebug("Study started!");

} elseif ($action != NULL && $action == "stopStudyForUser") {
	logDebug("Trying to connect to DB...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;

	logDebug("Ending study for user - User ID:".$user_id);

	setUserState($user_id, "END_STUDY");
	logDebug("Study ended!");
} elseif ($action != NULL && $action == "addUserGoal") {
	logDebug("Adding user goal...");
	connectToDB();

	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
	$text = isset($_GET['text']) ? $_GET['text'] : NULL;
	$area = isset($_GET['area']) ? $_GET['area'] : NULL;
	$time_scope = isset($_GET['time_scope']) ? $_GET['time_scope'] : NULL;

	addUserGoal($user_id, "manual", $text, $area, $time_scope);
	logDebug("Goal ended!");
}

?>