<?php
// Clear the log file
file_put_contents('update.json', '');

// Read database.json content
$databaseContent = file_get_contents('database.json');
if ($databaseContent === false) {
    die('Error: Unable to read database.json file.');
}

// Decode JSON content
$database = json_decode($databaseContent, true);
if ($database === null) {
    die('Error: Unable to decode database.json content.');
}

// Load existing changelog or create new
$changelog = [];
if (file_exists('update.json')) {
    $changelogContent = file_get_contents('update.json');
    if ($changelogContent !== false) {
        $changelog = json_decode($changelogContent, true);
    }
}

// Iterate through each case URL
foreach ($database as &$caseInfo) {
    $url = $caseInfo['Url'];

    // Log start of scraping for this URL
    fwrite($logFile, "Scraping data for case: $url\n");

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

        // Debugging statement
        fwrite($logFile, "Map image URL: $mapImageUrl\n");

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

                // Update or add new data to the case info
                $caseInfo['CaseData'] = $caseData + [
                    'CaseImages' => $caseImages,
                    'SensitiveImages' => $sensitiveImages,
                    'CombinedMapImage' => './' . $combinedMapFilename
                ];

                // Log changes to the changelog
                $caseNumber = $caseInfo['CaseNumber'];
                if (!isset($changelog[$caseNumber])) {
                    $changelog[$caseNumber] = [];
                }
                $changelog[$caseNumber][] = "Updated data for case $url";

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

// Save the changelog back to update.json
$updatedChangelogJson = json_encode($changelog, JSON_PRETTY_PRINT);
if ($updatedChangelogJson === false) {
    fwrite($logFile, "Error: Unable to encode updated changelog content to JSON.\n");
}

if (file_put_contents('update.json', $updatedChangelogJson) === false) {
    fwrite($logFile, "Error: Unable to save updated changelog content to update.json.\n");
}

// Close the log file
fclose($logFile);
?>