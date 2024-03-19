<?php
// Clear the scrape.log file
$logFile = fopen('scrape.log', 'w');
if ($logFile === false) {
    die('Error: Unable to open or create scrape.log file.');
}
fclose($logFile);

// Open or create scrape.log file for logging
$logFile = fopen('scrape.log', 'a');
if ($logFile === false) {
    die('Error: Unable to open or create scrape.log file.');
}

// Get current year
$currentYear = date('y');

// Load existing database or create new
$database = [];
if (file_exists('database.json')) {
    $databaseContent = file_get_contents('database.json');
    if ($databaseContent !== false) {
        $database = json_decode($databaseContent, true);
    }
}

// Iterate through cases for the current year
for ($caseNumber = 0; $caseNumber <= 100000; $caseNumber++) {
    $caseId = sprintf('%02d-%06d', $currentYear, $caseNumber);
    $url = 'https://missingpersons.police.uk/en-gb/case/' . $caseId;

    // Log the URL checked
    fwrite($logFile, "Checking URL: $url\n");

    // Check if the case already exists in the database
    if (isset($database[$caseId])) {
        fwrite($logFile, "Case $caseId already exists in the database. Skipping...\n");
        continue;
    }

    // Fetch HTML content
    $html = file_get_contents($url);
    if ($html === false) {
        fwrite($logFile, "Error: Unable to fetch HTML content from the provided URL: $url\n");
        continue;
    }

    try {
        // Use DOMDocument for parsing HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        // Parse relevant data
        $caseData = [];
        $entries = $dom->getElementsByTagName('div');
        foreach ($entries as $entry) {
            if ($entry->getAttribute('class') === 'Entry') {
                $key = trim($entry->getElementsByTagName('div')[0]->nodeValue);
                $value = trim($entry->getElementsByTagName('div')[1]->nodeValue);
                $caseData[$key] = $value;
            }
        }

        // Get case images
        $caseImages = [];
        foreach ($dom->getElementsByTagName('div') as $link) {
            if ($link->getAttribute('class') === 'CaseImageGrid') {
                foreach ($link->getElementsByTagName('a') as $image) {
                    $imageSrc = $image->getAttribute('href');
                    // Check if the image src ends with jpg or png
                    if (preg_match('/\.(jpg|png)$/', $imageSrc)) {
                        $caseImages[] = $imageSrc;
                    }
                }
            }
        }

        // Get sensitive images
        $sensitiveImages = [];
        foreach ($dom->getElementsByTagName('a') as $link) {
            if ($link->getAttribute('class') === 'SensitiveImage') {
                $sensitiveImages[] = $link->getAttribute('data-url');
            }
        }

        // Get map image URL
        $mapImageUrl = '';
        foreach ($dom->getElementsByTagName('img') as $img) {
            if (strpos($img->getAttribute('src'), 'forcemap') !== false) {
                $mapImageUrl = 'https://missingpersons.police.uk' . $img->getAttribute('src');
                break;
            }
        }

        // Only proceed if map image URL is not empty
        if (!empty($mapImageUrl)) {
            // Determine location from map image URL
            $parts = explode('/', $mapImageUrl);
            $location = rtrim($parts[count($parts) - 1], '.png');

            // Combine map images
            $backgroundImageUrl = 'https://missingpersons.police.uk/site-content/themes/public/mpu-2021/i/RegionMap/background.jpg';
            $backgroundImage = imagecreatefromjpeg($backgroundImageUrl);
            $mapImage = imagecreatefrompng($mapImageUrl);
            if ($backgroundImage && $mapImage) {
                imagecopy($backgroundImage, $mapImage, 0, 0, 0, 0, imagesx($mapImage), imagesy($mapImage));
                $combinedMapFilename = 'geo/' . $location . '.jpg'; // Save to geo/location.jpg
                imagejpeg($backgroundImage, $combinedMapFilename);
                imagedestroy($backgroundImage);
                imagedestroy($mapImage);

                // Add new data to the database
                $database[$caseId] = [
                    'Url' => $url,
                    'CaseNumber' => $caseId,
                    'CaseData' => $caseData + [
                        'CaseImages' => $caseImages,
                        'SensitiveImages' => $sensitiveImages,
                        'CombinedMapImage' => './' . $combinedMapFilename
                    ]
                ];

                // Log successful scrape
                fwrite($logFile, "Scraped data successfully for case: $url\n");
            } else {
                fwrite($logFile, "Error: Unable to create or combine map images for case: $url\n");
            }
        } else {
            fwrite($logFile, "Error: Map image URL is empty for case: $url\n");
        }
    } catch (Exception $e) {
        // Handle any exceptions or errors during parsing
        fwrite($logFile, 'Error: ' . $e->getMessage() . "\n");
    }
}

// Save the updated database back to database.json
$updatedDatabaseJson = json_encode($database, JSON_PRETTY_PRINT);
if ($updatedDatabaseJson === false) {
    fwrite($logFile, "Error: Unable to encode updated database content to JSON.\n");
}

if (file_put_contents('database.json', $updatedDatabaseJson) === false) {
    fwrite($logFile, "Error: Unable to save updated database content to database.json.\n");
}

// Close the log file
fclose($logFile);
?>
