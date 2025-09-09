<?php
function createDirectories()
{
    $dirs = ['uploads', 'output', 'temp'];
    foreach ($dirs as $dir) {
        $dirPath = __DIR__ . '/' . $dir;
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0777, true);
        }
    }
}

function sanitizeFileName($filename)
{
    return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
}

function extractKmz($kmzPath, $extractPath)
{
    $zip = new ZipArchive;
    $res = $zip->open($kmzPath);

    if ($res !== true) {
        throw new Exception("Failed to open KMZ file. Error code: $res");
    }

    if (!file_exists($extractPath)) {
        mkdir($extractPath, 0777, true);
    }

    $zip->extractTo($extractPath);
    $zip->close();

    // Cari file KML (bisa di root atau subfolder)
    $kmlFiles = array_merge(
        glob($extractPath . '/*.kml'),
        glob($extractPath . '/**/*.kml', GLOB_BRACE)
    );

    if (empty($kmlFiles)) {
        throw new Exception("No KML file found in KMZ archive");
    }

    return $kmlFiles[0]; // Ambil file KML pertama yang ditemukan
}

function processUploadedFile($file)
{
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, ['kml', 'kmz'])) {
        throw new Exception("Only KML and KMZ files are allowed");
    }

    $sanitizedName = sanitizeFileName(pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $fileExt;
    $uploadPath = __DIR__ . '/uploads/' . $sanitizedName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception("Failed to move uploaded file");
    }

    if ($fileExt === 'kmz') {
        $extractPath = __DIR__ . '/temp/' . uniqid('kmz_');
        $kmlPath = extractKmz($uploadPath, $extractPath);

        return [
            'path' => $kmlPath,
            'isKmz' => true,
            'extractPath' => $extractPath,
            'originalPath' => $uploadPath
        ];
    }

    return [
        'path' => $uploadPath,
        'isKmz' => false
    ];
}

function getKmlContent($fileInfo)
{
    if (!file_exists($fileInfo['path'])) {
        throw new Exception("KML file not found");
    }

    $kmlContent = file_get_contents($fileInfo['path']);
    if ($kmlContent === false) {
        throw new Exception("Failed to read KML file");
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($kmlContent);
    if ($xml === false) {
        $errors = libxml_get_errors();
        $errorMessages = array_map(function ($error) {
            return $error->message;
        }, $errors);
        libxml_clear_errors();
        throw new Exception("Failed to parse KML: " . implode(", ", $errorMessages));
    }

    return [
        'xml' => $xml,
        'content' => $kmlContent
    ];
}

function saveProcessedFile($xml, $originalName, $suffix)
{
    $outputName = sanitizeFileName(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . $suffix . '.kml';
    $outputPath = __DIR__ . '/output/' . $outputName;

    // Format output dengan indentasi
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());

    file_put_contents($outputPath, $dom->saveXML());
    return $outputName;
}

function cleanTempFiles($fileInfo)
{
    if (isset($fileInfo['isKmz']) && $fileInfo['isKmz']) {
        // Hapus file KML yang diekstrak
        if (isset($fileInfo['path']) && file_exists($fileInfo['path'])) {
            unlink($fileInfo['path']);
        }

        // Hapus direktori ekstrak
        if (isset($fileInfo['extractPath']) && file_exists($fileInfo['extractPath'])) {
            array_map('unlink', glob($fileInfo['extractPath'] . '/*'));
            rmdir($fileInfo['extractPath']);
        }

        // Hapus file KMZ asli
        if (isset($fileInfo['originalPath']) && file_exists($fileInfo['originalPath'])) {
            unlink($fileInfo['originalPath']);
        }
    } elseif (isset($fileInfo['path']) && file_exists($fileInfo['path'])) {
        unlink($fileInfo['path']);
    }
}

function calculateCentroid($coordinates)
{
    $coordinates = preg_replace('/\s+/', ' ', trim($coordinates));
    $points = explode(' ', $coordinates);
    $latSum = $lonSum = $count = 0;

    foreach ($points as $point) {
        if (empty($point))
            continue;
        $parts = explode(',', $point);
        if (count($parts) < 2)
            continue;

        $lonSum += (float) $parts[0];
        $latSum += (float) $parts[1];
        $count++;
    }

    return $count > 0 ? ['lat' => $latSum / $count, 'lon' => $lonSum / $count] : null;
}

function calculatePointsAlongPath($coordinates, $distanceMeters)
{
    $points = [];
    $coords = [];

    // Parse koordinat
    $rawPoints = explode(' ', trim(preg_replace('/\s+/', ' ', $coordinates)));
    foreach ($rawPoints as $point) {
        if (empty($point))
            continue;
        $parts = explode(',', $point);
        if (count($parts) >= 2) {
            $coords[] = ['lon' => (float) $parts[0], 'lat' => (float) $parts[1]];
        }
    }

    if (count($coords) < 2)
        return $points;

    $totalDistance = 0;
    $segmentDistances = [];

    // Hitung total jarak dan jarak tiap segment
    for ($i = 0; $i < count($coords) - 1; $i++) {
        $dist = haversineDistance($coords[$i], $coords[$i + 1]);
        $totalDistance += $dist;
        $segmentDistances[] = $dist;
    }

    // Hitung jumlah titik yang dibutuhkan
    $numPoints = ceil($totalDistance / $distanceMeters);
    if ($numPoints < 1)
        return $points;

    $actualInterval = $totalDistance / $numPoints;
    $currentDistance = 0;
    $currentSegment = 0;
    $remainingInSegment = $segmentDistances[0];

    for ($i = 0; $i <= $numPoints; $i++) {
        $targetDistance = $i * $actualInterval;

        // Cari segment yang sesuai
        while ($targetDistance > $currentDistance + $remainingInSegment && $currentSegment < count($segmentDistances) - 1) {
            $currentDistance += $remainingInSegment;
            $currentSegment++;
            $remainingInSegment = $segmentDistances[$currentSegment];
        }

        $ratio = ($targetDistance - $currentDistance) / $remainingInSegment;
        $ratio = max(0, min(1, $ratio)); // Clamp antara 0-1

        $start = $coords[$currentSegment];
        $end = $coords[$currentSegment + 1];

        $points[] = [
            'lat' => $start['lat'] + ($end['lat'] - $start['lat']) * $ratio,
            'lon' => $start['lon'] + ($end['lon'] - $start['lon']) * $ratio
        ];
    }

    return $points;
}

function haversineDistance($point1, $point2)
{
    $lat1 = deg2rad($point1['lat']);
    $lon1 = deg2rad($point1['lon']);
    $lat2 = deg2rad($point2['lat']);
    $lon2 = deg2rad($point2['lon']);

    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;

    $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    $radius = 6371000; // Radius bumi dalam meter
    return $radius * $c;
}

function renamePlacemarks($xml, $options)
{
    $namespaces = $xml->getNamespaces(true);
    $kmlNamespace = $namespaces[''] ?? null;

    if ($kmlNamespace) {
        $xml->registerXPathNamespace('kml', $kmlNamespace);
        $placemarks = $xml->xpath('//kml:Placemark');
    } else {
        $placemarks = $xml->xpath('//Placemark');
    }

    $counter = (int) $options['startNumber'];
    $totalPlacemarks = count($placemarks);
    $usedNumbers = [];

    // Jika penomoran acak, buat daftar nomor terlebih dahulu
    if ($options['labelType'] === 'numeric' && $options['numbering'] === 'random') {
        $numbers = range($counter, $counter + $totalPlacemarks - 1);
        shuffle($numbers);
    }

    foreach ($placemarks as $i => $pm) {
        // Hapus description jika ada
        if (isset($pm->description)) {
            unset($pm->description);
        }

        // Generate nama baru
        if ($options['labelType'] === 'numeric') {
            $number = ($options['numbering'] === 'sequential')
                ? $counter + $i
                : $numbers[$i];

            $prefix = trim($options['prefix']);
            $newName = sprintf("%s%03d", $prefix, $number);
        } else {
            $newName = str_replace('{n}', $counter + $i, $options['customName']);
        }

        // Update nama
        $nameNode = $pm->name;
        if ($nameNode) {
            $nameNode[0] = $newName;
        } else {
            $pm->addChild('name', $newName);
        }
    }

    return $xml;
}
?>