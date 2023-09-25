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

function haversine_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371;  // Earth radius in kilometers

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earth_radius * $c;

    return $distance;
}

function PF_LineBox(String $Title, Array $Coords, String $RGB, $thickness = 3) {
    if (empty($Coords)) {
        return ";Error: Empty coordinates for " . $Title;
    }

    // Simple linear interpolation function
    function lerp($start, $end, $fraction) {
        return ($end - $start) * $fraction + $start;
    }

    $PlacefileText = "Color: $RGB\n";
    $PlacefileText .= "Line: $thickness, 0, \"$Title\"\n";  // Use the dynamic thickness value

    $previousCoord = null;

    foreach ($Coords as $Coord) {
        $convertedCoord = LatLon($Coord);

        if ($convertedCoord !== false) {
            if ($previousCoord !== null) {
                list($lat1, $lon1) = explode(', ', $previousCoord);
                list($lat2, $lon2) = explode(', ', $convertedCoord);
                $segmentDistance = haversine_distance($lat1, $lon1, $lat2, $lon2);

                if ($segmentDistance > 0) {
                    $PlacefileText .= "  $lat1, $lon1\n";
                    $numTexts = 5;  // Adjust this number as needed
                    $textSpacing = $segmentDistance / $numTexts;
                    $coveredDistance = 0;

                    for ($i = 1; $i <= $numTexts; $i++) {
                        $fraction = $coveredDistance / $segmentDistance;
                        $interpLat = lerp($lat1, $lat2, $fraction);
                        $interpLon = lerp($lon1, $lon2, $fraction);
                        $PlacefileText .= "  Text: $interpLat, $interpLon, 1, \"$Title\"\n";
                        $coveredDistance += $textSpacing;
                    }
                }
            }

            $PlacefileText .= "  $convertedCoord\n";
            $previousCoord = $convertedCoord;
        }
    }

    $PlacefileText .= "End:\n";

    return $PlacefileText;
}

// Main script starts here
header('Content-Type: text/plain');

$BaseUrl = 'https://www.spc.noaa.gov';
$day1outlookurl = "https://www.spc.noaa.gov/products/outlook/day1otlk.html";

$readday1 = file_get_contents($day1outlookurl);

// Extract the issue time
$issueTimePattern = '/(\d{4} [APM]{2} CDT [A-Za-z]{3} [A-Za-z]{3} \d{2} \d{4})/';
preg_match($issueTimePattern, $readday1, $issueTimeMatch);

$issueTime = isset($issueTimeMatch[1]) ? $issueTimeMatch[1] : "Unknown Issue Time";

// Extract the link for the detailed outlook text
$getday1texturl = '/\/products\/outlook\/archive\/[0-9]{4}\/.{10}_[0-9]{12}\.txt/';
preg_match_all($getday1texturl, $readday1, $Day1TextLinkMatch);

$Day1TextLink = $BaseUrl . $Day1TextLinkMatch[0][0];

// Get the content of the detailed outlook text using the link
$readday1Text = file_get_contents($Day1TextLink);

// Extract the valid time from the detailed outlook text
$ValidTimePattern = '/VALID TIME ([0-9]{6}Z - [0-9]{6}Z)/';
preg_match_all($ValidTimePattern, $readday1Text, $Day1TextValidTimeMatch);

$Day1TextValidTime = isset($Day1TextValidTimeMatch[1][0]) ? "Valid Through: " . $Day1TextValidTimeMatch[1][0] : "Unknown Valid Time";

// Extract the SUMMARY section after getting the content
//$summaryPattern = '/\s*\.\.\.SUMMARY\.\.\.(.*?)\n/s'; // Flexible spaces before ...SUMMARY...
//preg_match($summaryPattern, $readday1Text, $summaryMatch);

//$summary = isset($summaryMatch[1]) ? trim($summaryMatch[1]) : "No summary found";

$PlacefileText = "Refresh: 10\n";
$PlacefileText .= "Title: SPC Day 1 Convective Outlook - " . gmdate("H:i:s") . " UTC " . gmdate("D M d") . "\n";
$PlacefileText .= "Color: 200 200 255\n";
$PlacefileText .= "Font: 1, 11, 1, \"Arial\"\n";

$readday1TextArray = explode("\n", $readday1Text);
$capturing = 0;

foreach ($readday1TextArray as $Day1Line) {
    $CatStartTextMatch = '/\.\.\. CATEGORICAL \.\.\./';
    $StartMatches = preg_match_all($CatStartTextMatch, $Day1Line, $Text);
    $CatEndTextMatch = '/&&/';
    $EndMatches = preg_match_all($CatEndTextMatch, $Day1Line, $Text);
    if ($EndMatches == 1 && $capturing == 1) {
        $capturing = 0;
        break;
    }
    if ($StartMatches == 1 && $capturing == 0) {
        $capturing = 1;
    }
    if ($capturing == 1) {
        $CatPlainText[] = $Day1Line;
    }
}

$Cats = array();
$CatProb = array();
$CatCount = -1;
$capturing = 0;

foreach ($CatPlainText as $CatLine) {
    $CatStartTextMatch = '/([MDTENHSLGTMRGLTSTM]{3,4})[ ]{3,4}/';
    $StartMatches = preg_match_all($CatStartTextMatch, $CatLine, $Text);
    if ($StartMatches == 1) {
        $capturing = 1;
        $CatCount++;
        $CatProb[$CatCount] = $Text[1][0];
    }
    if ($capturing == 1) {
        $CoordMatch = '/ ([0-9]{8})/';
        preg_match_all($CoordMatch, $CatLine, $AllMatches);
        foreach ($AllMatches[1] as $Coord) {
            $Cats[$CatCount][] = $Coord;
        }
    }
}

foreach ($Cats as $i => $CatCoords) {
    if ($CatProb[$i] === 'TSTM') continue;

    // Default settings for each category
    $defaultSettings = [
        'MRGL' => ['color' => '60 120 60', 'thickness' => 3, 'description' => 'Marginal Risk of Severe Thunderstorms'],
        'SLGT' => ['color' => '240 240 60', 'thickness' => 3, 'description' => 'Slight Risk of Severe Thunderstorms'],
        'ENH' => ['color' => '230 194 126', 'thickness' => 3, 'description' => 'Enhanced Risk of Severe Thunderstorms'],
        'MDT' => ['color' => '230 126 126', 'thickness' => 3, 'description' => 'Moderate Risk of Severe Thunderstorms'],
        'HIGH' => ['color' => '255 126 255', 'thickness' => 3, 'description' => 'High Risk of Severe Thunderstorms'],
    ];

    $category = $CatProb[$i];
    $defaultColor = $defaultSettings[$category]['color'];
    $defaultThickness = $defaultSettings[$category]['thickness'];
    $ProbabilityRing = $defaultSettings[$category]['description'];

    $categoryColorParam = isset($_GET[strtolower($category) . 'color']) ? str_replace('-', ' ', $_GET[strtolower($category) . 'color']) : $defaultColor;
    $categoryThickness = isset($_GET[strtolower($category) . 'thickness']) ? intval($_GET[strtolower($category) . 'thickness']) : $defaultThickness;

    $PlacefileText .= "\nColor: $categoryColorParam\n";
    $PlacefileText .= "Line: $categoryThickness, , \"Day 1 Convective Outlook\\n$ProbabilityRing\\nIssued at: $issueTime\\n$Day1TextValidTime\"\n"; // \nSummary: $summary\


    foreach ($CatCoords as $Coord) {
        $PlacefileText .= "  " . LatLon($Coord) . "\n";
    }

    $PlacefileText .= "End:\n";

    // Text labels
    foreach ($CatCoords as $Coord) {
        $PlacefileText .= "\nText: " . LatLon($Coord) . ", 1, \"" . $category . "\"\n";
        $PlacefileText .= "Color: $categoryColorParam\n";
    }
}

print $PlacefileText;
?>
