<?php

include_once("Managers/errorReporting.php");
$logPath = "log_testConversation.txt";
$logFile = fopen($logPath, "a");

logDebug("----TEST CONVERSATION LOAD----");

include_once("Managers/userManager.php");
include_once("Managers/messageManager.php");
include_once("Managers/studyManager.php");

logDebug("Trying to connect to DB in Study...");
connectToDB();

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : NULL;
$msg_no = isset($_GET['msg_no']) ? $_GET['msg_no'] : NULL;


if ($user_id) {
	$user_name = getUserName($user_id);
}

if (strlen($user_name)==0) {
	$user_name = "ANONYMOUS";
}

$messages = getAllTestMessages();
logDebug("Got test messages:".print_r($messages, TRUE));

//If not message id indicated, select random
if ($msg_no == NULL) {
	srand(make_seed());
	$msg_no = rand(0,count($messages)-1);
}

$msg_id = $messages[$msg_no]['id'];

$filename = "img_".$user_id."_".$msg_id."_".date('Y_m_d_H_i_s').".png";

#determine the days
#$d=7;
#if (strcasecmp($messages[$msg_no]['scope'], "2weeks") == 0) {
#	$d=14;
#}
//Get ID for the message
$message_entry = getMessageForID($msg_id);

$d=7;
if (strcasecmp($message_entry['scope'], "2weeks") == 0) {
	$d=14;
}


date_default_timezone_set(getUserTimezone($user_id));
$edate = date('Y-m-d', time());
$sdate = date('Y-m-d', strtotime("-".$d." days", strtotime($edate)));
logDebug("Start date:" . $sdate . ", End date:" . $edate);

//get the initial message
$msg_params = sendTestMessageToUser($user_id, $msg_id, $sdate, $edate, $target = "Array");
$msg_img_url = "";
if (array_key_exists("image", $msg_params)) {
	$msg_img_url = $msg_params['image'];
}	
$msg_text = $msg_params['text'];

$msg_luis_url = "";
if (array_key_exists("luis_url", $msg_params)) {
	$msg_luis_url = $msg_params['luis_url'];
}



?>
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

    <style>
	    #contents {
	  		width: 300px;
	  		margin-left: auto;
	  		margin-right: auto;
	  		margin-top: 20px;
		}

		#top_bar {
			border: 1px solid #ddd;
			background-color: #eeeeee;
			width:100%;
			height:25px;
			padding-left: 5px;
			padding-top: 3px;
		}

		#bottom_bar {
			border: 1px solid #ddd;
			background-color: #eeeeee;
			width:100%;
			height:30px;
		}

		#chat_box {
			padding: 5px;
			background-color: #FAFAFA;
			width: 300px;
	  		height: 450px;
	  		border: 1px solid #ddd;
	  		overflow-y: auto;
	  		overflow: scroll;
		}

		.bot_msg {
			background-color:#ddddff; 
			width: 210px; 
			float:right;
			padding: 4px;
			margin-top: 5px;
			margin-bottom: 5px;
		}

		.user_msg {
			background-color:#ddffdd; 
			width:210px;
			float: left;
			padding: 4px;
			margin-top: 5px;
			margin-bottom: 5px;
		}

	</style>

    <script>
    	var user_states = ["SEND_INITIAL", "SEND_FOLLOW_UP", "SEND_FEEDBACK"];
    	var user_state_id = 0;

	  	$( document ).ready(function() {
	    	console.log( "ready!" );
	    	console.log( "msg no: <?= $msg_no ?>");
	    	console.log( "msg id: <?= $msg_id ?>");
	    	console.log( 'msg text: <?= $msg_text ?>');
	    	console.log( "msg img url: <?= $msg_img_url ?>");
	    	console.log( "msg luis url: <?= $msg_luis_url ?>");

	    	$("#user_message").keyup(function(event){
			    if(event.keyCode == 13){
			        $("#send_message").click();
			    }
			});

			setTimeout(function() {
			    	botMessage('<?= $user_id ?>', '<?= $msg_id ?>');
				}, 1000);

			//Math.floor((Math.random() * 14))
	  	});

	  	/*window.setInterval(function() {
		  var elem = document.getElementById('chat_box');
		  elem.scrollTop = elem.scrollHeight;
		}, 500);*/

		function progressUserState() {
			if (user_state_id+1 < user_states.length) {
				user_state_id += 1;
			}
		}

		function getUserState() {
			return user_states[user_state_id];
		}

		function getTextIntent(luis_url, text) {
			console.log("Calling the getTextIntent function with params -> luis_url:" + luis_url + ", text: " + text);
			intent = "";
			var request = $.ajax({
		        url: "Managers/luisManager.php",
		        type: "GET",
		        data: {action: "getIntent", url: luis_url, text: text},
		        dataType: "html",
		        async: false, 
		        success : function (msg)
		        {
		          console.log( "Message got ("+msg+")" );
		          var obj = JSON.parse(msg);

		          console.log("Got intent: " + obj.intent);

		          intent = obj.intent;
		  		}
		    });

		    return intent;
		}

	  	function userMessage(user_id) {
	  		console.log("In sendMessage function...")
      
    		var message = $("#user_message").val();
    		console.log("User message: " + message);

    		if (message.trim().length == 0) {
    			console.log("Nice try, empty message!");
    		} else if (getUserState() == "SEND_INITIAL") {
    			console.log("Wait for the bot");
    			alert("Please wait a moment, our bot is thinking about your activity data :)");
    		} else {
	    		var chat_box = document.getElementById("chat_box");
	    		console.log("chat_box:" + chat_box.innerHTML);
	    		chat_box.innerHTML = chat_box.innerHTML + "<div class='user_msg'>"+message+"</div>";

	    		//clear the messages
	    		$("#user_message").val("");

	    		//Scroll to contents
	    		var elem = document.getElementById('chat_box');
			  	elem.scrollTop = elem.scrollHeight;

			  	console.log("User state now: "+getUserState());
			  	if (getUserState() == "SEND_FOLLOW_UP") {
			  		//do we need to evaluate intent for this message
			  		console.log("Is intent evaluation needed?");
			  		if ("<?= $msg_luis_url ?>" != "") {
			  			//calling intent evaluation
			  			console.log("Evaluating user response intent...");
			  			intent = getTextIntent("<?= $msg_luis_url ?>", message);
			  			console.log("Intent outside: "+intent);

			  			console.log("Getting the follow-up message...");

				  		//get the follow up message
				  		setTimeout(function() {
					    	botMessage('<?= $user_id ?>', '<?= $msg_id ?>', message, intent);
						}, 1000);

				  	//there is not special logic for this message
			  		} else {
			  			console.log("No intent evaluation, matching any message...");

			  			//get the follow up message
				  		setTimeout(function() {
					    	botMessage('<?= $user_id ?>', '<?= $msg_id ?>', message, "Any");
						}, 1000);
			  		}

			  	} else {
			  		console.log("Getting the feedback confirmation message...");
				  	//ask for confirmation reply
		    		setTimeout(function() {
					    getReplyConfirmationMessage('<?= $user_name ?>', message);
					}, 1000);
		    	}
		    }
    	};

    	function getMessageFollowUp(user_id, msg_id, original_msg, intent) {
			console.log("Calling the getMessageFollowUp function with params -> msg_id:" + msg_id + ", original_msg:" + original_msg +", intent: " + intent);
			var request = $.ajax({
		        url: "Managers/studyManager.php",
		        type: "GET",
		        data: {action: "getMessageFollowUp", user_id: user_id, msg_id: msg_id, original_msg: original_msg, intent: intent},
		        dataType: "html",
		        async: true, 
		        success : function (msg)
		        {
		        	console.log( "Message got ("+msg+")" );
		          	var obj = JSON.parse(msg);

		          	console.log("Got followup message: " + obj);

		          	//Add message to the chat
		          	showBotMessage(obj.text);
		  		}
		    });
		}

    	function botMessage(user_id, msg_id, message="", intent="Any") {
    		console.log("Trying to get bot message...");
    		console.log("Message in ajax call:"+msg_id);

    		var image_url = "";
    		console.log("User state now: "+getUserState());

    		follow_up = false;
    		if (getUserState() == "SEND_FOLLOW_UP") {
    			console.log("Follow up is true in botMessage!");
    			follow_up = true;

    			getMessageFollowUp(user_id, msg_id, message, intent);

    		} else {
    			console.log("Start date: <?= $sdate ?>");
    			console.log("End date: <?= $edate ?>");

    			//initial message, not follow up
    			console.log("Initial message, not a follow up in botMessage!");

    			showBotMessage('<?= $msg_text ?>', "<?= $msg_img_url ?>");
    		}

    		progressUserState();
			console.log("User state changed to: "+getUserState());
    	}

    	function showBotMessage(text, image="") {
    		//construct message	
			msg_contents = "<div class='bot_msg'>";
			if(image != ""){
				msg_contents +=  "<img src="+image+" width=200 height=200><br />";
			}
			msg_contents += text+"</div>";

			//Add message to the chat
          	var chat_box = document.getElementById("chat_box");
          	console.log("Contents:<"+chat_box.innerHTML+">");
          	//var n = str.indexOf("welcome");
          	if (chat_box.innerHTML.indexOf("Thinking...") == 0) {
          		chat_box.innerHTML = "";
          	}
			chat_box.innerHTML += msg_contents;
	  
	        //Scroll to contents
    		var elem = document.getElementById('chat_box');
		  	elem.scrollTop = elem.scrollHeight;
		}

    	function getReplyConfirmationMessage(user_name, original_msg) {
    		console.log("Trying to get reply confirmaion message...");

		    var request = $.ajax({
		        url: "Managers/studyManager.php",
		        type: "GET",
		        data: {action: "getReplyConfirmationMessage", user_name: user_name, original_msg: original_msg},
		        dataType: "html",
		        async: true, 
		        success : function (msg)
		        {
		        	console.log( "Message got ("+msg+")" );
		          	var obj = JSON.parse(msg);

		          	//Add message to the chat
		          	var chat_box = document.getElementById("chat_box");
	    			chat_box.innerHTML = chat_box.innerHTML + "<div class='bot_msg'>"+obj.text+"</div>";

			      	//Scroll to contents
    			 	var elem = document.getElementById('chat_box');
		  			elem.scrollTop = elem.scrollHeight;
		  		}
		    });
    	}


	</script>

</head>
<body>


<div id="contents">
	<div id="top_bar">User: <?= $user_name ?></div>
	<!--<textarea id="chat_log" rows="8" cols="50"></textarea><br />-->
	<div id="chat_box">Thinking...</div>

	<div id="bottom_bar">
		<input id="user_message" type="text" size="33" name="user_message" />
		<button id="send_message" type="button" onClick="userMessage('<?= $user_id ?>');">Send</button>
	</div>
</div>


</body>
</html>

<!--

    		//Just generate the chart here
		    /*var request = $.ajax({
		        url: "Managers/studyManager.php",
		        type: "GET",
		        data: {action: "sendMessagetoUser", 
		        		user_id: user_id, 
		        		msg_id: msg_id,
		        		start_date: "<?= $sdate ?>",
		        		end_date: "<?= $edate ?>",
		        		target: "JSON"},
		        dataType: "html",
		        async: true, 
		        success : function (msg)
		        {
		          	console.log( "Message got ("+msg+")" );
		          	var obj = JSON.parse(msg);
				    
		         	//construct message	
	    			msg_contents = "<div class='bot_msg'>";
	    			if(obj.hasOwnProperty("image")){
	    				msg_contents +=  "<img src="+obj.image+" width=200 height=200><br />";
	    			}
	    			msg_contents += obj.text+"</div>";

	    			//Add message to the chat
		          	var chat_box = document.getElementById("chat_box");
	    			chat_box.innerHTML += msg_contents;
			  
			      //Scroll to contents
		    		var elem = document.getElementById('chat_box');
				  	elem.scrollTop = elem.scrollHeight;

				}
		    });*/
		   -->

<!---
console.log("Trying to get bot message...");
    		console.log("Message in ajax call:"+msg_id);
    		console.log("Start date: <?= $sdate ?>");
    		console.log("End date: <?= $edate ?>");

    		var image_url = "";
    		console.log("User state now: "+getUserState());

    		follow_up = false;
    		if (getUserState() == "SEND_FOLLOW_UP") {
    			console.log("Follow up is true in botMessage!");
    			follow_up = true;
    		}

    		var request = $.ajax({
		        url: "Managers/messageManager.php",
		        type: "GET",
		        data: {action: "getMessage", 
		        		user_id: user_id,
		        		user_name: "<?= $user_name ?>", 
		        		msg_id: msg_id,
		        		follow_up: follow_up},
		        dataType: "html",
		        async: true, 
		        success : function (msg)
		        {
		          	console.log( "Message got in getMessage ("+msg+")" );
		          	var obj = JSON.parse(msg);

		          	if(obj.source != "NONE") {
			          	//Generate the chart
			          	var request2 = $.ajax({
					        url: "Managers/studyManager.php",
					        type: "GET",
					        data: { action: "generateActivityChart", 
					        		user_id: user_id, 
					        		source: obj.source,
					        		scope: obj.scope,
					        		filename: "<?= $filename ?>",
					        		start_date: "<?= $sdate ?>",
					        		end_date: "<?= $edate ?>",
					        		target: "JSON"},
					        dataType: "html",
					        async: true, 
					        success : function (msg2)
					        {
					          	console.log( "Message got in generateActivityChart ("+msg2+")" );
					          	var obj2 = JSON.parse(msg2);
							    
					         	//construct message	
				    			image = ""
				    			if(obj2.hasOwnProperty("image")){
				    				image = obj2.image;
				    			}

				    			showBotMessage(obj.text, image);
				    			progressUserState();
				    			console.log("User state changed to: "+getUserState());
							}
					    });
					} else {
						showBotMessage(obj.text);
						progressUserState();
						console.log("User state changed to: "+getUserState());
					}
				}
		    });

-->

