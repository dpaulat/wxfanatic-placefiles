<?php

ini_set('display_errors', '0');     // Do not display any errors
error_reporting(0);                 // Turn off all error reporting

// Utility functions
function left($str, $length) {
    return substr($str, 0, $length);
}

function right($str, $length) {
    return substr($str, -$length);
}

function LatLon(String $oldnum) {
    if (strlen($oldnum) != 8) {
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

function PF_PolygonBox(String $Title, Array $Coords, String $RGBA) {
    if (empty($Coords)) {
        return ";Error: Empty coordinates for " . $Title;
    }

    $PlacefileText = ";$Title\n";
    $PlacefileText .= "Color: $RGBA\n"; // Set the RGBA color using Color: statement
    $PlacefileText .= "Polygon:\n";

    foreach ($Coords as $CoOrd) {
        $convertedCoord = LatLon($CoOrd);
        if ($convertedCoord !== false) {
            $PlacefileText .= $convertedCoord . "\n";
        }
    }

    $PlacefileText .= "End:\n";

    return $PlacefileText;
}

// Main script starts here
header('Content-Type: text/plain');

$BaseUrl = 'https://www.spc.noaa.gov';
$wwurl = "https://www.spc.noaa.gov/products/watch/";

$readww = file_get_contents($wwurl);

$getwwnum = '/<strong><a href=\"(\/products\/watch\/ww[0-9]{4}\.html)\">(.+Watch #([0-9]{1,4}))<\/a><\/strong>/';
preg_match_all($getwwnum, $readww, $WWMatches);

$PlacefileText = "Refresh: 5\nThreshold: 999\nTitle: SPC Watches - Polygons\n\n";

foreach ($WWMatches[1] as $WW) {
    $WWUrl = "$BaseUrl$WW";
    $ReadWW = file_get_contents($WWUrl);
    $WatchNameMatch = '/<title>Storm Prediction Center +(.+ Watch [0-9]{1,4})<\/title>/';
    preg_match_all($WatchNameMatch, $ReadWW, $WatchNameMatches);
    $WatchName = $WatchNameMatches[1][0];
    $LatLonMatch = '/ ([0-9]{8})/';
    preg_match_all($LatLonMatch, $ReadWW, $WWLatLonMatches);
    $GPS = $WWLatLonMatches[1];
    $GPS[] = $GPS[0];

    // Determine the color based on the watch type
    if (strpos($WatchName, 'Tornado') !== false) {
        $defaultRgb = '255-0-0';  // Default tornado color
        $rgbColorParam = isset($_GET['torcolor']) ? $_GET['torcolor'] : $defaultRgb;
        $opacity = isset($_GET['toropacity']) ? $_GET['toropacity'] : '127';  // Default 50% opacity
    } else {
        $defaultRgb = '0-0-255';  // Default severe thunderstorm color
        $rgbColorParam = isset($_GET['svrcolor']) ? $_GET['svrcolor'] : $defaultRgb;
        $opacity = isset($_GET['svropacity']) ? $_GET['svropacity'] : '127';  // Default 50% opacity
    }

    $rgbComponents = explode('-', $rgbColorParam);
    $rgbaColor = implode(' ', $rgbComponents) . " " . $opacity;  // Combine RGB with opacity

    // Generate the polygon for this watch
    $WatchPolygon = PF_PolygonBox($WatchName, $GPS, $rgbaColor);
    $PlacefileText .= "$WatchPolygon\n";
}

print($PlacefileText);

?>
