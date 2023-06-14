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
$imageTypes = ["jpg", "jpeg", "png", "gif"];

getRoomers($roomId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $incomingURI = $_SERVER['REQUEST_URI'];
  if (str_contains($incomingURI, "/chatwork")) {

    $rowData = file_get_contents('php://input');
    $data = json_decode($rowData, true);
    $sender = $data['webhook_event']['account_id'];
    $rawContent = $data['webhook_event']['body'];
    $removeContentPos = strpos($rawContent, "[info][title]");
    $imagePos = strpos($rawContent,'title][preview');
    $text = $removeContentPos > 0 ? substr($rawContent, 0, $removeContentPos) : ($removeContentPos == 0 ? "" : $rawContent);

    $senderInfo = array_filter($roomers, function ($roomer) use ($sender) {
      return $roomer["id"] == $sender;
    });

    $finalContent = preg_replace('/(\[To:)\d+/','$1',$text);
    $accessMatch = [];
    $fileInfo = [];
    $getType = "";

    preg_match('/download:(\d+)/', $rawContent, $accessMatch);
    if (!empty($accessMatch)) {
      $fileInfo = getFileURL($accessMatch[1]);
      $getType = $fileInfo["extension"];
    }
    $sender = reset($senderInfo)["name"];
    $status = "";
    $accessToken = getSkypeToken();

    // Decide message statement 
    if($imagePos && $getType != "gif") {
      $status = $status . " sent an image";
    }
    else if (!$imagePos && $getType && $getType != "gif") {
      $status = $status . " uploads a file";
    }
    else if($getType == "gif"){
      $status = $status . " sent a GIF";
    }
    else {
      $status = $status . " sent a message";
    }
    $message = $status."\n".$finalContent;
    sendMessage($message, $sender, $senderId, $recepientId, $accessToken, $skypeURL, $fileInfo);

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

function sendMessage($message, $sender, $senderId, $recepientId, $accessToken, $skypeURL, $fileInfo)
{
  global $imageTypes;
  $skypeMessgaeURL = $skypeURL . "/conversations/" . $senderId . "/activities";
  $headers = array(
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
  );

  $messageContent = "**$sender**".$message;
  $messagePayload = array(
    "type" => "message",
    "text" => $messageContent,
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

  if($fileInfo["url"] && $fileInfo["extension"] ) {
    $attachmentPayload = [];

    if(in_array($fileInfo["extension"],$imageTypes)) {
      $attachmentPayload = array(
        'contentType' => "image/".$fileInfo["extension"],
        'contentUrl' => $fileInfo["url"],
        'name' => ""
      );
      $messagePayload["attachments"] = array($attachmentPayload);
    }
    else {
      $messagePayload["text"] = $messageContent."\n **Here is the link to download the file** [Click Here](".$fileInfo["url"].") \n(Notice the link is only available for 30 seconds. Sorry for inconvenience) \n";
    }
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

function getFileURL($file_id): Array {
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
  return [
    "url" => $data["download_url"],
    "name" => $data["filename"],
    "extension" => pathinfo($data["filename"],PATHINFO_EXTENSION),
    "size" => $data["filesize"]
  ];
}
