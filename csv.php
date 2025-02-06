<?php
// Start output buffering to capture any unwanted output.
ob_start();

// Suppress error display to prevent any stray output.
error_reporting(0);
ini_set('display_errors', '0');

// Replace these placeholders with your actual Google Sheet export URL details.
$csvUrl = 'https://docs.google.com/spreadsheets/d/YOUR_SPREADSHEET_ID/export?format=csv&id=YOUR_SPREADSHEET_ID&gid=YOUR_GID';

// Set the proper XML header.
header('Content-Type: text/xml; charset=UTF-8');

// Clear any buffered output to ensure nothing precedes the XML declaration.
ob_clean();

// Output the XML declaration.
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

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

// Fetch CSV content from the Google Sheet.
$csvContent = fetchCsvWithCurl($csvUrl);
if (!$csvContent) {
    http_response_code(500);
    exit("Error: Empty CSV content.");
}

// Remove a BOM if present.
if (substr($csvContent, 0, 3) === "\xEF\xBB\xBF") {
    $csvContent = substr($csvContent, 3);
}

// Open a memory stream for the CSV content.
$handle = fopen('php://memory', 'r+');
if (!$handle) {
    http_response_code(500);
    exit("Error opening memory stream.");
}
fwrite($handle, $csvContent);
rewind($handle);

// Process the CSV content to find matches.
// Expected CSV columns: [0] customer ID, [1] first name, [2] last name, [3] email, [4] phone.
$matches = array();
while (($data = fgetcsv($handle, 1000, ",")) !== false) {
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

// Build XML output.
echo '<livelookup version="1.0" columns="customer_id,first_name,last_name">';
if (count($matches)) {
    foreach ($matches as $person) {
        echo '<customer>';
        echo '<customer_id>' . htmlspecialchars($person[0], ENT_XML1, 'UTF-8') . '</customer_id>';
        echo '<first_name>'  . htmlspecialchars($person[1], ENT_XML1, 'UTF-8') . '</first_name>';
        echo '<last_name>'   . htmlspecialchars($person[2], ENT_XML1, 'UTF-8') . '</last_name>';
        echo '<email>'       . htmlspecialchars($person[3], ENT_XML1, 'UTF-8') . '</email>';
        echo '<phone>'       . htmlspecialchars($person[4], ENT_XML1, 'UTF-8') . '</phone>';
        echo '</customer>';
    }
}
echo '</livelookup>';

// Flush the output buffer.
ob_end_flush();
?>
