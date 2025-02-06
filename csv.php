<?php
ob_start();
error_reporting(0);
ini_set('display_errors', '0');

// Set header as XML with UTF-8.
header('Content-Type: text/xml; charset=UTF-8');

/**
 * Fetch CSV content using cURL.
 */
function fetchCsvWithCurl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    if(curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return $data;
}

// Replace with your actual Google Sheet export URL.
$csvUrl = 'https://docs.google.com/spreadsheets/d/YOUR_SPREADSHEET_ID/export?format=csv&id=YOUR_SPREADSHEET_ID&gid=YOUR_GID';
$csvContent = fetchCsvWithCurl($csvUrl);
if ($csvContent === false) {
    http_response_code(500);
    exit("Error fetching CSV data.");
}

// Remove BOM if present.
if (substr($csvContent, 0, 3) === "\xEF\xBB\xBF") {
    $csvContent = substr($csvContent, 3);
}

// Write CSV content to a memory stream.
$handle = fopen('php://memory', 'r+');
if (!$handle) {
    http_response_code(500);
    exit("Error opening memory stream.");
}
fwrite($handle, $csvContent);
rewind($handle);

// Create a new DOMDocument.
$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

// Build the root element.
$root = $doc->createElement('livelookup');
$root->setAttribute('version', '1.0');
$root->setAttribute('columns', 'customer_id,first_name,last_name');
$doc->appendChild($root);

// Process CSV rows.
// Expected CSV columns: [0] customer ID, [1] first name, [2] last name, [3] email, [4] phone.
while (($data = fgetcsv($handle, 1000, ",")) !== false) {
    if (count($data) < 5) {
        continue;
    }
    if (
        (!empty($_GET['customer_id']) && $data[0] == $_GET['customer_id']) ||
        (!empty($_GET['email']) && $data[3] == $_GET['email'])
    ) {
        $customer = $doc->createElement('customer');
        $customer->appendChild($doc->createElement('customer_id', $data[0]));
        $customer->appendChild($doc->createElement('first_name', $data[1]));
        $customer->appendChild($doc->createElement('last_name', $data[2]));
        $customer->appendChild($doc->createElement('email', $data[3]));
        $customer->appendChild($doc->createElement('phone', $data[4]));
        $root->appendChild($customer);
    }
}
fclose($handle);

// Clear any output buffering before sending XML.
$output = $doc->saveXML();
ob_end_clean();
echo $output;
?>
