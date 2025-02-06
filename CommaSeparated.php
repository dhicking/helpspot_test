<?php
// This sample file is used for a HelpSpot Live Lookup using a Google Sheet published as CSV.
// Replace the placeholders below with your actual Google Sheet export URL details.
// To get this URL:
//   1. Open your Google Sheet.
//   2. Select File → Publish to the web.
//   3. Choose the desired sheet and "Comma-separated values (.csv)" as the format.
//   4. Copy the generated URL.
$csvUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSQQ_pLFbgdlUI-1SBX5Ve2WlWNg36vrPRPE-8wYnVSWrnVL46DWL5f8xdac5TqCXaL7AZD2bdwyNEi/pub?gid=0&single=true&output=csv';

// Output the XML header
header('Content-type: text/xml');
echo '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";

/**
 * Fetch CSV content using cURL.
 *
 * @param string $url The URL to fetch.
 * @return string The CSV content.
 */
function fetchCsvWithCurl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Follow redirects if necessary
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        http_response_code(500);
        exit("Error fetching CSV: " . $error_msg);
    }
    curl_close($ch);
    return $result;
}

// Fetch CSV content from the Google Sheet
$csvContent = fetchCsvWithCurl($csvUrl);
if (!$csvContent) {
    http_response_code(500);
    exit("Error: Empty CSV content.");
}

// Open a memory stream to process the CSV content
$handle = fopen('php://memory', 'r+');
if (!$handle) {
    http_response_code(500);
    exit("Error opening memory stream.");
}
fwrite($handle, $csvContent);
rewind($handle);

// Read CSV content and search for matches
$matches = [];
while (($data = fgetcsv($handle, 1000, ",")) !== false) {
    // Expecting 5 columns: [0] customer ID, [1] first name, [2] last name, [3] email, [4] phone
    if (count($data) < 5) {
        continue;
    }
    if (!empty($_GET['customer_id']) && $data[0] == $_GET['customer_id']) {
        $matches[] = $data;
    } elseif (!empty($_GET['email']) && $data[3] == $_GET['email']) {
        $matches[] = $data;
    }
}
fclose($handle);

// Build XML output
echo '<livelookup version="1.0" columns="customer_id,first_name,last_name">';
if (count($matches)) {
    foreach ($matches as $person) {
        echo '<customer>';
        echo '<customer_id>' . htmlspecialchars($person[0], ENT_XML1, 'ISO-8859-1') . '</customer_id>';
        echo '<first_name>'  . htmlspecialchars($person[1], ENT_XML1, 'ISO-8859-1') . '</first_name>';
        echo '<last_name>'   . htmlspecialchars($person[2], ENT_XML1, 'ISO-8859-1') . '</last_name>';
        echo '<email>'       . htmlspecialchars($person[3], ENT_XML1, 'ISO-8859-1') . '</email>';
        echo '<phone>'       . htmlspecialchars($person[4], ENT_XML1, 'ISO-8859-1') . '</phone>';
        echo '</customer>';
    }
}
echo '</livelookup>';
?>
