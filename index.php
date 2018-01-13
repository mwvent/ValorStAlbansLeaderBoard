<?php
error_reporting( E_ALL );
require("PoGoDB-SQLite3.php");

$page = isset( $_GET["p"] ) ? $_GET["p"] : "livescores";

switch( $page ) {
	case "livescores":
		livescores_simples();
		break;
	case "livescores_simples":
		livescores_simples();
		break;
	default:
		livescores_simples();
		break;
}

// actual pages - TODO seperate files!
// return array of arrays with elements score, lastSubTS, pogoname, isCurrentUser
	// key of main array is rank number
	//ublic function ui_thismonthscores($rankOnly = false)
function livescores_simples() {
	echo "<html><head><title>This Months Live Scores</title></head>";
	echo "<body>";
	$database = new PoGoDB_SQLite3();
	$scores = $database->ui_thismonthscores();
	$curentTime = $newerTime = new DateTime('@' .  $_SERVER['REQUEST_TIME']);
	$curentTime->setTimezone(new DateTimeZone('Europe/London'));
	echo "<table><tr><th>Rank</th><th>Name</th><th>Score</th><th>Age</th></tr>";
	$rank = 0;
	foreach( $scores as $scoreinfo ) {
		$score = $scoreinfo[ "score" ];
		$user = $scoreinfo[ "pogoname" ];
		$isCurrentUser = $scoreinfo[ "pogoname" ];
		// ignore no score
		if( is_null($score) ) continue;
		// has a score so increment rank #
		$rank++;
		// if current user store rank
		$yourrank = $isCurrentUser ? $rank : $yourrank;
		// do not add ranks over 10 to list
		if( $rank > 10 ) continue;
		// format the unix timestamp of the sub time into
		// a human redable interval since submission
		$lastSubTS = $scoreinfo[ "lastSubTS" ];
		$subTime = new DateTime('@' . $lastSubTS);
		$subTime->setTimezone(new DateTimeZone('Europe/London'));
		$subInterval = $subTime->diff($curentTime);
		$subInterval_human = "";
		// format human readable age
		if( (int)$subInterval->format('%d') > 0 ) {
			$subInterval_human = $subInterval->format('%dd ago');
		} elseif( (int)$subInterval->format('%h') > 0 ) {
			$subInterval_human = $subInterval->format('%hh ago');
		} elseif( (int)$subInterval->format('%i') > 10 ) {
			$subInterval_human = $subInterval->format('%im ago');
		} else {
			$subInterval_human = "just now";
		}
		// final string
		echo "<tr><td>" . 
			 $rank . "</td><td>" . 
			 $user . "</td><td>" . 
			 round($score,2) . " hours" . "</td><td>" . 
			 $subInterval_human . "</td></tr>";
	}
	echo "</table>";
	echo "</body>";
}
