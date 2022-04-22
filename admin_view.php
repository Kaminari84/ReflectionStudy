<?php
    // Require the bundled autoload file - the path may need to change
    // based on where you downloaded and unzipped the SDK
    require __DIR__ . '/twilio-php-master/Twilio/autoload.php';

    // Use the REST API Client to make requests to the Twilio REST API
    use Twilio\Rest\Client;

    // Your Account SID and Auth Token from twilio.com/console
    $sid = $_GET['twillio_ssid'];
    $token = $_GET['twillio_auth_token'];
    $client = new Client($sid, $token); 

    include_once("Managers/dbManager.php");
    $logPath = "log_ReflectionStudy_ADMIN.txt";
    $logFile = fopen($logPath, "a");


    include_once("Managers/userManager.php");
    include_once("Managers/fitbit_profileManager.php");
    include_once("Managers/fitbit_dataManager.php");
    include_once("Managers/fitbit_goalManager.php");
    include_once("Managers/fitbit_deviceManager.php");
    include_once("Managers/fitbit_exchangeLogger.php");
    include_once("Managers/messageManager.php");
    include_once("Managers/studyManager.php");
    include_once("Managers/responseManager.php");
    include_once("Managers/mobileManager.php");

    logDebug("----ADMIN VIEW PAGE LOAD----");
    logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

    logDebug("Trying to connect to DB in Study...");
    connectToDB();

    $allMessages = getAllMessages();
    if (count($allMessages) == 0) {
        logDebug("Generating all messages!");
        generateAllMessages();
    }

    $users = getUserList();

    $action = isset($_GET['action']) ? $_GET['action'] : NULL;
    $userID = isset($_GET['user']) ? $_GET['user'] : NULL;

    logDebug("Generating messages in admin view...");
    $messages = generateMessagesForUser();  
    $message_slots = assignMessagesToSlots(14, $messages);


    /*$arr = json_decode('{"user_id": 456}', true);
    print("USER_ID:".$arr['user_id']);*/

?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">

    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap-theme.min.css">

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>

    <!-- Latest compiled and minified JavaScript -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>

    <link rel="stylesheet" href="style.css">

<script>
  $( document ).ready(function() {
    console.log( "ready!" );
  });

  function addUser() {
    console.log("In addUser function...")
      
    var user_number = $("#user_number").val();
    console.log("User number: " + user_number);
      
    var user_email = $("#user_email").val();
    console.log("User email: " + user_email);

    var user_name = $("#user_name").val();
    console.log("User name: " + user_name);

    var user_timezone = $("#user_timezone").val();
    console.log("User timezone: " + user_timezone);

    var user_min_time = $("#user_min_time").val();
    console.log("User min time: " + user_min_time);

    var user_max_time = $("#user_max_time").val();
    console.log("User max time: " + user_max_time);

    //FITBIT
    //var user_fitbit_id = $("#user_fitbit_id").val();
    //console.log("User fitbit id: " + user_fitbit_id);

    //var user_fitbit_secret = $("#user_fitbit_secret").val();
    //console.log("User fitbit secret: " + user_fitbit_secret);

    //Add Fitbit data
    /*var request = $.ajax({
        url: "Managers/fitbit_profileManager.php",
        type: "GET",
        data: {action: "addFitbitProfile", fitbit_id: user_fitbit_id, fitbit_secret: user_fitbit_secret},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
            console.log( "Fitbit profile add success! ("+user_fitbit_id+", "+user_fitbit_secret+") -> " + msg );
            //location.reload();

            fitbit_profile_id = msg;

            console.log("Fitbit profile id added:"+fitbit_profile_id); 

        }
    });*/

    var request2 = $.ajax({
        url: "Managers/userManager.php",
        type: "GET",
        data: {action: "addUser", user_number: user_number, user_name: user_name, user_email: user_email, user_timezone: user_timezone, user_min_time: user_min_time, user_max_time: user_max_time},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
            console.log("User add success! ("+user_number+", "+user_name+") -> " + msg );

            //var obj = JSON.parse(msg);
            //console.log("Adding Fitbit prfile for User ID:" + obj.user_id);

            location.reload();
        }
    });
  }

  function startStudyForUser(user_id) {
    console.log("Trying to start study for:"+user_id);

    var request = $.ajax({
        url: "Managers/userManager.php",
        type: "GET",
        data: {action: "startStudyForUser", user_id: user_id},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Starting study success! ("+user_id+")" );
          //location.reload();
        }
      });
  }

  function stopStudyForUser(user_id) {
    console.log("Trying to stop study for:"+user_id);

    var request = $.ajax({
        url: "Managers/userManager.php",
        type: "GET",
        data: {action: "stopStudyForUser", user_id: user_id },
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Stopping study success! ("+user_id+")" );
          //location.reload();
        }
      });
  }

  function simulateMessages() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    document.location = "admin_view.php?user="+user_id+"&action=simulate_messages";
  }

  function showAssignedMessages() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    document.location = "admin_view.php?user="+user_id+"&action=show_messages";
  }

  function showFitbitProfile() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    document.location = "admin_view.php?user="+user_id+"&action=show_fitbit_profile";
  }

  function showFitbitData() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    document.location = "admin_view.php?user="+user_id+"&action=show_fitbit_data";
  }

  function showFitbitExchanges() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    document.location = "admin_view.php?user="+user_id+"&action=show_fitbit_exchanges";
  }

  function showUserGoals() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    document.location = "admin_view.php?user="+user_id+"&action=show_user_goals";
  }

  function sendMessageToUser(msg_id, start_date, end_date) {
    user_id = $('input[name=user]:checked', '#userForm').val();

    console.log("Trying to send refelctive message to:"+user_id+",msg_id:"+msg_id+",sdate:"+start_date+",edate:"+end_date);

    var request = $.ajax({
        url: "Managers/studyManager.php",
        type: "GET",
        data: {action: "sendMessagetoUser", user_id: user_id, msg_id: msg_id, start_date: start_date, end_date: end_date },
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Sent a reflective message to the user ("+user_id+")!" );
          //location.reload();
        }
      });

    //document.location = "admin_view.php?user="+user_id+"&action=show_fitbit_profile";
  }

  function sendRequestForFitbitAccessApproval() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    var request = $.ajax({
        url: "Managers/studyManager.php",
        type: "GET",
        data: {action: "requestFitbitAccessApproval", user_id: user_id },
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Sent a message to the user to get Fitbit access approval ("+user_id+")!" );
          //location.reload();
        }
      });

    document.location = "admin_view.php?user="+user_id+"&action=show_fitbit_profile";
  }

  function callAPIFitbitData() {
    user_id = $('input[name=user]:checked', '#userForm').val();
    source = "all";
    scope = "1m";

    var request = $.ajax({
        url: "Managers/fitbit_dataManager.php",
        type: "GET",
        data: {action: "callFitbitAPIForData", user_id: user_id, source: source, scope: scope },
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Called Fitbit API to get data for ("+user_id+")!" );
          //location.reload();
        }
      });

    document.location = "admin_view.php?user="+user_id+"&action=show_fitbit_data";
  }

  function callAPIFitbitDevices() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    var request = $.ajax({
        url: "Managers/fitbit_deviceManager.php",
        type: "GET",
        data: {action: "callFitbitAPIForDevices", user_id: user_id},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Called Fitbit API to get devices for ("+user_id+")!" );
          //location.reload();
        }
      });

        document.location = "admin_view.php?user="+user_id+"&action=show_fitbit_data";
  }

  function callAPIFitbitGoals() {
    user_id = $('input[name=user]:checked', '#userForm').val();

    var request = $.ajax({
        url: "Managers/fitbit_goalManager.php",
        type: "GET",
        data: {action: "callFitbitAPIForGoal", user_id: user_id, source: "all"},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Called Fitbit API to get goals for ("+user_id+")!" );
          //location.reload();
        }
      });

        document.location = "admin_view.php?user="+user_id+"&action=show_user_goals";
  }

  function assignMessages() {
    user_id = $('input[name=user]:checked', '#userForm').val()

    console.log("Trying to assign messages for user:"+user_id);

    var request = $.ajax({
        url: "Managers/studyManager.php",
        type: "GET",
        data: {action: "assignMessagesToUser", user_id: user_id, future_days: 14},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Assigning user messages success! ("+user_id+")" );
          location.reload();
        }
      });
  }

  function updateStates() {
      console.log("In updateStates function...")

      var request = $.ajax({
        url: "stateUpdateLoop.php",
        type: "GET",
        data: {},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "State update loop success!");
          location.reload();
        }
      });
  }

  function sendCustomMessage(user_number) {
    var user_id = $('input[name=user]:checked', '#userForm').val()
    var message_text = document.getElementById("user_custom_message").value;

    console.log("Trying to send custom message for user number:"+user_number+", text:"+message_text);

    var request = $.ajax({
        url: "Managers/mobileManager.php",
        type: "GET",
        data: {action: "sendCustomMessage", user_number: user_number, message_text: message_text},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Sending message to user success! ("+user_number+","+message_text+")" );
          //location.reload();
        }
      });
  }

  function addUserGoal() {
    var user_id = $('input[name=user]:checked', '#userForm').val()
    var text = $("#goal_text").val();
    var area = $("#goal_area").val();
    var time_scope = $("#goal_time_scope").val();

    console.log("Trying to add new goal for user:"+user_id+", text:"+text);

    var request = $.ajax({
        url: "Managers/userManager.php",
        type: "GET",
        data: {action: "addUserGoal", user_id: user_id, text: text, area: area, time_scope: time_scope},
        dataType: "html",
        async: true, 
        success : function (msg)
        {
          console.log( "Adding goal for user success! ("+user_id+","+text+")" );
          //location.reload();
        }
      });
  }

</script>

</head>
<body>
    <b>Test Motivators server: <img height=30 src="http://motivators.hcde.uw.edu/test.png" /></b></br>
    <b>Update states:</b><button type="button" onclick="return updateStates();">Update</button>
    <br /><br />
    <b>List of participants</b> <br />
   
    <form id="userForm" name="userForm" class="form-inline" action="admin_view.php?action=generate_messages" method="get" style="text-align: left">
         <div id="user_list">
            <table border="1px solid black" style="font-size:80%; text-align:center">
                <tr> 
                    <th style="background-color:#dddddd">N</th> 
                    <th style="background-color:#dddddd">Last update</th>
                    <th style="background-color:#dddddd">ID</th> 
                    <th style="background-color:#dddddd">Number</th> 
                    <th style="background-color:#dddddd">E-mail</th> 
                    <th style="background-color:#dddddd">Name</th>
                    <th style="background-color:#dddddd">Timezone</th> 
                    <th style="background-color:#dddddd">Study state</th> 
                    <th style="background-color:#dddddd">MSG state</th> 
                    <th style="background-color:#dddddd">Local time</th>
                    <th style="background-color:#dddddd">Min msg</th>
                    <th style="background-color:#dddddd">Max msg</th>
                    <th style="background-color:#dddddd">Fitbit ID</th>

                    <th style="background-color:#dddddd">Start</th>
                    <th style="background-color:#dddddd">Days</th>
                    <th style="background-color:#dddddd"># sent</th>
                    <th style="background-color:#dddddd"># resp</th>
                    <th style="background-color:#dddddd"># fb ex.</th>
                    <th style="background-color:#dddddd"># fb err.</th>
                    
                    <th style="background-color:#dddddd">Start</th>
                    <th style="background-color:#dddddd">Stop</th>
                </tr>
                <?php
                    foreach ($users as $n => $user) {
                    $user_id = $user["id"];
                ?>
                <tr>
                    <td><input type="radio" name="user" value="<?= $user['id'] ?>" <?= ($userID==$user['id'])?"checked":""; ?>><?= ($n+1) ?></td>
                    <td><?= $user["last_update_date"] ?></td>
                    <td><?= $user["id"] ?></td>
                    <td><?= $user["number"] ?></td>
                    <td><?= $user["e_mail"] ?></td>
                    <td><?= $user["name"] ?></td>
                    <td><?= $user["timezone"] ?></td>
                    <td><?= $user["state"] ?></td>
                    <td><?= $user["m_state"] ?></td>
                    <td>
                    <?php 
                        date_default_timezone_set($user["timezone"]);
                        echo date('m/d H:i:s');
                    ?>
                    </td>
                    <td><?= $user["min_msg_time"] ?></td>
                    <td><?= $user["max_msg_time"] ?></td>
                    <td><?= getUserFitbitProfileID($user["id"]) ?></td>

                    <!--Study start date-->
                    <td><?= date('m-d',strtotime(getUserStartDay($user_id))); ?></td>
                    <!--Study days so far-->
                    <td><?= calcUserStudyDays($user_id); ?></td>
                    <td><?= count(getMessageLogsSent($user_id)); ?></td>
                    <td><?= count(getAllUserResponses($user_id)); ?></td>
                    <td><?= count(getLastExchangesForHours($user_id, 12)); ?></td>
                    <td><?= count(getLastErrorsForHours($user_id, 12)); ?></td>

                    <td><button type="button" onclick="return startStudyForUser('<?= $user['id'] ?>');">Start</button></td>
                    <td><button type="button" onclick="return stopStudyForUser('<?= $user['id'] ?>');">Stop</button></td>
                </tr>
                <?php
                    }
                ?>
            </table>
        </div>
        <div id="message_allocation" style="background-color:#DDDDDD">
            <b>Message allocation</b><br />
            <button type="button" onclick="return showAssignedMessages();">Show messages assigned to user</button>
            <button type="button" onclick="return simulateMessages();">Simulate message assignment</button>
            <button type="button" onclick="return assignMessages();" style='background-color:#FFDDDD'>Assign messages to user</button>
            <br />
            <b>Fitbit management</b><br />
            <button type="button" onclick="return showFitbitExchanges();">Show fitbit exchanges for user</button>
            <button type="button" onclick="return showFitbitProfile();">Show fitbit profile for the user</button>
            <button type="button" onclick="return showFitbitData();">Show fitbit data for user</button>
            <br />
            <b>User profile</b><br />
            <button type="button" onclick="return showUserGoals();">Show user goals</button>
            <br />
        </div>
    </form>
    <div id="user_data" style="border: 1px solid #444444; margin-top: 5px"> 

        <?php
        if (strcasecmp($action,"simulate_messages") == 0) {
            //var_dump($allMessages);
        ?>
            <!-- Message alocation showing -->
            Total original messages: <?= count($messages) ?></br>
            <b>Generated example messages<b><br />
            <table border="1" style="font-size:80%">
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>text</th>
                    <th>cat</th>
                    <th>sub_cat</th>
                    <th>scope</th>
                    <th>source</th>
                </tr>
            <?php
              logDebug("Number of message slots: ".count($message_slots));
              foreach ($message_slots as $i => $message_id) {
                $message = getMessageForID($message_id);

                echo "<tr>";
                echo "<td>".$i."</td>";
                echo "<td>".$message['id']."</td>";
                echo "<td>".$message['text']."</td>";
                echo "<td>".$message['cat']."</td>";
                echo "<td>".$message['sub_cat']."</td>";
                echo "<td>".$message['scope']."</td>";
                echo "<td>".$message['source']."</td>";
                echo "</tr>";
              }

            ?>
          </table>

        <?php
        } elseif (strcasecmp($action, "show_messages") == 0) {
            if ($userID) {
                echo "<b>Showing messages for user: ".$userID."</b><br />";
                echo "Total original messages: ".count($messages) ."</br>";

                $date_now = strtotime(date('Y-m-d ',time())."00:00:00");
                $date_user = getUserStartDay($userID);//getUserEndDay($user_id);
                if (strlen($date_user) == 0) { $date_user = date('Y-m-d ',time())."00:00:00"; }
                $date_start = $date_user;
                $date_end = getUserEndDay($userID);

                $d = 0;
                $act_date = $date_start;
        ?>
                <table id="user-messages" border='1' style='font-size:80%'>
                    <tr>
                        <th>#</th>
                        <th>Log ID</th>
                        <th>Msg ID</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Text</th>
                        <th>Source</th>
                        <th>Scope</th>
                        <th>LUIS</th>
                        <th>Sent time</th>
                        <th>Rmd1 time</th>
                        <th>Followup time</th>
                        <th>Rmd2 time</th>
                        <th>Dial end time</th>
                        <th>Data</th>
                        <th>Chart</th>
                        <th>No.resp</th>
                        <th>No.followup resp</th>
                        <th>Simulate</th>
                    </tr>
        <?php
                while (strtotime($act_date) < strtotime("0 days", strtotime($date_end))) {
                    $act_date = date('Y-m-d', strtotime($d." days", strtotime($date_start)));
                    logDebug("Act date:".$act_date);

                    $log_entry = getUserDayStudyLog($userID, $act_date, false);
                    #var_dump($log_entry);

                    $msgID = $log_entry[0]['msg_id'];
                    //echo "MSG ID:".$msgID."<br />";
                    $message_entry = getMessageForID($msgID);
                    //var_dump($message_entry);

                    //$edate = date('Y-m-d',strtotime($act_date));
                    //$sdate = date('Y-m-d', strtotime("-7 days", strtotime($edate)));

                    $days=7;
                    if (strcasecmp($message_entry['scope'], "2weeks") == 0) {
                        $days=14;
                    }

                    date_default_timezone_set(getUserTimezone($userID));
                    $edate = date('Y-m-d', strtotime("0 days", time() ));
                    $sdate = date('Y-m-d', strtotime("-".$days." days", strtotime($edate) ));
                    //logDebug("Start date:" . $sdate . ", End date:" . $edate);

                    //$days_move = (int)date('N',strtotime($act_date)) - 1;
                    //$sdate = date('Y-m-d', strtotime("-".$days_move." days", strtotime($act_date)));
                    //$edate = date('Y-m-d', strtotime("7 days", strtotime($sdate)));

                    logDebug("Edate:". $edate);
                    logDebug("Sdate:". $sdate);

                    #responses to msg
                    $responses = getUserResponsesToMsg($userID, $log_entry[0]['id']);

                    #responses to followup
                    $responses_follow = getUserResponsesToFollowup($userID, $log_entry[0]['id']);

                    echo "<tr>";
                    echo "<td style='background-color:#dddddd'>".$d."</td>";
                    echo "<td style='background-color:#dddddd'>".$log_entry[0]['id']."</td>";
                    echo "<td style='background-color:#dddddd'>".$message_entry['id']."</td>";
                    echo "<td style='background-color:#dddddd'>".$act_date." (".date('l',strtotime($act_date)).")</td>";
                    echo "<td style='background-color:#dddddd'>".$log_entry[0]['planned_time']."</td>";
                    echo "<td>".$message_entry['text']."</td>";
                    echo "<td>".$message_entry['source']."</td>";
                    echo "<td>".$message_entry['scope']."</td>";
                    echo "<td ".((array_key_exists("luis_url", $message_entry))?"style='background-color:#33FF33'":"").">".array_key_exists("luis_url", $message_entry)."</td>";
                    echo "<td>".$log_entry[0]['msg_sent_time']."</td>";
                    echo "<td>".$log_entry[0]['rmd1_sent_time']."</td>";
                    echo "<td>".$log_entry[0]['followup_sent_time']."</td>";
                    echo "<td>".$log_entry[0]['rmd2_sent_time']."</td>";
                    echo "<td>".$log_entry[0]['dialogue_complete_sent_time']."</td>";
                    echo "<td>".str_replace(",", ", ", $log_entry[0]['chart_data'])."</td>";
                    echo "<td><a href=\"".$log_entry[0]['chart_img']."\" target=\"_blank\"><img height=\"50\" src=\"".$log_entry[0]['chart_img']."\"/></a></td>";
                    echo "<td>".count($responses)."</td>";
                    echo "<td>".count($responses_follow)."</td>";
                    echo "<td>". ($log_entry[0]['msg_sent_time'] == 0 ? '<button type="button" onclick="return sendMessageToUser('.$message_entry['id'].', \''.$sdate.'\', \''.$edate.'\');">Send</button>' : "?")."</td>";
                    echo "</tr>";
                
                    $d++;
                }
        ?>
            </table>
        <?php

            } else {
                echo "<div style='color:red; font-weight:bold'>No user selected!</div>";
            }

        ?>
        <?php
        } elseif (strcasecmp($action, "show_fitbit_exchanges") == 0) {
            echo "<b>Showing Fitbit Exchanges</b>";
            if ($userID) {
        ?>          
                <!-- Fitbit data collected for user -->
                <table id="fitbit-exchanges" border='1' style='font-size:80%;'>
                    <tr>
                        <th>ID</th>
                        <th>User_id</th>
                        <th>Date</th>
                        <th>Local date</th>
                        <th>Request</th>
                        <th>Response</th>
                        <th>isError</th>
                    </tr>

        <?php
                $fitbit_exchanges = getExchanges($userID, 50);
                foreach ($fitbit_exchanges as $row_nr => $values) {
                    echo "<tr>";
                    echo "<td>".$values['id']."</th>";
                    echo "<td>".$values['user_id']."</th>";
                    echo "<td>".$values['date']."</th>";
                    echo "<td>".$values['local_date']."</th>";
                    echo "<td>".substr($values['request'],0,100)."..."."</th>";
                    echo "<td>".substr($values['response'],0,200)."..."."</th>";
                    echo "<td>".$values['isError']."</th>";
                    echo "</tr>";
                }


        ?>
                </table>

        <?php
            } else {
                echo "<div style='color:red; font-weight:bold'>No user selected!</div>";
            }
        } elseif (strcasecmp($action, "show_fitbit_profile") == 0) {
            if ($userID) {
        ?>
                <br />
                <button type="button" onclick="return sendRequestForFitbitAccessApproval();">Send message to user to get Fitbit access approval</button>
                <br />
                <br />
        <?php
                $last_fitbit_call_time = getFitbitLastCallTime($userID);
                $next_fitbit_call_time = getFitbitNextCallTime($userID);
                $fitbit_profile_id = getUserFitbitProfileID($userID);
                $fitbit_profile_entry = getFitbitProfile($fitbit_profile_id);
                echo "<table id='fitbit-profile' border='1' style='font-size:80%; border-collapse:collapse; table-layout:fixed; width:800px;'>";
                echo "<tr><td style='width:100px'>Profile ID</td><td>".$fitbit_profile_entry['id']."</td></tr>";
                echo "<tr><td style='width:100px'>Fitbit ID</td><td>".$fitbit_profile_entry['fitbit_id']."</td></tr>";
                echo "<tr><td style='width:100px'>Consumer secret</td><td>".$fitbit_profile_entry['consumer_secret']."</td></tr>";
                echo "<tr><td style='width:100px'>Access token</td><td style='width:450px; word-wrap:break-word;'>".$fitbit_profile_entry['access_token']."</td></tr>";
                echo "<tr><td style='width:100px'>Refresh token</td><td style='width:450px; word-wrap:break-word;' >".$fitbit_profile_entry['refresh_token']."</td></tr>";
                echo "<tr><td style='width:100px'>Expires in</td><td style='width:450px; word-wrap:break-word;' >".$fitbit_profile_entry['expires_in']."</td></tr>";
                echo "<tr><td style='width:100px'>Scope</td><td style='width:450px; word-wrap:break-word;' >".$fitbit_profile_entry['scope']."</td></tr>";
                echo "<tr><td style='width:100px'>Token type</td><td style='width:450px; word-wrap:break-word;' >".$fitbit_profile_entry['token_type']."</td></tr>";
                echo "<tr><td style='width:100px'>Fitbit user id</td><td style='width:450px; word-wrap:break-word;' >".$fitbit_profile_entry['fitbit_user_id']."</td></tr>";
                
                echo "<tr><td style='width:100px'>Last call time</td><td>".$last_fitbit_call_time."</td></tr>";
                echo "<tr><td style='width:100px'>Next call time</td><td>".$next_fitbit_call_time."</td></tr>";
                echo "</table>";
            } else {
                echo "<div style='color:red; font-weight:bold'>No user selected!</div>";
            }
        } elseif (strcasecmp($action, "show_fitbit_data") == 0) {
            if ($userID) {
        ?>          
                <br />
                <button type="button" onclick="return callAPIFitbitDevices();">Request devices</button>
                <br />
                <br />
                <!-- Fitbit devices for user -->
                <table id="fitbit-data" border='1' style='font-size:80%'>
                    <tr>
                        <th>ID</th>
                        <th>Device id</th>
                        <th>Device Type</th>
                        <th>Device Version</th>
                        <th>Battery</th>
                        <th>Last sync time</th>
                    </tr>

        <?php
                $fitbit_devices = getUserDevices($userID);
                foreach ($fitbit_devices as $row_nr => $values) {
                    echo "<tr>";
                    echo "<td>".$values['id']."</th>";
                    echo "<td>".$values['device_id']."</th>";
                    echo "<td>".$values['type']."</th>";
                    echo "<td>".$values['device_version']."</th>";
                    echo "<td>".$values['battery']."</th>";
                    echo "<td>".$values['last_sync_time']."</th>";
                    echo "</tr>";
                }
        ?>
                </table>
                <br />
                <button type="button" onclick="return callAPIFitbitData();">Request data from FITBIT API</button>
                <br />
                <br />
                
                <!-- Fitbit data collected for user -->
                <table id="fitbit-data" border='1' style='font-size:80%'>
                    <tr>
                        <th>ID</th>
                        <th>Fitbit date</th>
                        <th>Steps</th>
                        <th>Calories</th>
                        <th>Distance</th>
                        <th>Floors</th>
                        <th>Heart Rate</th>
                        <th>Weight</th>
                        <th>Sleep minutes</th>
                    </tr>
                
        <?php
                $fitbit_data = getFitbitDataForUser($userID);
                foreach ($fitbit_data as $row_nr => $values) {
                    echo "<tr>";
                    echo "<td>".$values['id']."</th>";
                    echo "<td>".$values['fitbit_date']."</th>";
                    echo "<td>".$values['steps']."</th>";
                    echo "<td>".$values['calories']."</th>";
                    echo "<td>".$values['distance']."</th>";
                    echo "<td>".$values['floors']."</th>";
                    echo "<td>".$values['heart_rate']."</th>";
                    echo "<td>".$values['weight']."</th>";
                    echo "<td>".$values['sleep_minutes']."</th>";
                    echo "</tr>";
                }
        ?>
                </table>
        <?php
            } else {
                echo "<div style='color:red; font-weight:bold'>No user selected!</div>";
            }
        } elseif (strcasecmp($action, "show_user_goals") == 0) {
            if ($userID) {
        ?>          
                <br />
                <!-- Goals for user -->
                <table id="fitbit-data" border='1' style='font-size:80%'>
                    <tr>
                        <th>ID</th>
                        <th>source</th>
                        <th>text</th>
                        <th>area</th>
                        <th>time_scope</th>
                    </tr>

        <?php
                $user_goals = getAllUserGoals($userID);
                foreach ($user_goals as $row_nr => $values) {
                    echo "<tr>";
                    echo "<td>".$values['id']."</th>";
                    echo "<td>".$values['source']."</th>";
                    echo "<td>".$values['text']."</th>";
                    echo "<td>".$values['area']."</th>";
                    echo "<td>".$values['time_scope']."</th>";
                    echo "</tr>";
                }
        ?>
                </table>
                <br />
                <div id="add_goal">
                    Text:  <input id="goal_text" type="text" size="50" name="text"><br />
                    Goal Area:
                    <select id="goal_area" name="area">
                        <option value="PHYSICAL_ACTIVITY">PHYSICAL_ACTIVITY</option>
                        <option value="WEIGHT">WEIGHT</option>
                        <option value="NUTRITION">NUTRITION</option>
                        <option value="SLEEP">SLEEP</option>
                        <option value="HEALTH">HEALTH</option>
                    </select><br />
                    Goal time scope:
                    <select id="goal_time_scope" name="time_scope">
                        <option value="DAILY">DAILY</option>
                        <option value="WEEKLY">WEEKLY</option>
                        <option value="LONG_TERM">LONG_TERM</option>
                    </select><br />
                    <button type="button" onclick="return addUserGoal();">Add goal for user</button>
                </div>
                <br />
                <br />
                <b>Fitbit goals</b>
                <br />
                <button type="button" onclick="return callAPIFitbitGoals();">Request fitbit goals</button>
                <br />
                <!-- Fitbit goals for user -->
                <table id="fitbit-data" border='1' style='font-size:80%'>
                    <tr>
                        <th>ID</th>
                        <th>source</th>
                        <th>json</th>
                    </tr>

        <?php
                $fitbit_goals = getFitbitGoalForUser($userID);
                foreach ($fitbit_goals as $row_nr => $values) {
                    echo "<tr>";
                    echo "<td>".$values['id']."</th>";
                    echo "<td>".$values['source']."</th>";
                    echo "<td>".$values['json']."</th>";
                    echo "</tr>";
                }
        ?>
                </table>
                <br />
        <?php
            } else {
                echo "<div style='color:red; font-weight:bold'>No user selected!</div>";
            }
        }
        ?>
    </div>




    <!-- PERMANENT USER MESSAGE -->

    <br /><br />
    <div id="add_participant">
        <b>Add participant</b><br />
        Phone number:  <input id="user_number" type="text" name="number"><br />
        Name:  <input id="user_name" type="text" name="name"><br />
        Email:  <input id="user_email" type="text" name="email"><br />
        Min_time(HH*MM): <input id="user_min_time" type="text" name="min_time"><br />
        Max_time(HH*MM): <input id="user_max_time" type="text" name="max_time"><br />
        Timezone:  
        <select id="user_timezone" name="timezone">
            <option value="America/Anchorage"> America/Anchorage-AKDT, Seattle-1</option>
            <option value="America/Los_Angeles"> America/Los_Angeles-PDT, Seattle</option>
            <option value="America/Denver"> America/Denver-MDT, Seattle+1</option>
            <option value="America/Winnipeg"> America/Winnipeg-CDT, Seattle+2</option>
            <option value="America/New_York"> America/New_York-EDT, Seattle+3</option>
            <option value="America/Halifax"> America/Halifax-ADT, Seattle+4</option>
        </select><br />
        <a href="http://www.timeanddate.com/time/map/" target="_blank">Check timezone map</a><br />
        <!--FITBIT OAuth 2.0 Client ID: <input id="user_fitbit_id" type="text" name="fitbit_id"><br />
        FITBIT Client Secret: <input id="user_fitbit_secret" type="text" name="fitbit_secret"><br />-->
        <button type="button" onclick="return addUser();">Add user</button>
    </div>
    <br />

    <div id="sent_custom_message">
        <?php
        if ($userID != NULL) {
            $user_timezone = getUserTimezone($userID);
            $user_number = getUserMobileNumber($userID);
        ?>
            <b>Sending custom message to participant</b><br/>

            <textarea id="user_custom_message" rows="4" cols="50"></textarea><br/>
            <button type="button" onclick="return sendCustomMessage('<?= $user_number ?>');">Send message</button>
        <?php
        }
        ?>
    </div>

    <br /><br />
    <div id="user_responses">
        <b>Participant comments</b><br />
        <?php
        if ($userID != NULL) {
            $responses = getAllUserResponses($userID);
            $user_timezone = getUserTimezone($userID);
            $user_number = getUserMobileNumber($userID);
            date_default_timezone_set($user_timezone);
            echo "NR:".$user_number."<br />";
            echo "<table border=1>";
            echo "<tr>";
            echo "<th>Date</th>";
            echo "<th>Log id</th>";
            echo "<th>Body</th>";
            echo "<th>Intent</th>";
            echo "<th>Score</th>";
            echo "<th>Raw intent</th>";
            echo "</tr>";//->getIterator(0,50,array("To" => $user['number']))
            foreach ($responses as $rsp_no => $rsp_values) {
                echo "<tr>";
                echo "<td>".date("D, d M Y H:i:s",strtotime($rsp_values['local_date']))."</td>";
                echo "<td>".$rsp_values['log_id']."</td>";
                echo "<td>".$rsp_values['text']."</td>";
                echo "<td>".$rsp_values['intent']."</td>";
                echo "<td>".$rsp_values['score']."</td>";
                echo "<td>".$rsp_values['intent_raw']."</td>";
                echo "</tr>";
            }
            echo "<table>";
        }

      ?>
      <br />
    </div>

    <br />
    
    <div id="twilio_messages">
        <b>Twilio messages:</b><br />
        <?php
     
        // Loop over the list of messages and echo a property for each one
        if ($userID != NULL) {
            $exchanges = [];

        
            // Messages sent by the user
            foreach ($client->account->messages->stream($options = array('From' => $user_number, 'dateSentAfter' => "2017-05-09"), $limit = 50) as $msg) { #0,50, array('To' => '+12068760738'))  as $msg) {#->read(array("To" => "+12068760738")) as $msg) {
                 
                 $timestamp = date_format($msg->dateSent,"Y-m-d H:i:s");
                 $mediaUrl = "";
                 if(isset($msg->mediaUrl)) {
                    $mediaUrl = $msg->mediaUrl;
                 }
                 $exchange = [  "date" => $timestamp, 
                                "source" => "user", 
                                "from" => $msg->from, 
                                "to" => $msg->to, 
                                "body" => $msg->body,
                                "mediaUrl" => $mediaUrl];

                 $exchanges[] = $exchange;
            }
            // Messages sent to the user
            foreach ($client->account->messages->stream($options = array('To' => $user_number, 'dateSentAfter' => "2017-05-09"), $limit = 50) as $msg) { #0,50, array('To' => '+12068760738'))  as $msg) {#->read(array("To" => "+12068760738")) as $msg) {
                 
                 $timestamp = date_format($msg->dateSent,"Y-m-d H:i:s");
                 $mediaUrl = "";
                 if(isset($msg->mediaUrl)) {
                    $mediaUrl = $msg->mediaUrl;
                 }
                 $exchange = [  "date" => $timestamp, 
                                "source" => "system", 
                                "from" => $msg->from, 
                                "to" => $msg->to, 
                                "body" => $msg->body,
                                "mediaUrl" => $mediaUrl];
                 
                 $exchanges[] = $exchange;
            }

            echo "NR:".$user_number."<br />";
            echo "<table border=1>";
            echo "<tr>";
            echo "<th>#</th>";
            echo "<th>Date</th>";
            echo "<th>From</th>";
            echo "<th>To</th>";
            echo "<th>Body</th>";
            echo "<th>MediaUrl</th>";
            echo "</tr>";
            
            $i=0;
            $user_timezone = getUserTimezone($userID);
            rsort($exchanges);
            foreach ($exchanges as $key => $val) {
                #date in GMT
                date_default_timezone_set("Atlantic/Azores");
                $ts = strtotime($val['date']);
                #date in local
                date_default_timezone_set($user_timezone);
                $date_str = date("Y-m-d H:i:s", $ts);

                $style = "";
                if ($val['source'] == "user") {
                    $style = "style='background-color:#eeeeee'";
                }

                echo "<tr>";
                echo "<td ".$style.">" . $i . "</td>";
                echo "<td ".$style.">" . $date_str. "</td>";
                echo "<td ".$style.">" . $val['from'] . "</td>";
                echo "<td ".$style.">" . $val['to'] . "</td>";
                echo "<td ".$style.">" . $val['body'] . "</td>";
                echo "<td ".$style.">" . $val['mediaUrl'] . "</td>";
                echo "</tr>";
                
                $i = $i + 1;
            }

            /*foreach ($client->account->messages->stream($options = array('To' => $user_number, 'dateSentAfter' => "2017-04-25"), $limit = 50) as $msg) { #0,50, array('To' => '+12068760738'))  as $msg) {#->read(array("To" => "+12068760738")) as $msg) {
                 echo "<tr>";
                 echo "<td>".$i."</td>";
                 echo "<td>".date_format($msg->dateSent,"Y-m-d H:i:s")."</td>";
                 echo "<td>".$msg->from."</td>";
                 echo "<td>".$msg->to."</td>";
                 echo "<td>".$msg->body."</td>";
                 echo "</tr>";
                 $i = $i + 1;
            }
            // Messages sent by the user
            foreach ($client->account->messages->stream($options = array('From' => $user_number, 'dateSentAfter' => "2017-04-25"), $limit = 50) as $msg) { #0,50, array('To' => '+12068760738'))  as $msg) {#->read(array("To" => "+12068760738")) as $msg) {
                 echo "<tr>";
                 echo "<td style='background-color:#eeeeee'>".$i."</td>";
                 echo "<td style='background-color:#eeeeee'>".date_format($msg->dateSent,"Y-m-d H:i:s")."</td>";
                 echo "<td style='background-color:#eeeeee'>".$msg->from."</td>";
                 echo "<td style='background-color:#eeeeee'>".$msg->to."</td>";
                 echo "<td style='background-color:#eeeeee'>".$msg->body."</td>";
                 echo "</tr>";
                 $i = $i + 1;
            }*/

            echo "<table>";
        }

        ?>

    </div>

</body>
</html>
