<?php
require_once("settings.php");
$response = [
	"get_started" => [
		"payload" => "welcome"
	],
	"persistent_menu" => [[
		"locale" => "default",
		"call_to_actions" => [
			[ "title" => "Submit Score", "type" => "postback", "payload" => "handleMessage_switchboard_1_submitscore" ],
//			[ "title" => "This Months Scores", "type" => "postback", "payload" => "handleMessage_switchboard_2_showscores" ],
			[ "type"=>"web_url","title"=>"This Months Scores","url"=>"https://wattz.org.uk/pogosta/valor/leaderboard/","webview_height_ratio"=>"full"],

			[ "title" => "Menu", "type" => "postback", "payload" => "welcome" ],
		]
	]]
];
$ch = curl_init('https://graph.facebook.com/v2.6/me/messenger_profile?access_token='.$accessToken);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
echo $response;
curl_close($ch);



