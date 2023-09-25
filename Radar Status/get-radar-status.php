<?php
$url = 'https://api.weather.gov/radar/stations';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    die("Could not fetch data from the API.");
}

$data = json_decode($response, true);
$radarSites = $data["features"];

$currentTime = new DateTime(null, new DateTimeZone('GMT')); // Current time in GMT timezone

// Radar sites to exclude
$excludeSites = ['HWPA2', 'ROCO2', 'AWPA2', 'TLKA2'];

$outputDown = "Refresh: 5\n";
$outputDown .= "Threshold: 999\n";
$outputDown .= "Title: Down Radar Site Status\n";
$outputDown .= "Font: 1, 15, 1, \"Courier New\"\n\n"; // Double break line here

$outputAlarm = "Refresh: 5\n";
$outputAlarm .= "Threshold: 999\n";
$outputAlarm .= "Title: Alarm Radar Site Status\n";
$outputAlarm .= "Font: 1, 15, 1, \"Courier New\"\n\n"; // Double break line here

foreach ($radarSites as $radarSite) {
    $id = $radarSite["properties"]["id"];

    // If this radar site is in the exclude list, skip it
    if (in_array($id, $excludeSites)) {
        continue;
    }

    $lastReceivedTime = new DateTime($radarSite["properties"]["latency"]["levelTwoLastReceivedTime"], new DateTimeZone('GMT'));
    $name = $radarSite["properties"]["name"]; // Getting the radar site name
    $lat = $radarSite["geometry"]["coordinates"][1];
    $lon = $radarSite["geometry"]["coordinates"][0];

    // Get alarm summary data from the radar site's properties
    $alarmSummary = null;
    if (isset($radarSite["properties"]["rda"]["properties"]["alarmSummary"])) {
        $alarmSummary = $radarSite["properties"]["rda"]["properties"]["alarmSummary"];
    }

    // Fetch the alarm messages
    $alarmMessages = null;
    $alarmInLast48Hours = false;
    if ($alarmSummary !== "No Alarms") {
        $alarmResponse = file_get_contents("https://api.weather.gov/radar/stations/$id/alarms", false, $context);
        if ($alarmResponse !== false) {
            $alarmData = json_decode($alarmResponse, true);
            $alarmMessages = "";
            $count = 0;
            foreach ($alarmData["@graph"] as $alarm) {
                // Convert alarm timestamp to DateTime
                $alarmTime = new DateTime($alarm['timestamp'], new DateTimeZone('GMT'));

                // Check if the alarm timestamp is within the past 48 hours
                $diffInHours = ($currentTime->getTimestamp() - $alarmTime->getTimestamp()) / 3600; // Convert seconds to hours
                if ($diffInHours <= 36) {
                    $alarmInLast48Hours = true;
                }

                if ($count >= 5) break;
                $alarmMessages .= "\\n{$alarm['message']} ({$alarm['timestamp']})";
                $count++;
            }
        }
    }

    // Calculate the difference in minutes between the last received time and the current time
    $diffInMinutes = ($currentTime->getTimestamp() - $lastReceivedTime->getTimestamp()) / 60; // Convert seconds to minutes

    // Radar is down
    if ($diffInMinutes > 15) {
        $statusText = "$id DOWN";
        $outputDown .= "Color: 255 0 0\n"; // Red color for down radars
        $outputDown .= "Text: $lat,$lon,1,\"$statusText\",\"Radar: $name\\nLast Update: {$lastReceivedTime->format('Y-m-d H:i:s')} GMT\\nAlarm: $alarmSummary"; // Include alarm summary
        if ($alarmMessages !== null) {
            $outputDown .= "$alarmMessages";
        }
        $outputDown .= "\"\n";
    }
    // Radar has alarms other than "No Alarms" and alarm in last 48 hours
    elseif ($alarmSummary !== null && $alarmSummary != "No Alarms" && $alarmInLast48Hours) {
        $statusText = "$id ALARM";
        $outputAlarm .= "Color: 255 165 0\n"; // Orange color for radars with alarms
        $outputAlarm .= "Text: $lat,$lon,1,\"$statusText\",\"Radar: $name\\nAlarm: $alarmSummary";
        if ($alarmMessages !== null) {
            $outputAlarm .= "$alarmMessages";
        }
        $outputAlarm .= "\"\n";
    }
}

file_put_contents('downRadarStatus.txt', $outputDown);
file_put_contents('alarmRadarStatus.txt', $outputAlarm);
?>
