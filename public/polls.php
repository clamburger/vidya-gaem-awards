<?php

    include_once(__DIR__."/../includes/php.php");
    $tpl->set('title', "Polls");
    
    set_time_limit(5);
    
    $valid = false;
    
    # Get the complete list of polls: it is a useful resource to have
    $query = "SELECT * FROM `poll_questions` ORDER BY `ID` DESC";
    $result = mysql_query($query);
    $polls = array();
    while ($row = mysql_fetch_array($result)) {
		$polls[$row['ID']] = array($row['Question'], $row['Description'], $row['Status']);
	}
    
    if (!empty($polls)) {
		$lastIndex = max(array_keys($polls));
		$pollIndex = $lastIndex;    
	}
    
    $tpl->set('create', false, true);
    
    if (isset($_GET['create']) && canDo("special")) {
		
		$tpl->set('create', true, true);
		$tpl->set('new-question', false);
		$tpl->set('new-description', false);
		$tpl->set('new-responses', false);
		$tpl->set('new-creator', $ID);
		
		# Creating a new poll
		if (isset($_POST['question'])) {
			$tpl->set('new-question', $_POST['question']);
			$tpl->set('new-description', $_POST['description']);
			$tpl->set('new-responses', $_POST['responses']);
			
			$question = userInput($_POST['question']);
			$creator = userInput($_POST['creator']);
			$description = userInput($_POST['description']);
			if (empty($question)) {
				$tpl->set('error', "You must enter in a question for the poll!");
			} else {
				$result = mysql_query("SELECT `Question` FROM `poll_questions` WHERE `Question` = '$question'");
				if (mysql_num_rows($result) && false) {
					$tpl->set('error', "A poll with that question already exists.");
				} else {
					$responses = array_values(array_filter(array_map("trim", explode("\n", $_POST['responses']))));

					if (count($responses) < 2) {
						$tpl->set('error', "You must enter at least two responses.");
					} else {
						$initialStatus = 1;
						if (!empty($description)) {
							$descSQL = "'$description'";
						} else {
							$descSQL = "NULL";
						}
						$query = "INSERT INTO `poll_questions` (`Question`, `Creator`, `Status`, `Description`) ";
						$query .= "VALUES ('$question', '$creator', $initialStatus, $descSQL)";
						mysql_query($query);
						$pollID = mysql_insert_id();
						action("new", $pollID);
						$responses = array_map("userInput", $responses);
						
						foreach ($responses as $response) {
							$query = "INSERT INTO `poll_options` (`PollID`, `Response`) VALUES ($pollID, '$response')";
							mysql_query($query);
						}
						
						storeMessage('success', "Poll successfully created! Go <a href='polls.php?id=$pollID'>check it out</a>.");
						refresh();
						
						$tpl->set('new-question', false);
						$tpl->set('new-responses', false);
						$tpl->set('new-description', false);
					}
				}
			}
		}
	
	} else if (!isset($pollIndex)) {
       # Check if there are no polls yet
		$tpl->set('poll', false, true);
		
	} else {

		if (isset($_GET['id'])) {
			if (is_numeric($_GET['id'])) {
				$pollIndex = $_GET['id'];
			}
		}
		
		# Find out if the provided ID is valid
		if (!isset($polls[$pollIndex])) {
			$pollIndex = $lastIndex;
		} else {
			$valid = true;
		}
		
		# Find out if the user has voted on this poll yet
		$query = "SELECT * FROM `poll_votes` WHERE `UserID` = '$ID' AND `PollID` = $pollIndex";
		$voted = mysql_num_rows(mysql_query($query));
		
		# They can't reset the vote if they never voted to start with
		if (!$voted) {
			$valid = false;
		}
		
		# Reset the user's vote.
		if (isset($_GET['reset']) && $valid) {
			$query = "DELETE FROM `poll_votes` WHERE `UserID` = '$ID' AND `PollID` = $pollIndex";
			mysql_query($query);
			action("reset", $pollIndex);
			$tpl->set("success", "Your vote has been reset. Don't forget to vote again!");
			$voted = 0;
		}
		
		# Submit the vote.
		if (isset($_POST['response']) && !$voted && $loggedIn) {
			$query = "SELECT * FROM `poll_options` WHERE `OptionID` = {$_POST['response']} AND `PollID` = $pollIndex";
			if (!mysql_num_rows(mysql_query($query))) {
				$tpl->set("error", "Invalid option: perhaps the poll was deleted while you were voting?");
			} else {
				$query = "INSERT INTO `poll_votes` VALUES ($pollIndex, ${_POST["response"]}, '$ID')";
				mysql_query($query);
				action("voted", $pollIndex, $_POST['response']);
				$voted = 1;
				storeMessage("success", "You have successfully voted on this poll.");
				refresh();
			}
		}
		
		# Find the question (to life, the universe and everything)
		$question = $polls[$pollIndex][0];
		$tpl->set('question', $question);
		$tpl->set('description', $polls[$pollIndex][1]);
		
		$preview = false;
		
		# Check if the user is just viewing results
		if (isset($_GET['preview']) && !$voted) {
			$voted = true;
			$preview = true;
		}
		
		$closed = !$polls[$pollIndex][2];
		
		if (!$loggedIn || $closed) {
			$voted = true;
			$preview = true;
			$canVote = false;
		} else {
			$canVote = true;
		}
		
		$tpl->set("closed", $closed);
		
		# If the user has voted...
		if ($voted) {
			
			# Look up the avaliable options for this poll.
			$query = "SELECT * FROM `poll_options` WHERE `PollID` = $pollIndex";
			$res = mysql_query($query);
			$options = array();
			while($row = mysql_fetch_array($res)){
				$options[$row["OptionID"]] = array("text"=>$row["Response"], "value"=>0);
			}
			
			# Figure out how many times each option has been voted for
			$total = mysql_fetch_array(mysql_query("SELECT COUNT(*) FROM `poll_votes` WHERE `PollID` = $pollIndex"));
			$total = intval($total[0]);
			
			$query = "SELECT COUNT(*), `OptionID` FROM `poll_votes` WHERE `PollID` = $pollIndex GROUP BY `OptionID`";
			$res = mysql_query($query);
			while($row = mysql_fetch_array($res)){
				$options[$row["OptionID"]]["count"] = $row[0];
				$options[$row["OptionID"]]["value"] = (intval($row[0]) / $total) * 100;
			}

			foreach ($options as $key => $value) {
				if (!isset($value['count'])) {
					$options[$key]['count'] = 0;
				}
			}
					
			# Get the complete list of votes for this poll.
			$votes = array();
			$query = "SELECT `UserID`, `OptionID` FROM `poll_votes` WHERE `PollID` = $pollIndex ORDER BY `OptionID` ASC, `UserID` ASC";
			$result = mysql_query($query);

			while ($row = mysql_fetch_assoc($result)) {
				$tempUserID = strtolower($row['UserID']);
				$votes[$row['OptionID']][] = $tempUserID;
			}
			
			$jsData = array();
			
			$rowspan = 1;
			foreach ($options as $data) {
				$jsData[] = "{name: \"{$data['text']}\", y: {$data['count']}}";
				$rowspan++;
			}
			
			$tpl->set("rowspan", $rowspan);
			$tpl->set("graphData", implode(",\n", $jsData));

			$votesStr = array();
					
			# Format everything so it can be displayed in the template correctly.
			foreach ($options as $ID => $data) {
				if (!isset($votes[$ID])) {
					$votes[$ID] = array();
				}
			}
			ksort($votes);
			
			foreach ($votes as $key => $peoplee) {
				$votesStr[$key] = implode(", ", $peoplee);
			}
			foreach ($votesStr as $key => $peoplee) {
				$options[$key]['people'] = $peoplee;
			}
			
			$tpl->set('options', $options);
			
		# If the user has not voted...
		} else {
			
			# Get the list of options for this poll
			$query = mysql_fetch_array(mysql_query("SELECT `Question` FROM `poll_questions` WHERE `ID` = $pollIndex"));
			$tpl->set('question', $query[0]);
			$res = mysql_query("SELECT * FROM `poll_options` WHERE `PollID` = $pollIndex");
			$options = array();
			while ($row = mysql_fetch_array($res)) {
				$options[] = array("id" => $row["OptionID"], "text"=>$row["Response"]);
			}
			$tpl->set('options', $options);
		}
		
		# Find out if there are any other polls
		$previous = array();
		foreach ($polls as $ID => $question) {
			if ($ID != $pollIndex) {
				$previous[] = array("id" => $ID, "question" => $question[0]);
			}
		}
		
		if (empty($previous)) {
			$previous = false;
		}
		
		$tpl->set('pollid', $pollIndex, true);
		$tpl->set('previous', $previous, true);
		$tpl->set('voted', $voted, true);
		$tpl->set('preview', $preview, true);
		$tpl->set('poll', true, true);
		$tpl->set('canVote', $canVote);
		
	}
	
	$createLink = false;
	if (canDo("special")) {
		$createLink = true;
	}
	
	$tpl->set('createLink', $createLink, true);
	
    fetch();
?>