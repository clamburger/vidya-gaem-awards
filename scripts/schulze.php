<pre><?phpini_set("display_errors", true);$timeStart = microtime(true);function timer($msg) {  global $timeStart;    $timeEnd = microtime(true);  echo round($timeEnd - $timeStart, 2).": $msg\n";}require_once("../includes/config.php");$mysqli = new Mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_DB);// Remove all existing data//$mysqli->query("TRUNCATE TABLE `winner_cache`");// Start by getting a list of categories and all the nominees.$categories = array();$nominees = array();$query = "SELECT * FROM `categories` WHERE `Enabled` = 1 ORDER BY `Order` ASC";$result = $mysqli->query($query);while ($row = $result->fetch_assoc()) {  $categories[$row['ID']] = $row;  $nominees[$row['ID']] = array();}timer("Categories loaded.");$query = "SELECT * FROM `nominees`";$result = $mysqli->query($query);$nominees = array();while ($row = $result->fetch_assoc()) {  $nominees[$row['CategoryID']][$row['NomineeID']] = $row;}timer("Nominees loaded.");$filters = array(  "00none" => "1",);foreach ($filters as $filterName => $condition) {  // Now we can start grabbing votes.  $query = "SELECT `Preferences` FROM `votes` WHERE `CategoryID` = ? AND ($condition)";  $stmt = $mysqli->prepare($query);  $stmt->bind_param("s", $categoryID);  $query = "INSERT INTO `winner_cache` (`CategoryID`, `Filter`, `Results`, `Steps`) VALUES(?, ?, ?, ?)";  $insertStmt = $mysqli->prepare($query);  $insertStmt->bind_param("ssss", $categoryID, $filterName, $jsonResults, $jsonSteps);  // don't run over all categories yet    foreach ($categories as $categoryID => $category) {  // just run over the smallest category to test    if ($categoryID != "not-a-rehash") {      //continue;    }        echo "\n<strong>$categoryID</strong>\n";      $jsonResults = array();    $jsonSteps = array();        // Initial run to get first place    $stmt->execute();    $stmt->bind_result($preferences);    $votes = array();    while ($stmt->fetch()) {      if ($preferences != "[]") {        $votes[] = json_decode($preferences, true);      }    }    $candidates = $nominees[$categoryID];    // this should now return a multidimensional array where [x][y] is the strength of x compared to y      $result = runVoteProcess($candidates, $votes);    // need to display the array for testing instead of placing it into database    echo '<table border="1">';    foreach ($result as $key => $row) {      echo "<tr>";      echo "<th>$key</th>";      foreach ($row as $val) {        echo "<td>$val</td>";      }      $counts = array_count_values($row);      $position = $counts[0] + 1;      echo "<td><strong>No. $position</strong></td>";      echo "</tr>";    }        echo '</table>';    echo "\n";    timer("Completed category: $categoryID");  }} $stmt->close();$mysqli->close();timer("Done.");// $candidates should be an array of nominee rows// $votes should be an array of decoded preferencesfunction runVoteProcess($candidates, $votes) {  // create a matrix of pairwise preferences  $pairwise = array(); // for every nominee  $candidates2 = $candidates;  $candidates3 = $candidates2;  foreach ($candidates as $candidateX => $xInfo) {   // compare it to every other nominee   foreach ($candidates2 as $candidateY => $yInfo) {      //check you aren't comparing it to itself      if ($candidateX != $candidateY) {        // set initial matrix value - not sure if this is required       $pairwise[$candidateX][$candidateY] = 0;       // now iterate through each voter        foreach ($votes as $key => $vote) {          // check each candidate was voted for and store it in 20 otherwise         if (array_search($candidateX, $vote) === false) {            $vote[20] = $candidateX;         }          if (array_search($candidateY, $vote) === false) {            $vote[20] = $candidateY;         }          // compare the ranks - don't know the data structure well enough to guess this         if ( array_search($candidateX, $vote) < array_search($candidateY, $vote)) {            // increase the matrix value of candidateX preferred over candidateY           $pairwise[$candidateX][$candidateY]++;          }        }      }    }  }   // hopefully we should get a pairwise matrix that we can now compare strengths of strongest paths  $strengths = array();  foreach ($candidates as $i => $value) {     foreach ($candidates2 as $j => $value) {      if ($i != $j) {        if ($pairwise[$i][$j] > $pairwise[$j][$i]) {          $strengths[$i][$j] = $pairwise[$i][$j];        } else {          $strengths[$i][$j] = 0;        }      }    }  }  foreach ($candidates as $i => $value) {    foreach ($candidates2 as $j => $value) {      if ($i != $j) {        foreach ($candidates3 as $k => $value) {          if (($i != $k) && ($j != $k)) {            $strengths[$j][$k] = max($strengths[$j][$k], min($strengths[$j][$i], $strengths[$i][$k]));          }        }      }    }  }    $result = $strengths;    return $result;}