<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function genPlacefile() {
    $output = ""; // Initialize the variable

    $output .= ";Credit: adsb.one for the API\n";
    $output .= "RefreshSeconds: 15\n";
    $output .= "Threshold: 999\n";
    $output .= "Title: ADSB Aircraft (adsb.one) - Tallahassee, FL 250nm\n";

    // Add IconFile statement at the beginning
    $output .= "IconFile: 1, 32, 32, 16, 16, \"LinkTo32x32ImageHere.png\"\n\n";

    // Define the API endpoint.
    $api_endpoint = "API ENDPOINT GOES HERE"; // 250nm Tallahassee, FL

    // Fetch the data from the API
    $raw_data = file_get_contents($api_endpoint);

    // Decode the JSON response:
    $data = json_decode($raw_data, true);

    // Check for an error in the API response:
    if (isset($data['msg']) && $data['msg'] !== "No error") {
        die("Error fetching data: " . $data['msg']);
    }

    // Data from API
    foreach ($data['ac'] as $entry) {
        $lat = $entry['lat'];
        $lon = $entry['lon'];
        $flight = isset($entry['flight']) ? trim($entry['flight']) : 'N/A';
        $type = isset($entry['type']) ? $entry['type'] : 'N/A';
        $altitude_baro = isset($entry['alt_baro']) ? $entry['alt_baro'] : 'N/A';
        $altitude_geom = isset($entry['alt_geom']) ? $entry['alt_geom'] : 'N/A';
        $nav_qnh = isset($entry['nav_qnh']) ? $entry['nav_qnh'] : 'N/A';
        $gs = isset($entry['gs']) ? $entry['gs'] : 'N/A';
        $track = isset($entry['track']) ? floatval($entry['track']) : 0.0;
        $squawk = isset($entry['squawk']) ? $entry['squawk'] : 'N/A';
        $emergency = isset($entry['emergency']) ? $entry['emergency'] : 'N/A';
        $category = isset($entry['category']) ? $entry['category'] : 'N/A';
        $nav_modes = (isset($entry['nav_modes']) && is_array($entry['nav_modes'])) ? implode(", ", $entry['nav_modes']) : 'N/A';
        $version = isset($entry['version']) ? $entry['version'] : 'N/A';
        $nac_p = isset($entry['nac_p']) ? $entry['nac_p'] : 'N/A';
        $nac_v = isset($entry['nac_v']) ? $entry['nac_v'] : 'N/A';
        $messages = isset($entry['messages']) ? $entry['messages'] : 'N/A';
        $rssi = isset($entry['rssi']) ? $entry['rssi'] : 'N/A';

        $angle = floatval($track) - 90.0;
        if ($angle < 0) {
        $angle += 360;
    }

        // Icon and pop-up data for the composite object
        $output .= "Object: $lat,$lon\n"; // Start of composite object
        $output .= "Icon: 0,0,$angle,1,1,\"Flight: $flight"
        . "\\nCategory: $category"
        . "\\nBaro Altitude: $altitude_baro ft"
        . "\\nGeom Altitude: $altitude_geom ft"
        . "\\nGround Speed: $gs kts"
        . "\\nHeading: $track"
        . "\\nNav Mode(s): $nav_modes"
        . "\\nQNH: $nav_qnh hPa"
        . "\\nSquawk: $squawk"
        . "\\nEmergency: $emergency"
        . "\\nADS-B Version: $version"
        . "\\nRSSI: $rssi"
        . "\"\n";
        $output .= "Text: 0,-20,0,\"$flight\"\n"; // Position text above the icon
        $output .= "End:\n"; // End of composite object

    }

    // Write to adsb-ktlh.txt
    file_put_contents('adsb-ktlh.txt', $output);
}

genPlacefile();

?>
