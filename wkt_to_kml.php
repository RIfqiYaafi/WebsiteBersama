<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['excelFile']['tmp_name'];
    $wktColumn = trim($_POST['wktColumn'] ?? 'geometry');   // default: geometry
    $outputName = trim($_POST['outputName'] ?? 'hasil_kml'); // default: hasil_kml.kml
    $labelColumn = trim($_POST['labelColumn'] ?? '');         // opsional: nama kolom label

    try {
      $spreadsheet = IOFactory::load($fileTmpPath);
      $worksheet = $spreadsheet->getActiveSheet();
      $rows = $worksheet->toArray(null, true, true, true);

      if (empty($rows) || !isset($rows[1])) {
        echo json_encode(["success" => false, "message" => "File kosong atau tidak ada data."]);
        exit;
      }

      // Header
      $headerRow = $rows[array_key_first($rows)];
      $headerLower = array_map('strtolower', $headerRow);

      // Cari index kolom WKT
      $wktIndex = array_search(strtolower($wktColumn), $headerLower);
      if ($wktIndex === false) {
        echo json_encode(["success" => false, "message" => "Kolom WKT tidak ditemukan."]);
        exit;
      }

      // Cari index kolom label (jika ada)
      $labelIndex = false;
      if ($labelColumn !== '') {
        $labelIndex = array_search(strtolower($labelColumn), $headerLower);
      }

      // Awal struktur KML
      $kmlContent = <<<KML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
  <Style id="yellowLinePolygon">
    <LineStyle>
      <color>ff00ffff</color>
      <width>2</width>
    </LineStyle>
    <PolyStyle>
      <color>00ffffff</color>
      <fill>0</fill>
      <outline>1</outline>
    </PolyStyle>
  </Style>

KML;

      // Loop setiap baris
      foreach ($rows as $key => $row) {
        if ($key === array_key_first($rows))
          continue;

        $wkt = trim($row[$wktIndex] ?? '');
        if (!$wkt)
          continue;

        // Tentukan nama Placemark
        if ($labelIndex !== false && isset($row[$labelIndex]) && trim($row[$labelIndex]) !== '') {
          $name = trim($row[$labelIndex]);
        } else {
          $firstColKey = array_key_first($row);
          $name = trim($row[$firstColKey]) ?: 'Tanpa Nama';
        }

        // Buat deskripsi dari kolom lain (skip kolom WKT & kosong)
        $descriptionParts = [];
        foreach ($row as $colKey => $val) {
          if ($colKey === $wktIndex)
            continue;
          if (trim($val) === '')
            continue;

          $header = $headerRow[$colKey] ?? "Kolom$colKey";
          $descriptionParts[] = "$header: $val";
        }
        $description = implode("<br>", $descriptionParts);

        // Parsing WKT
        if (stripos($wkt, 'POLYGON') === 0) {
          // POLYGON
          preg_match('/POLYGON\s*\(\(\s*(.*?)\s*\)\)/i', $wkt, $m);
          if ($m) {
            $coordPairs = explode(',', $m[1]);
            $coordKml = '';
            foreach ($coordPairs as $pair) {
              $parts = preg_split('/\s+/', trim($pair));
              if (count($parts) === 2) {
                $coordKml .= "{$parts[0]},{$parts[1]},0 ";
              }
            }
            $kmlContent .= "
  <Placemark>
    <name>$name</name>
    <description><![CDATA[$description]]></description>
    <styleUrl>#yellowLinePolygon</styleUrl>
    <Polygon>
      <outerBoundaryIs>
        <LinearRing>
          <coordinates>$coordKml</coordinates>
        </LinearRing>
      </outerBoundaryIs>
    </Polygon>
  </Placemark>
";
          }
        } elseif (stripos($wkt, 'LINESTRING') === 0) {
          // LINESTRING
          preg_match('/LINESTRING\s*\(\s*(.*?)\s*\)/i', $wkt, $m);
          if ($m) {
            $coordPairs = explode(',', $m[1]);
            $coordKml = '';
            foreach ($coordPairs as $pair) {
              $parts = preg_split('/\s+/', trim($pair));
              if (count($parts) === 2) {
                $coordKml .= "{$parts[0]},{$parts[1]},0 ";
              }
            }
            $kmlContent .= "
  <Placemark>
    <name>$name</name>
    <description><![CDATA[$description]]></description>
    <LineString>
      <tessellate>1</tessellate>
      <coordinates>$coordKml</coordinates>
    </LineString>
  </Placemark>
";
          }
        } elseif (stripos($wkt, 'POINT') === 0) {
          // POINT
          preg_match('/POINT\s*\(\s*(.*?)\s*\)/i', $wkt, $m);
          if ($m) {
            $parts = preg_split('/\s+/', trim($m[1]));
            if (count($parts) === 2) {
              $coord = "{$parts[0]},{$parts[1]},0";
              $kmlContent .= "
  <Placemark>
    <name>$name</name>
    <description><![CDATA[$description]]></description>
    <Point>
      <coordinates>$coord</coordinates>
    </Point>
  </Placemark>
";
            }
          }
        }
      }

      // Tutup KML
      $kmlContent .= "</Document>\n</kml>";

      // Simpan file output
      $outputDir = __DIR__ . "/outputs/";
      if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
      }
      $filePath = $outputDir . $outputName . ".kml";
      file_put_contents($filePath, $kmlContent);

      echo json_encode([
        "success" => true,
        "filename" => $outputName . ".kml",
        "url" => "outputs/" . $outputName . ".kml"
      ]);
      exit;

    } catch (Exception $e) {
      echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
      exit;
    }
  } else {
    echo json_encode(["success" => false, "message" => "❌ Gagal upload file."]);
  }
} else {
  echo json_encode(["success" => false, "message" => "Akses tidak diizinkan."]);
}
