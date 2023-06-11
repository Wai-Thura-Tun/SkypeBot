<?php

require_once "env_accessor.php";

$chatworkURL = $envAssoc["CHATWORK_DOMAIN"];
$chatApiToken = $envAssoc["CHATWORK_APITOKEN"];
$roomId = $envAssoc["ROOM_ID"];
$skypeBotId = $envAssoc["SKYPEBOT_ID"];
$skypeSecret = $envAssoc["SKYPE_SECRET"];
$microsoftURL = $envAssoc["MICROSOFT_OAUTH_DOMAIN"];
$skypeURL = $envAssoc["SKYPE_DOMAIN"];
$recepientId = $envAssoc["SKYPE_RECIPIENT_ID"];
$senderId = $envAssoc["SKYPE_SENDER_ID"];
$accessToken = "";
$roomers = [];

getRoomers($roomId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $incomingURI = $_SERVER['REQUEST_URI'];
  if (str_contains($incomingURI, "/chatwork")) {

    $rowData = file_get_contents('php://input');
    $data = json_decode($rowData, true);
    $sender = $data['webhook_event']['account_id'];
    $text = explode("\n",$data["webhook_event"]["body"])[0];
    $senderInfo = array_filter($roomers, function ($roomer) use ($sender) {
      return $roomer["id"] == $sender;
    });

    $accessMatch = [];
    $attachmentURL = "";
    preg_match('/\[download:(\d+)\](.+?)(?=\s+\(\d+(?:\.\d+)? (?:B|KB|MB|GB|TB)\)\[\/download\])/', $rowData, $accessMatch);
    if (!empty($accessMatch)) {
      $attachmentURL = getFileURL($accessMatch[1]);
    } 
    $getType = explode(".",$accessMatch[2])[1];
    $content = $text ? ": " . $text : "";
    $message = reset($senderInfo)["name"] . $content;
    $accessToken = getSkypeToken();
    sendMessage($message, $senderId, $recepientId, $accessToken, $skypeURL,$accessMatch[2], $attachmentURL, $getType);

  } else if (str_contains($incomingURI, "/skype")) {
    $rowData = file_get_contents('php://input');
    $pf = fopen("sample.txt", "w");
    fwrite($pf, $rowData);
    fclose($pf);
  }
} else {
  // Handle the case when the request method is not POST
  echo "Error: Invalid request method";
}

function getRoomers($room_id)
{
  $ch = curl_init();
  global $chatworkURL;
  global $chatApiToken;
  global $roomers;
  curl_setopt($ch, CURLOPT_URL, $chatworkURL . "/rooms/" . $room_id . "/members");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("x-chatworktoken: " . $chatApiToken));
  $rawResponse = curl_exec($ch);
  $response = json_decode($rawResponse, true);
  $accountArray = [];
  foreach ($response as $account) {
    $accountArray["id"] = $account["account_id"];
    $accountArray["name"] = $account["name"];
    $accountArray["profile"] = $account["avatar_image_url"];
    $roomers[] = $accountArray;
  }
  curl_close($ch);
}

function getSkypeToken()
{
  global $skypeBotId;
  global $skypeSecret;
  global $microsoftURL;
  $ch = curl_init($microsoftURL);
  $payload = "grant_type=client_credentials&client_id=" . $skypeBotId . "&client_secret=" . $skypeSecret . "&scope=https%3A%2F%2Fapi.botframework.com%2F.default";
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $rawResponse = curl_exec($ch);
  curl_close($ch);
  $response = json_decode($rawResponse, true);
  return $response["access_token"];
}

function sendMessage($message, $senderId, $recepientId, $accessToken, $skypeURL, $file_name, $file_url, $file_type)
{
  $skypeMessgaeURL = $skypeURL . "/conversations/" . $senderId . "/activities";
  $headers = array(
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
  );

  $messagePayload = array(
    "type" => "message",
    "text" => $message,
    "from" => array(
      "id" => $senderId,
      "name" => "ThuraBot"
    ),
    "recipient" => array(
      "id" => $recepientId,
      "name" => "Wai Thura Tun"
    ),
    "textFormat" => "markdown",
    "conversation" => array(
      "id" => $recepientId
    )
  );

  if($file_name && $file_url && $file_type) {
    $attachmentPayload = array(
      'contentType' => "image/".$file_type,
      'contentUrl' => $file_url,
      'name' => ""
    );
    $messagePayload["attachments"] = array($attachmentPayload);
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $skypeMessgaeURL);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messagePayload));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_exec($ch);
  curl_close($ch);
}

function getFileURL($file_id): String {
  global $chatApiToken;
  global $chatworkURL;
  global $roomId;
  $ch = curl_init();
  $url = $chatworkURL . "/rooms/" . $roomId . "/files/".$file_id."?create_download_url=1";
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("x-chatworktoken: ".$chatApiToken));
  $rawResponse = curl_exec($ch);
  curl_close($ch);
  $data = json_decode($rawResponse, true);
  return $data["download_url"];
  //$rawFileData = file_get_contents($data["download_url"], true);
  //$filePath = "img/".$file_name;
  //if($rawFileData != false && !file_exists($filePath)) {
  //  file_put_contents($filePath,$rawFileData);
  //}
}
