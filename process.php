<?php
require_once 'functions.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

try {
    createDirectories();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['kmlFile']) || !isset($_GET['action'])) {
        throw new Exception('Invalid request. Please use POST method with kmlFile and action parameters.');
    }

    $action = $_GET['action'];
    $file = $_FILES['kmlFile'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['kml', 'kmz'];

    if ($action === 'wkt') {
        $allowedExtensions = ['xlsx'];
    }

    if (!in_array($fileExt, $allowedExtensions)) {
        throw new Exception('Invalid file type.');
    }

    $fileInfo = processUploadedFile($file);
    $outputName = '';

    switch ($action) {
        case 'centroid':
            $kmlData = getKmlContent($fileInfo);
            $xml = $kmlData['xml'];
            $placemarkNamePattern = $_POST['placemarkName'] ?? 'Rumah {n}';
            $placemarkIcon = $_POST['placemarkIcon'] ?? '';

            $namespaces = $xml->getNamespaces(true);
            $kmlNamespace = $namespaces[''] ?? null;

            $xpath = $kmlNamespace ? '//kml:Polygon' : '//Polygon';
            $polygons = $xml->xpath($xpath);

            if ($polygons === false || empty($polygons)) {
                throw new Exception("Tidak ada polygon ditemukan di file KML.");
            }

            $placemarks = [];
            $counter = 1;

            foreach ($polygons as $polygon) {
                $coordPath = $kmlNamespace ? './/kml:coordinates' : './/coordinates';
                $coordElements = $polygon->xpath($coordPath);

                if ($coordElements === false || count($coordElements) === 0) {
                    continue; // skip polygon tanpa koordinat
                }

                $coordinates = trim((string) $coordElements[0]);
                if (!empty($coordinates) && ($centroid = calculateCentroid($coordinates))) {
                    $placemarks[] = [
                        'name' => str_replace('{n}', $counter++, $placemarkNamePattern),
                        'lat' => $centroid['lat'],
                        'lon' => $centroid['lon'],
                        'icon' => $placemarkIcon
                    ];
                }
            }

            $document = $xml->Document ?: $xml;
            $folder = $document->addChild('Folder');
            $folder->addChild('name', 'Centroid Placemarks');

            foreach ($placemarks as $pm) {
                $placemark = $folder->addChild('Placemark');
                $placemark->addChild('name', htmlspecialchars($pm['name']));

                if (!empty($pm['icon'])) {
                    $style = $placemark->addChild('Style');
                    $iconStyle = $style->addChild('IconStyle');
                    $icon = $iconStyle->addChild('Icon');
                    $icon->addChild('href', $pm['icon']);
                }

                $point = $placemark->addChild('Point');
                $point->addChild('coordinates', $pm['lon'] . ',' . $pm['lat'] . ',0');
            }

            $outputName = saveProcessedFile($xml, $file['name'], 'with_centroids');
            break;

        case 'path':
            $kmlData = getKmlContent($fileInfo);
            $xml = $kmlData['xml'];
            $placemarkNamePattern = $_POST['placemarkName'] ?? 'Titik {n}';
            $placemarkIcon = $_POST['placemarkIcon'] ?? '';
            $distanceMeters = max(1, (float) ($_POST['distance'] ?? 100));

            $namespaces = $xml->getNamespaces(true);
            $kmlNamespace = $namespaces[''] ?? null;

            $xpath = $kmlNamespace ? '//kml:LineString' : '//LineString';
            $paths = $xml->xpath($xpath);

            if ($paths === false || empty($paths)) {
                throw new Exception("Tidak ada LineString ditemukan di file KML.");
            }

            $placemarks = [];
            $counter = 1;

            foreach ($paths as $path) {
                $coordPath = $kmlNamespace ? './/kml:coordinates' : './/coordinates';
                $coordElements = $path->xpath($coordPath);

                if ($coordElements === false || count($coordElements) === 0) {
                    continue; // skip path tanpa koordinat
                }

                $coordinates = (string) $coordElements[0];
                $points = calculatePointsAlongPath($coordinates, $distanceMeters);

                foreach ($points as $point) {
                    $placemarks[] = [
                        'name' => str_replace('{n}', $counter++, $placemarkNamePattern),
                        'lat' => $point['lat'],
                        'lon' => $point['lon'],
                        'icon' => $placemarkIcon
                    ];
                }
            }

            $document = $xml->Document ?: $xml;
            $folder = $document->addChild('Folder');
            $folder->addChild('name', 'Path Placemarks');

            foreach ($placemarks as $pm) {
                $placemark = $folder->addChild('Placemark');
                $placemark->addChild('name', htmlspecialchars($pm['name']));

                if (!empty($pm['icon'])) {
                    $style = $placemark->addChild('Style');
                    $iconStyle = $style->addChild('IconStyle');
                    $icon = $iconStyle->addChild('Icon');
                    $icon->addChild('href', $pm['icon']);
                }

                $point = $placemark->addChild('Point');
                $point->addChild('coordinates', $pm['lon'] . ',' . $pm['lat'] . ',0');
            }

            $outputName = saveProcessedFile($xml, $file['name'], 'with_path_points');
            break;

        case 'rename':
            $kmlData = getKmlContent($fileInfo);
            $xml = $kmlData['xml'];
            $options = [
                'labelType' => $_POST['labelType'] ?? 'numeric',
                'prefix' => $_POST['numericPrefix'] ?? 'Lokasi',
                'startNumber' => max(1, (int) ($_POST['startNumber'] ?? 1)),
                'numbering' => $_POST['numbering'] ?? 'sequential',
                'customName' => $_POST['customName'] ?? 'Lokasi {n}'
            ];

            $xml = renamePlacemarks($xml, $options);
            $outputName = saveProcessedFile($xml, $file['name'], 'renamed');
            break;

        case 'wkt':
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            $wktColumn = $_POST['wktColumn'] ?? 'geometry';
            $outputFilename = $_POST['outputName'] ?? 'output';
            $outputName = $outputFilename . '.kml';

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document></Document></kml>');
            $document = $xml->Document;

            $headerRow = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1')[0];
            $wktIndex = array_search($wktColumn, $headerRow);

            if ($wktIndex === false) {
                throw new Exception("Kolom WKT '$wktColumn' tidak ditemukan dalam file Excel.");
            }

            $counter = 1;
            for ($row = 2; $row <= $highestRow; $row++) {
                $wkt = trim($sheet->getCellByColumnAndRow($wktIndex + 1, $row)->getValue());
                if (empty($wkt))
                    continue;

                $placemark = $document->addChild('Placemark');
                $placemark->addChild('name', 'WKT ' . $counter++);

                if (stripos($wkt, 'POINT') === 0) {
                    preg_match('/POINT\s*\(([-\d.]+)\s+([-\d.]+)\)/i', $wkt, $matches);
                    if (count($matches) === 3) {
                        $lon = $matches[1];
                        $lat = $matches[2];
                        $point = $placemark->addChild('Point');
                        $point->addChild('coordinates', "$lon,$lat,0");
                    }
                } elseif (stripos($wkt, 'LINESTRING') === 0) {
                    preg_match_all('/[-\d.]+\s+[-\d.]+/', $wkt, $matches);
                    $coords = array_map(function ($pair) {
                        [$lon, $lat] = preg_split('/\s+/', trim($pair));
                        return "$lon,$lat,0";
                    }, $matches[0]);
                    $line = $placemark->addChild('LineString');
                    $line->addChild('coordinates', implode(' ', $coords));
                } elseif (stripos($wkt, 'POLYGON') === 0) {
                    preg_match_all('/[-\d.]+\s+[-\d.]+/', $wkt, $matches);
                    $coords = array_map(function ($pair) {
                        [$lon, $lat] = preg_split('/\s+/', trim($pair));
                        return "$lon,$lat,0";
                    }, $matches[0]);
                    $polygon = $placemark->addChild('Polygon');
                    $outer = $polygon->addChild('outerBoundaryIs')->addChild('LinearRing');
                    $outer->addChild('coordinates', implode(' ', $coords));
                }
            }

            $outputPath = 'output/' . $outputName;
            $xml->asXML($outputPath);
            break;

        default:
            throw new Exception('Invalid action. Supported actions: centroid, path, rename, wkt.');
    }

    echo json_encode([
        'success' => true,
        'filename' => $outputName,
        'downloadUrl' => 'output/' . $outputName
    ]);

} catch (Exception $e) {
    if (isset($fileInfo)) {
        cleanTempFiles($fileInfo);
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($fileInfo)) {
        cleanTempFiles($fileInfo);
    }
}
