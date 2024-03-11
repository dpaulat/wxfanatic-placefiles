<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Utility functions

function custom_log($message, $logfile = 'errors.log') {
    $date_format = 'Y-m-d H:i:s';
    $log_entry = "[" . date($date_format) . "] " . $message . "\n";
    // error_log($log_entry, 3, $logfile);
}

function left($str, $length) {
    return substr($str, 0, $length);
}

function right($str, $length) {
    return substr($str, -$length);
}

function LatLon(String $oldnum) {
    if (strlen($oldnum) != 8) {
        custom_log("Invalid coordinate format: " . $oldnum);
        return false;  // Return false for invalid data
    }

    $Lat = left($oldnum, 4);
    $Lon = right($oldnum, 4);
    $Lat = left($Lat, 2) . "." . right($Lat, 2);
    $Lon = left($Lon, 2) . "." . right($Lon, 2);

    // Exclude placeholder coordinates
    if ($Lat == "99.99" && $Lon == "99.99") {
        return false;
    }

    If ($Lon < 30.00) {
        $Lon = "-1" . $Lon;
    } else {
        $Lon = "-" . $Lon;
    }

    return $Lat . ", " . $Lon;
}

// Main script starts here

header('Content-Type: text/plain');

$BaseUrl = 'https://www.spc.noaa.gov';
$mcdurl = "https://www.spc.noaa.gov/products/md/";

// Get the color and thickness from the URL
$rgbColorParam = isset($_GET['color']) ? $_GET['color'] : '255-140-0';
$rgbColor = str_replace('-', ' ', $rgbColorParam); // Replace hyphens with spaces
echo "Parsed Color: $rgbColor\n";  // Debugging
$thickness = isset($_GET['thickness']) ? intval($_GET['thickness']) : 5; // Default thickness if not provided

$PlacefileText = "Refresh: 5
Threshold: 999
Title: SPC Mesoscale Discussion - Lines
Color: $rgbColor
\n";  // added "\n" for an additional newline

$readmcd = file_get_contents($mcdurl);

$getmcdnum = '/<strong><a href=\"(\/products\/md\/md[0-9]{4}\.html)\">(Mesoscale Discussion #([0-9]{1,4}))<\/a><\/strong>/';
preg_match_all($getmcdnum, $readmcd, $MDMatches);

foreach ($MDMatches[1] as $MD) {
    $MDUrl = "$BaseUrl$MD";
    $ReadMD = file_get_contents($MDUrl);

    $LatLonMatch = '/ ([0-9]{8})/';
    preg_match_all($LatLonMatch, $ReadMD, $MDLatLonMatches);
    $GPS = $MDLatLonMatches[1];

    $popupContent = [];
    $MDFileArray = explode("\n", $ReadMD);

    $capturingSummary = false; // A flag to determine if we are capturing summary content

    foreach ($MDFileArray as $line) {
        $line = strip_tags(trim($line));
        if (empty($line)) continue;  // Skip empty lines

        // Check if the line starts with "DISCUSSION" and exit the loop if it does
        if (strpos($line, "DISCUSSION...") === 0) {
            break;
        }

        // Add the "Mesoscale Discussion #" title to $popupContent only once
        if (strpos($line, "Mesoscale Discussion") !== false && empty($popupContent)) {
            $popupContent[] = $line;
            continue;
        }

        if (strpos($line, "Areas affected") !== false || strpos($line, "Concerning") !== false || strpos($line, "Valid") !== false || strpos($line, "Probability of Watch Issuance") !== false) {
            $popupContent[] = $line;
        }

        if (strpos($line, "SUMMARY") !== false) {
            $capturingSummary = true; // Start capturing summary content
        }

        // If we are capturing summary content, keep adding lines
        if ($capturingSummary) {
            $popupContent[] = $line;
        }
    }

    // Add a newline character after each element
    $popupText = implode("\\n", $popupContent);

    if (count($GPS) > 1) {
        $PlacefileText .= ";MD Number: $MD\n";
        $PlacefileText .= "Line: 5, 0, \"" . $popupText . "\"\n";
        foreach ($GPS as $coord) {
            $PlacefileText .= LatLon($coord) . "\n";
        }
        $PlacefileText .= "End:\n";
    }
}

print($PlacefileText);

?>
