<?php

date_default_timezone_set('UTC');

// Log file path, you can change this
$logFile = 'placefile_log.txt';

function logMessage($message) {
    global $logFile;
    file_put_contents($logFile, $message . "\n", FILE_APPEND);
}

function fetchKmlContent() {
    logMessage("Fetching KMZ content...");
    $kmzUrl = "https://water.weather.gov/ahps/download.php?data=kmz_obs";
    $kmzContent = file_get_contents($kmzUrl);
    if ($kmzContent === FALSE) {
        logMessage("Failed to fetch KMZ content.");
        return false;
    }
    $zip = new ZipArchive;
    $tmpFileName = sys_get_temp_dir() . "/temp_kmz_" . uniqid() . ".kmz";

    file_put_contents($tmpFileName, $kmzContent);
    if ($zip->open($tmpFileName) === TRUE) {
        $kmlContent = $zip->getFromName("ahps_national_obs.kml");
        $zip->close();
        unlink($tmpFileName);
        logMessage("KMZ content fetched successfully.");
        return $kmlContent;
    } else {
        unlink($tmpFileName);
        logMessage("Failed to open the KMZ file.");
        return false;
    }
}

function genPlacefile() {
    $kmlContent = fetchKmlContent();
    if ($kmlContent === false) {
        logMessage("Failed to fetch KML content.");
        return;
    }

        logMessage("Parsing KML content...");
    libxml_use_internal_errors(true); // Enable user error handling
    $xml = simplexml_load_string($kmlContent);

    if ($xml === false) {
        logMessage("Failed to parse KML content. XML errors:");
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            logMessage($error->message);
        }
        libxml_clear_errors();
        return;
    }

    // Registering the namespace
    $xml->registerXPathNamespace('kml', 'http://earth.google.com/kml/2.1');

    // Using XPath to query the Placemark elements
    $placemarks = $xml->xpath('//kml:Placemark');
    if (!$placemarks) {
        logMessage("Placemark element not found in XML. Dumping first 500 characters of content:");
        logMessage(substr($kmlContent, 0, 500));
        return;
    }

    $output = "Refresh: 5\n";
    $output .= "Threshold: 999\n";
    $output .= "Title: AHPS River Gauges\n";
    $output .= "Font: 1, 24, 0, \"Courier New\"\n\n";

    foreach ($placemarks as $placemark) {
    $description = (string) $placemark->description;
    preg_match_all('/<b>([^<]+)<\/b>\s*([^<]+)<br \/>/', $description, $matches);
    $data = array_combine($matches[1], $matches[2]);

    $observationTime = $data['UTC Observation Time:'];
    $observationTimestamp = strtotime($observationTime);

    // Check if the observation is within the last 48 hours
    if ($observationTimestamp < strtotime("-48 hours")) {
        continue; // Skip this placemark if older than 48 hours
    }

    $name = (string) $placemark->name;
    $coordinates = (string) $placemark->Point->coordinates;
    $waterLevel = floatval(trim($data['Latest Observation Value:'], " ft"));

    list($lon, $lat) = explode(',', $coordinates);
    $letter = "G";

    // Get water level stages
    $majorFloodStage = floatval(trim($data['Major Flood Stage:'], " ft")) ?: floatval(trim($data['Major Flood Flow:'], " ft"));
    $moderateFloodStage = floatval(trim($data['Moderate Flood Stage:'], " ft")) ?: floatval(trim($data['Moderate Flood Flow:'], " ft"));
    $floodStage = floatval(trim($data['Flood Stage:'], " ft")) ?: floatval(trim($data['Flood Flow:'], " ft"));
    $actionFloodStage = floatval(trim($data['Action Flood Stage:'], " ft")) ?: floatval(trim($data['Action Flood Flow:'], " ft"));

    // Get water level and flow stages
    $majorStage = floatval(trim($data['Major Flood Stage:'], " ft")) ?: floatval(trim($data['Major Flood Flow:'], " ft"));
    $moderateStage = floatval(trim($data['Moderate Flood Stage:'], " ft")) ?: floatval(trim($data['Moderate Flood Flow:'], " ft"));
    $floodStage = floatval(trim($data['Flood Stage:'], " ft")) ?: floatval(trim($data['Flood Flow:'], " ft"));
    $actionStage = floatval(trim($data['Action Flood Stage:'], " ft")) ?: floatval(trim($data['Action Flood Flow:'], " ft"));

    // Determine color based on water level or flow
    if ($majorStage > 0 && $waterLevel >= $majorStage) {
    $color = "255 0 0"; // Red for major
    } elseif ($moderateStage > 0 && $waterLevel >= $moderateStage) {
    $color = "255 165 0"; // Orange for moderate
    } elseif ($floodStage > 0 && $waterLevel >= $floodStage) {
    $color = "255 255 0"; // Yellow for minor
    } elseif ($actionStage > 0 && $waterLevel >= $actionStage) {
    $color = "0 255 0"; // Green for action stage
    } else {
    $color = "128 128 128"; // Default color
}


    $popupContent = "GAUGE REPORT";
    $keysOrder = ['NWSLID:', 'WFO:', 'Location:', 'State:', 'Lat/Long:'];

    foreach ($keysOrder as $key) {
        $value = $data[$key] ?? "";
        $trimmedValue = trim($value);
        if ($trimmedValue === "" || $trimmedValue === "Not Available" || $trimmedValue === "N/A") continue;

        $popupContent .= "\\n" . $key . " " . $trimmedValue;
    }

    $popupContent .= "\\nObservation: $waterLevel ft at " . $data['UTC Observation Time:'];

    foreach ($data as $key => $value) {
        if (in_array($key, $keysOrder) || $key === 'UTC Observation Time:' || $key === 'Latest Observation Value:') continue;

        $trimmedValue = trim($value);
        if ($trimmedValue === "" || $trimmedValue === "Not Available" || $trimmedValue === "N/A" || ($key === "Low Water Threshold:" && ($trimmedValue === "-9999 N/A" || $trimmedValue === "0 N/A"))) continue;

        $popupContent .= "\\n" . $key . " " . $trimmedValue;
    }

    $popupContent .= "\""; // Closing the content

    $output .= "Color: $color\n";
    $output .= "Text: $lat,$lon,1,\"$letter\",\"$popupContent\"\n";
}
    // Write to riverGauges.txt
    file_put_contents('riverGauges.txt', $output);
    logMessage("File written successfully.");
}

genPlacefile();

?>
