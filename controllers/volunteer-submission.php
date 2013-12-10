<?php
$__POST = array_map("mysql_real_escape_string", $_POST);

if (!$APPLICATIONS_OPEN) {
  storeMessage("formError", "Volunteer submissions for $YEAR are closed. Feel free to come back next year.");
  header("Location: /home");
  exit();
}

if (strlen($_POST['name']) == 0 || strlen($_POST['email']) == 0 || strlen($_POST['skills']) == 0) {
	storeMessage("formError", "You must fill in all the fields.");
	header("Location: /home");
	exit();
}
	
$query = "INSERT INTO `applications` (`UserID`, `Name`, `Email`, `Interest`, `Timestamp`)";
$query .= " VALUES('$userID', '{$__POST['name']}', '{$__POST['email']}', '{$__POST['skills']}', NOW())";
$result = mysql_query($query);
	
if ($result) {
	storeMessage("formSuccess", "Your interest has been successfully registered.");
	action("application", $userID);
			
	$subject = "New application";
	$message = "Somebody has registered their interest on the /v/GA website.\n\n";
	$message .= "Name: {$_POST['name']}\n";
	$message .= "Email: {$_POST['email']}\n";
	$message .= "Interest: {$_POST['skills']}\n";
			
	$headers = "From: Vidya Gaem Awards <$EMAIL_FROM>\r\n";
			
	mail($EMAIL_TO, $subject, $message, $headers);
	
} else {
	storeMessage("formError", "An error has occurred. The form has not been saved.<br />".mysql_error());
}

header("Location: /home");
?>