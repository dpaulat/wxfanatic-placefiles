<?php

function custom_log($message, $logfile = 'errors.log') {
    $date_format = 'Y-m-d H:i:s';
    $log_entry = "[" . date($date_format) . "] " . $message . "\n";
   // error_log($log_entry, 3, $logfile);
}

function saveToTxt($content, $filename = "output") {
    $filepath = __DIR__ . "/$filename.txt";
   // file_put_contents($filepath, $content);
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

    $Lat = Left($oldnum,4);
    $Lon = Right($oldnum,4);
    $Lat = Left($Lat,2) . "." . Right($Lat,2);
    $Lon = Left($Lon,2) . "." . Right($Lon,2);
    
    // Exclude placeholder coordinates
    if ($Lat == "99.99" && $Lon == "99.99") {
        return false;
    }

    If ($Lon < 30.00){
        $Lon = "-1" . $Lon;
    } else {
        $Lon = "-" . $Lon;
    }

    return $Lat . ", " . $Lon;
}

function PF_LineBox(String $Title, Array $Coords, String $RGB) {
    if (empty($Coords)) {
        custom_log("Empty coordinates provided for line box titled: " . $Title);
        return ";Error: Empty coordinates for " . $Title;
    }

    $PlacefileText = ";$Title\nColor: $RGB\nLine: 3, 0, $Title\n";
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

    // Save the PlacefileText to .txt file
    // saveToTxt($PlacefileText, $Title . "_LineBox");
    
    return $PlacefileText;
}

function PF_Polygon(String $Title, Array $Coords, String $RGBA){
    if (empty($Coords)) {
        custom_log("Empty coordinates provided for polygon titled: " . $Title);
        return ";Error: Empty coordinates for " . $Title;
    }

    $Polygon = ";$Title\nPolygon:\n";
    $firstCoord = array_shift($Coords);
    $Polygon .= LatLon($firstCoord) . ",$RGBA\n";
    
    $addedCoords = [$firstCoord => true];  // Keep track of added coordinates

foreach ($Coords as $CoOrd) {
    $convertedCoord = LatLon($CoOrd);
    if ($convertedCoord !== false && !isset($addedCoords[$CoOrd])) {  
        $Polygon .= $convertedCoord . ",$RGBA\n";
        $addedCoords[$CoOrd] = true;
    }
}

$Polygon .= LatLon($firstCoord) . ",$RGBA\n";  // Added the RGBA values
$Polygon .= "End:\n";

    // Save the Polygon text to .txt file
    // saveToTxt($Polygon, $Title . "_Polygon");
    
    return $Polygon;
}

function prob_color(string $Type, string $Percent, int $alpha = 255) {
    switch ($Type) {
        case 'Tor':
            switch ($Percent) {
                case '0.02':
                    return '121 186 122 ' . $alpha;
                case '0.05':
                    return '189 153 138 ' . $alpha;
                case '0.10':
                    return '255 228 129 ' . $alpha;
                case '0.15':
                    return '255 128 128 ' . $alpha;
                case '0.30':
                    return '255 128 255 ' . $alpha;
                case '0.45':
                    return '200 150 247 ' . $alpha;
                case '0.60':
                    return '16 78 139 ' . $alpha;
                default:
                    return '255 255 255 ' . $alpha;
            }
        case 'Wind':
            switch ($Percent) {
                case '0.05':
                    return '197 163 146 ' . $alpha;
                case '0.15':
                    return '255 235 127 ' . $alpha;
                case '0.30':
                    return '255 127 127 ' . $alpha;
                case '0.45':
                    return '255 127 255 ' . $alpha;
                case '0.60':
                    return '200 149 246 ' . $alpha;
                case 'SIGN':
                    return '0 0 0 ' . $alpha;
                default:
                    return '255 255 255 ' . $alpha;
            }
        case 'Hail':
            switch ($Percent) {
                case '0.05':
                    return '197 163 146 ' . $alpha;
                case '0.15':
                    return '255 235 127 ' . $alpha;
                case '0.30':
                    return '255 127 127 ' . $alpha;
                case '0.45':
                    return '255 127 255 ' . $alpha;
                case '0.60':
                    return '200 149 246 ' . $alpha;
                case 'SIGN':
                    return '0 0 0 ' . $alpha;
                default:
                    return '255 255 255 ' . $alpha;
            }
        default:
            return '255 255 255 ' . $alpha;
    }
}

function strip_tags_content($text, $tags = '', $invert = false) {
    if (!is_array($tags)) {
        $tags = (string) $tags;
        if ($tags === '') {
            return $text;
        }
        $tags = strtolower($tags);
        $tags = preg_replace('/[^a-z0-9,\s]/', '', $tags);
        $tags = explode(',', $tags);
        $tags = array_map('trim', $tags);
    }
    if ($invert === false) {
        return preg_replace('@<(' . implode('|', $tags) . ')(?:(?!\1\b|\b[\/\>])[\s\S])*[\/\>]@i', '', $text);
    } else {
        return preg_replace('@<([^' . implode('|', $tags) . ']+)(?:(?!\/\1\b)[\s\S])*(?:\/>|>[\s\S]*?<\/\1\s*>)@i', '', $text);
    }
}

?>
