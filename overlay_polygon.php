<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Buat folder output jika belum ada
if (!is_dir('outputs')) {
    mkdir('outputs', 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['stoFile']) || !isset($_FILES['microFile'])) {
        echo json_encode(["success" => false, "message" => "Upload file KML tidak lengkap"]);
        exit;
    }

    $stoFile = $_FILES['stoFile']['tmp_name'];
    $microFile = $_FILES['microFile']['tmp_name'];
    $outputName = isset($_POST['outputName']) && $_POST['outputName'] !== ""
        ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['outputName']) . ".xlsx"
        : "hasil_mapping.xlsx";

    // Load KML STO
    $stoXml = simplexml_load_file($stoFile);
    if (!$stoXml) {
        echo json_encode(["success" => false, "message" => "Gagal membaca file STO"]);
        exit;
    }
    $stoXml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
    $stoPolygons = [];
    foreach ($stoXml->xpath('//kml:Placemark') as $pm) {
        $name = (string) $pm->name;
        $coordsStr = (string) $pm->Polygon->outerBoundaryIs->LinearRing->coordinates;
        $coords = array_map('trim', explode(" ", trim($coordsStr)));
        $poly = [];
        foreach ($coords as $c) {
            if ($c) {
                [$lon, $lat] = explode(",", $c);
                $poly[] = [(float) $lon, (float) $lat];
            }
        }
        if (!empty($poly)) {
            $stoPolygons[] = ["name" => $name, "polygon" => $poly];
        }
    }

    // Load KML Microdemend
    $microXml = simplexml_load_file($microFile);
    if (!$microXml) {
        echo json_encode(["success" => false, "message" => "Gagal membaca file Microdemend"]);
        exit;
    }
    $microXml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
    $results = [];
    foreach ($microXml->xpath('//kml:Placemark') as $pm) {
        $microName = (string) $pm->name;
        $coordsStr = (string) $pm->Polygon->outerBoundaryIs->LinearRing->coordinates;
        $coords = array_map('trim', explode(" ", trim($coordsStr)));
        $poly = [];
        foreach ($coords as $c) {
            if ($c) {
                [$lon, $lat] = explode(",", $c);
                $poly[] = [(float) $lon, (float) $lat];
            }
        }

        if (empty($poly))
            continue;

        // Cari STO yang memuat titik pertama polygon microdemend
        $point = $poly[0];
        $foundSTO = "Tidak ditemukan";
        foreach ($stoPolygons as $sto) {
            if (pointInPolygon($point[0], $point[1], $sto['polygon'])) {
                $foundSTO = $sto['name'];
                break;
            }
        }

        $results[] = ["Microdemend" => $microName, "STO" => $foundSTO];
    }

    // Buat Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue("A1", "Microdemend");
    $sheet->setCellValue("B1", "STO");

    $row = 2;
    foreach ($results as $r) {
        $sheet->setCellValue("A$row", $r["Microdemend"]);
        $sheet->setCellValue("B$row", $r["STO"]);
        $row++;
    }

    $writer = new Xlsx($spreadsheet);
    $outputPath = "outputs/" . $outputName;
    $writer->save($outputPath);

    echo json_encode([
        "success" => true,
        "file" => $outputPath,
        "filename" => $outputName
    ]);
}

/**
 * Cek apakah titik ada di dalam polygon
 */
function pointInPolygon($x, $y, $polygon)
{
    $inside = false;
    $n = count($polygon);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $polygon[$i][0];
        $yi = $polygon[$i][1];
        $xj = $polygon[$j][0];
        $yj = $polygon[$j][1];

        $intersect = (($yi > $y) != ($yj > $y)) &&
            ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-10) + $xi);
        if ($intersect)
            $inside = !$inside;
    }
    return $inside;
}
