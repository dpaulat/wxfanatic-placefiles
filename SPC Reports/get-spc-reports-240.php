<?php
function fetchStormReports() {
    $csvFile = file_get_contents("https://www.spc.noaa.gov/climo/reports/today_raw.csv");
    $lines = explode(PHP_EOL, $csvFile);
    $array = array();
    foreach ($lines as $line) {
        $array[] = str_getcsv($line);
    }
    return $array;
}

function genPlacefile() {
    $output = "Refresh: 5\n";
    $output .= "Threshold: 999\n";
    $output .= "Title: SPC Storm Reports - Last 4 hours\n";
    $output .= "Font: 1, 15, 0, \"Courier New\"\n\n"; // Double break line here

    $reports = fetchStormReports();
    array_shift($reports); // Remove the first line which is the header of the CSV

    $currentReportType = '';
    foreach ($reports as $report) {
        if (!isset($report[1])) {
            continue; // Skip this row if index 1 does not exist
        }

        if ($report[1] == "EF_Scale" || $report[1] == "Speed(MPH)" || $report[1] == "Size(1/100in.)") {
            if ($report[1] == "EF_Scale") {
                $currentReportType = 'TORNADO';
            } elseif ($report[1] == "Speed(MPH)") {
                $currentReportType = 'WIND';
            } elseif ($report[1] == "Size(1/100in.)") {
                $currentReportType = 'HAIL';
            }
            continue;
        }

        $timeStr = isset($report[0]) ? $report[0] : '';
        $value = isset($report[1]) ? $report[1] : '';
        $location = isset($report[2]) ? $report[2] : '';
        $lat = isset($report[5]) ? $report[5] : '';
        $lon = isset($report[6]) ? $report[6] : '';
        $comments = isset($report[7]) ? $report[7] : '';

        $time = new DateTime($timeStr, new DateTimeZone('UTC')); // Convert the time string to a DateTime object in UTC
        $fourHoursAgo = new DateTime('now', new DateTimeZone('UTC'));
        $fourHoursAgo->modify('-4 hour');

        // Check if report is from the last 4 hours
        if ($time < $fourHoursAgo) {
            continue; // Skip reports older than 4 hours
        }

        if ($currentReportType == 'TORNADO') {
            $letter = 'T';
            $typeInfo = "EF-Scale: " . $value;
            $output .= "Color: 255 0 0\n"; // Red color for tornadoes
        } elseif ($currentReportType == 'HAIL') {
            $letter = 'H';
            $typeInfo = "Size: " . $value . " in.";
            $output .= "Color: 0 255 0\n"; // Green color for hail
        } elseif ($currentReportType == 'WIND') {
            $letter = 'W';
            $typeInfo = "Speed: " . $value . " mph";
            $output .= "Color: 255 165 0\n"; // Orange color for wind
        } else {
            continue;
        }

        $commentsWrapped = wordwrap($comments, 50, "\n");
        $commentsLines = explode("\n", $commentsWrapped);
        $commentsFormatted = implode("\\n", $commentsLines); // This will format the comments for the output

        $output .= "Text: $lat,$lon,1,\"$letter\",\"$currentReportType REPORT\\nTime: $timeStr UTC\\n$typeInfo\\nLocation: $location\\nComments: $commentsFormatted\"\n";
    }

    // Write to spcReports.txt
    file_put_contents('spcReports240.txt', $output);
}

genPlacefile();
?>
