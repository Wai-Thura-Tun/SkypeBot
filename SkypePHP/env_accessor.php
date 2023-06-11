<?php

$envFile = __DIR__ . "/.env";
$envRawData = file_get_contents($envFile);
$envArray = explode("\n", $envRawData);
$envAssoc = [];

foreach($envArray as $line) {
  if(empty($line)) {
    continue;
  }
  $changeFormat = explode("=", $line);
  $key = trim($changeFormat[0]);
  $value = trim($changeFormat[1]);
  $envAssoc[$key] = $value;
}
