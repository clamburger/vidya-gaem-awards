<?php
$tpl->set('title', "2012 in vidya");

$query = "SELECT * FROM `2010_releases` ORDER BY `Game` ASC";
$result = mysql_query($query);

$games = array();

while ($row = mysql_fetch_assoc($result)) {
	
	foreach ($row as $key => $value) {
		if ($key == "Game") {
			$wp = urlencode(str_replace(" ", "_", $value));
			$row[$key] = "<a href='http://en.wikipedia.org/wiki/$wp'>$value</a>";
			continue;
		}
		
		if ($value == 1) {
			$class = "c-".strtolower($key);
			$row[$key] = "<strong class='yes $class'>✓</strong>";
		} else {
			$row[$key] = "";
		}
	}

	$games[] = $row;

}

$tpl->set('games', $games);
	
	
?>