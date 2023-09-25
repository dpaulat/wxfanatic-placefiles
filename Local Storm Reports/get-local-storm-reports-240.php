<?php

date_default_timezone_set('UTC');

function fetchLSRJson() { // Shows last 4 hours of LSR's
    $fourHoursAgo = date("YmdHis", strtotime("-4 hours"));
    $today = date("YmdHis");

    $jsonUrl = "https://mesonet.agron.iastate.edu/geojson/lsr.php?sts={$fourHoursAgo}&ets={$today}";
    $jsonContent = file_get_contents($jsonUrl);
    $lsrArray = json_decode($jsonContent, true);
    return $lsrArray;
}

function genPlacefile() {
    $output = "Refresh: 5\n";
    $output .= "Threshold: 999\n";
    $output .= "Title: IEM Local Storm Reports - Last 4 Hrs\n";
    $output .= "Font: 1, 24, 0, \"Courier New\"\n\n";

    $lsrs = fetchLSRJson();

    foreach ($lsrs['features'] as $lsr) {
        $properties = $lsr['properties'];
        $geometry = $lsr['geometry'];
    
        $wfo = $properties['wfo'];
        $reportType = $properties['typetext'];
        $state = $properties['st'];
        $remarks = $properties['remark'];
        $city = $properties['city'];
        $source = $properties['source'];
        $time = $properties['valid'];
        $magnitude = isset($properties['magnitude']) ? $properties['magnitude'] : null; // Extract magnitude
        $lon = $geometry['coordinates'][0];
        $lat = $geometry['coordinates'][1];

        switch ($reportType) {
            case "BLOWING DUST":
                $letter = "BD";
                $color = "100 100 0"; // Example color, replace with your preference
                break;
            case "COASTAL FLOOD":
                $letter = "CF";
                $color = "0 100 100"; // Example color, replace with your preference
                break;
            case "DEBRIS FLOW":
                $letter = "DF";
                $color = "100 0 100"; // Example color, replace with your preference
                break;
            case "FLASH FLOOD":
                $letter = "FF";
                $color = "200 200 0"; // Example color, replace with your preference
                break;
            case "FLOOD":
                $letter = "FLD";
                $color = "0 200 200"; // Example color, replace with your preference
                break;
            case "FUNNEL CLOUD":
                $letter = "FC";
                $color = "200 0 200"; // Example color, replace with your preference
                break;
            case "HAIL":
                $letter = "H";
                $color = "255 255 0"; // Yellow
                break;
            case "LANDSLIDE":
                $letter = "LS";
                $color = "100 0 0"; // Example color, replace with your preference
                break;
            case "LIGHTNING":
                $letter = "LT";
                $color = "0 100 0"; // Example color, replace with your preference
                break;
            case "NON-TSTM WND GST":
                $letter = "NTWG";
                $color = "0 0 100"; // Example color, replace with your preference
                break;
            case "RAIN":
                $letter = "RN";
                $color = "50 50 50"; // Example color, replace with your preference
                break;
            case "RIP CURRENTS":
                $letter = "RC";
                $color = "200 100 100"; // Example color, replace with your preference
                break;
            case "TORNADO":
                $letter = "T";
                $color = "255 0 0"; // Red
                break;
            case "TROPICAL CYCLONE":
                $letter = "TC";
                $color = "255 0 0"; // Red
                break;
            case "TSTM WND DMG":
                $letter = "TWD";
                $color = "200 0 0"; // Example color, replace with your preference
                break;
            case "NON-TSTM WND DMG":
                $letter = "NTWD";
                $color = "200 0 0"; // Example color, replace with your preference
                break;
            case "TSTM WND GST":
                $letter = "TWG";
                $color = "0 200 0"; // Example color, replace with your preference
                break;
            case "WATERSPOUT":
                $letter = "WS";
                $color = "0 0 200"; // Example color, replace with your preference
                break;
            case "FOG":
                $letter = "FG";
                $color = "128 128 128"; // Example color, replace with your preference
                break;
            case "EXTREME HEAT":
                $letter = "EXHT";
                $color = "255 105 180"; // Example color, replace with your preference
                break;
            case "STORM SURGE":
                $letter = "SRG";
                $color = "255 105 180"; // Example color, replace with your preference
                break;
            default:
                $letter = "O"; // Other
                $color = "128 128 128"; // Gray
                break;
        }

        $remarksWrapped = wordwrap($remarks, 50, "\n");
        $remarksLines = explode("\n", $remarksWrapped);
        $remarksFormatted = implode("\\n", $remarksLines);
    
        $output .= "Color: $color\n";
        
  // Check if magnitude is available and modify the output string accordingly
  if ($magnitude !== null && $magnitude !== "") {
    $output .= "Text: $lat,$lon,1,\"$letter\",\"$reportType REPORT\\nMagnitude: $magnitude\\nTime: $time\\nWFO: $wfo\\nState: $state\\nCity: $city\\nSource: $source\\nRemarks: $remarksFormatted\"\n";
  } else {
    $output .= "Text: $lat,$lon,1,\"$letter\",\"$reportType REPORT\\nTime: $time\\nWFO: $wfo\\nState: $state\\nCity: $city\\nSource: $source\\nRemarks: $remarksFormatted\"\n";
  }
    }

    // Write to file. You may wish to change the file name. This is what goes into your placefile manager on your desired radar software Ex: Supercell-Wx
    file_put_contents('lsrReports240.txt', $output);
}

genPlacefile();
?>
