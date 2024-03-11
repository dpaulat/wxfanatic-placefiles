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

function PF_LineBox(String $Title, Array $Coords, String $RGB, $thickness) {
    if (empty($Coords)) {
        return ";Error: Empty coordinates for " . $Title;
    }

    $PlacefileText = ";$Title\n\n";
    $PlacefileText .= "Color: $RGB\n"; // Set the color using Color: statement
    $PlacefileText .= "Line: $thickness, 0, \"$Title\"\n";  // Use the dynamic thickness value
    $firstCoord = array_shift($Coords);
    $PlacefileText .= LatLon($firstCoord) . "\n";

    $addedCoords = [$firstCoord => true];  // Keep track of added coordinates

    foreach ($Coords as $CoOrd) {
        $convertedCoord = LatLon($CoOrd);
        if ($convertedCoord !== false && !isset($addedCoords[$CoOrd])) {
            $PlacefileText .= $convertedCoord . "\n";
            $addedCoords[$CoOrd] = true;
        }
    }

    $PlacefileText .= LatLon($firstCoord) . "\n";
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

$PlacefileText = "Refresh: 5\nThreshold: 999\nTitle: SPC Watches - Lines\n";

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

    // Determine the color and thickness based on the watch type
    if (strpos($WatchName, 'Tornado') !== false) {
        $defaultColor = '255-0-0';
        $defaultThickness = 5;
        $rgbColorParam = isset($_GET['torcolor']) ? $_GET['torcolor'] : $defaultColor;
        $thickness = isset($_GET['torthickness']) ? intval($_GET['torthickness']) : $defaultThickness;
    } else {
        $defaultColor = '0-0-255';
        $defaultThickness = 3;
        $rgbColorParam = isset($_GET['svrcolor']) ? $_GET['svrcolor'] : $defaultColor;
        $thickness = isset($_GET['svrthickness']) ? intval($_GET['svrthickness']) : $defaultThickness;
    }

    $rgbColor = str_replace('-', ' ', $rgbColorParam);

    // Generate the line for this watch
    $WatchLine = PF_LineBox($WatchName, $GPS, $rgbColor, $thickness);
    $PlacefileText .= "$WatchLine\n";
}

print($PlacefileText);

?>
