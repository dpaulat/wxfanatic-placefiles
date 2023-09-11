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

    function PF_LineBox(String $Title, Array $Coords, String $RGB, $thickness = 3) {
    if (empty($Coords)) {
        return ";Error: Empty coordinates for " . $Title;
    }

    $PlacefileText = ";$Title\nColor: $RGB\nLine: $thickness, 0, $Title\n"; // Adjusted to use provided thickness
    $firstCoord = array_shift($Coords);
    $PlacefileText .= LatLon($firstCoord) . "\n";

    $addedCoords = [$firstCoord => true];

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
$day1outlookurl = "https://www.spc.noaa.gov/products/outlook/day1otlk.html";

$readday1 = file_get_contents($day1outlookurl);

$getday1texturl = '/\/products\/outlook\/archive\/[0-9]{4}\/.{10}_[0-9]{12}\.txt/';
preg_match_all($getday1texturl, $readday1, $Day1TextLinkMatch);
$Day1TextLink = $BaseUrl . $Day1TextLinkMatch[0][0];

$readday1Text = file_get_contents($Day1TextLink);

$ValidTime = '/VALID TIME [0-9]{6}Z - [0-9]{6}Z/';
preg_match_all($ValidTime, $readday1Text, $Day1TextValidTimeMatch);
$Day1TextValidTime = $Day1TextValidTimeMatch[0][0];
$PlacefileText = "Refresh: 5\nThreshold: 999\nTitle: Day 1 Categorical Outlook Lines - $Day1TextValidTime\n";

$readday1TextArray = explode("\n", $readday1Text);
$capturing = 0;
foreach ($readday1TextArray as $Day1Line) {
    $CatStartTextMatch = '/\.\.\. CATEGORICAL \.\.\./';
    $StartMatches = preg_match_all($CatStartTextMatch, $Day1Line, $Text);
    $CatEndTextMatch = '/&&/';
    $EndMatches = preg_match_all($CatEndTextMatch, $Day1Line, $Text);
    If ($EndMatches == 1 && $capturing == 1){
        $capturing = 0;
        break;
    }
    If ($capturing == 1){
        $CatPlainText[] = $Day1Line;
    }
    If ($StartMatches == 1 && $capturing == 0){
        $capturing = 1;
    }
}

$Cats = array();
$CatProb = array();
$CatCount = -1;
foreach ($CatPlainText as $CatLine) {
    $CatStartTextMatch = '/([MDTENHSLGTMRGLTSTM]{3,4})[ ]{3,4}/';
    $StartMatches = preg_match_all($CatStartTextMatch, $CatLine, $Text);
    If ($StartMatches == 1){
        $capturing = 1;
        $StartMatches = 0;
        $CatCount++;
        $CatProb[$CatCount] = $Text[1][0];
    }
    if ($capturing == 1){
        $CoordMatch = '/ ([0-9]{8})/';
        preg_match_all($CoordMatch, $CatLine, $AllMatches);
        foreach ($AllMatches[1] as $Coord){
            $Cats[$CatCount][] = $Coord;
        }
    }
}

// Only draw the boundary lines here
for ($i=0; $i <= $CatCount; $i++) {
    if ($CatProb[$i] === 'TSTM') continue;

    $defaultThickness = 3; // Default thickness for all categories

    switch ($CatProb[$i]) {
        case 'MRGL':
            $defaultColor = '127-197-127';  // Default color for MRGL
            $ProbabilityRing = 'Marginal';
            break;
        case 'SLGT':
            $defaultColor = '246-246-127';  // Default color for SLGT
            $ProbabilityRing = 'Slight';
            break;
        case 'ENH':
            $defaultColor = '230-194-127';  // Default color for ENH
            $ProbabilityRing = 'Enhanced';
            break;
        case 'MDT':
            $defaultColor = '230-127-127';  // Default color for MDT
            $ProbabilityRing = 'Moderate';
            break;
        case 'HIGH':
            $defaultColor = '255-127-255';  // Default color for HIGH
            $ProbabilityRing = 'High';
            break;
        default:
            $defaultColor = '255-255-255';  // Default color for other categories
            break;
    }

    $categoryColorParam = isset($_GET[strtolower($CatProb[$i]).'color']) ? $_GET[strtolower($CatProb[$i]).'color'] : $defaultColor;
    $rgbColor = str_replace('-', ' ', $categoryColorParam);

    $categoryThickness = isset($_GET[strtolower($CatProb[$i]).'thickness']) ? intval($_GET[strtolower($CatProb[$i]).'thickness']) : $defaultThickness;

    $OutlookBoundary = PF_LineBox($ProbabilityRing, $Cats[$i], $rgbColor, $categoryThickness);
    $PlacefileText .= "\n$OutlookBoundary";
}

print $PlacefileText;
?>
