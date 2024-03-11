<?php
require('./WeatherFunctions.php');
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
$PlacefileText = "Refresh: 20
Threshold: 999
Title: Day 1 Categorical Polygon - $Day1TextValidTime
Color: 222 62 9
";

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

// Only draw the polygons here
for ($i=0; $i <= $CatCount; $i++) {
    if ($CatProb[$i] === 'TSTM') continue;

    switch ($CatProb[$i]) {
        case 'MRGL':
            $ProbabilityRing = 'Marginal';
            $RGB = "80 201 134";
            break;
        case 'SLGT':
            $ProbabilityRing = 'Slight';
            $RGB = "255 255 81";
            break;
        case 'ENH':
            $ProbabilityRing = 'Enhanced';
            $RGB = "255 192 108";
            break;
        case 'MDT':
            $ProbabilityRing = 'Moderate';
            $RGB = "255 80 80";
            break;
        case 'HIGH':
            $ProbabilityRing = 'High';
            $RGB = "255 80 255";
            break;
        default:
            $RGB = "255 255 255";
            break;
    }

    $RGBA = str_replace(" ", ",", $RGB) . ",60";
    $OutlookPolygon = PF_Polygon($ProbabilityRing, $Cats[$i], $RGBA);
    $PlacefileText .= "\n$OutlookPolygon";
}

print $PlacefileText;
?>