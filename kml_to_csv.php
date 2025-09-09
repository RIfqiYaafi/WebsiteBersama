<?php
// backend.php — Convert Deskripsi Polygon KML -> CSV/Excel (JSON-only response, robust pipe-parser)

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!ob_get_level())
    ob_start();
$responded = false;

// Shutdown handler untuk fatal errors
register_shutdown_function(function () use (&$responded) {
    $err = error_get_last();
    if ($responded)
        return;
    if ($err) {
        if (ob_get_length())
            ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error terjadi pada server',
            'error' => $err
        ], JSON_UNESCAPED_UNICODE);
        $responded = true;
    }
});

// Jadikan warning/error sebagai exception (kecuali notices yg disaring error_reporting)
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno))
        return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // Cek autoload
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        echo json_encode([
            'success' => false,
            'message' => 'Dependency tidak ditemukan: vendor/autoload.php.',
            'hint' => 'Jalankan di folder project: composer require phpoffice/phpspreadsheet'
        ], JSON_UNESCAPED_UNICODE);
        $responded = true;
        exit;
    }
    require $autoload;

    // Hanya POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method (gunakan POST).']);
        $responded = true;
        exit;
    }

    // Deteksi POST overflow
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 0 && empty($_FILES)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada file terunggah. Kemungkinan ukuran file melebihi post_max_size / upload_max_filesize.',
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ], JSON_UNESCAPED_UNICODE);
        $responded = true;
        exit;
    }

    // Validasi action
    $action = $_POST['action'] ?? '';
    if ($action !== 'convert_kml_csv') {
        echo json_encode(['success' => false, 'message' => 'Invalid action. Gunakan action=convert_kml_csv.']);
        $responded = true;
        exit;
    }

    // Validasi upload
    if (!isset($_FILES['kmlFile']) || $_FILES['kmlFile']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload KML gagal atau tidak ada file.']);
        $responded = true;
        exit;
    }
    $tmp = $_FILES['kmlFile']['tmp_name'];
    if (!is_uploaded_file($tmp) || !file_exists($tmp)) {
        echo json_encode(['success' => false, 'message' => 'File upload tidak valid.']);
        $responded = true;
        exit;
    }

    // Nama output & format
    $nameOut = trim($_POST['outputCsvName'] ?? 'hasil');
    $nameOut = $nameOut === '' ? 'hasil' : $nameOut;
    $nameOut = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nameOut);
    $format = (($_POST['outputFormat'] ?? 'csv') === 'excel') ? 'excel' : 'csv';

    // Baca KML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($tmp);
    if ($xml === false) {
        $errs = libxml_get_errors();
        $msg = "File KML tidak valid.";
        if (!empty($errs))
            $msg .= " XML error: " . trim($errs[0]->message);
        libxml_clear_errors();
        echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        $responded = true;
        exit;
    }

    // Ambil Placemark (dengan dan tanpa namespace)
    $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
    $placemarks = $xml->xpath('//kml:Placemark');
    if (!$placemarks || count($placemarks) === 0) {
        $placemarks = $xml->xpath('//Placemark');
    }
    if (!$placemarks || count($placemarks) === 0) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada Placemark pada KML.']);
        $responded = true;
        exit;
    }

    // Fungsi: parse coordinates -> WKT POLYGON
    $coords_to_wkt_polygon = function (string $coords): string {
        $pairs = preg_split('/\s+/', trim($coords));
        $pts = [];
        foreach ($pairs as $p) {
            if ($p === '')
                continue;
            $c = explode(',', $p);
            if (count($c) >= 2) {
                $lon = trim($c[0]);
                $lat = trim($c[1]);
                if ($lon !== '' && $lat !== '')
                    $pts[] = "{$lon} {$lat}";
            }
        }
        if (count($pts) < 3)
            return '';
        return 'POLYGON((' . implode(', ', $pts) . '))';
    };

    // Fungsi: parser deskripsi berbasis pipe (|)
    $parse_description_pipe = function (string $desc): array {
        $out = [];
        if ($desc === null)
            $desc = '';
        $desc = preg_replace('/^<!\[CDATA\[(.*)\]\]>$/s', '$1', $desc);
        $desc = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $desc = preg_replace('/<br\s*\/?>/i', "\n", $desc); // ganti <br> ke newline
        $desc = strip_tags($desc);

        // Pecah berdasarkan newline
        $parts = preg_split('/\r?\n/', $desc);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '')
                continue;

            if (strpos($part, ':') !== false) {
                list($k, $v) = explode(':', $part, 2);
                $k = trim($k);
                $v = trim($v);

                // skip kalau header tidak penting
                if ($k === 'KolomG')
                    continue;

                if ($k !== '')
                    $out[$k] = $v;
            } else {
                // Kalau tidak ada tanda ":", taruh di kolom Nama
                if (!isset($out['Nama'])) {
                    $out['Nama'] = $part;
                }
            }
        }
        return $out;
    };


    // Header terurut (selain PlacemarkName & Polygon)
    $dynamicHeaders = []; // urutan sesuai kemunculan pertama
    $dynamicSet = [];     // set untuk cek cepat

    // Data rows
    $rows = [];

    foreach ($placemarks as $pm) {
        // name
        $name = isset($pm->name) ? (string) $pm->name : '';

        // description
        $desc = isset($pm->description) ? (string) $pm->description : '';
        $descData = $parse_description_pipe($desc); // <-- parser pipe

        // Ambil polygon coordinates
        $poly = $pm->xpath('.//kml:Polygon/kml:outerBoundaryIs/kml:LinearRing/kml:coordinates');
        if (!is_array($poly) || count($poly) === 0) {
            $poly = $pm->xpath('.//Polygon/outerBoundaryIs/LinearRing/coordinates');
        }
        $coords = (is_array($poly) && count($poly)) ? (string) $poly[0] : '';
        $wkt = $coords ? $coords_to_wkt_polygon($coords) : '';

        // Kumpulkan header dinamis (urut sesuai kemunculan)
        foreach ($descData as $k => $_) {
            if (!isset($dynamicSet[$k])) {
                $dynamicSet[$k] = true;
                $dynamicHeaders[] = $k;
            }
        }

        $rows[] = [
            'PlacemarkName' => $name,
            'descData' => $descData,
            'Polygon' => $wkt
        ];
    }

    // Susun header final: PlacemarkName + dynamic + Polygon
    $header = array_merge(['PlacemarkName'], $dynamicHeaders, ['Polygon']);

    // Siapkan folder output
    $outDir = __DIR__ . '/output';
    if (!is_dir($outDir)) {
        if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
            throw new RuntimeException("Gagal membuat folder output: {$outDir}");
        }
    }
    if (!is_writable($outDir)) {
        throw new RuntimeException("Folder output tidak bisa ditulisi. Cek permission: {$outDir}");
    }

    // Tulis file
    if ($format === 'csv') {
        $filename = $nameOut . '.csv';
        $filepath = $outDir . DIRECTORY_SEPARATOR . $filename;

        $fp = fopen($filepath, 'w');
        if ($fp === false)
            throw new RuntimeException("Gagal membuka file: {$filepath}");
        // Tulis UTF-8 BOM agar Excel Windows mengenali UTF-8
        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, $header);
        foreach ($rows as $r) {
            $line = [];
            foreach ($header as $col) {
                if ($col === 'PlacemarkName') {
                    $line[] = $r['PlacemarkName'];
                } elseif ($col === 'Polygon') {
                    $line[] = $r['Polygon'];
                } else {
                    $line[] = $r['descData'][$col] ?? '';
                }
            }
            fputcsv($fp, $line);
        }
        fclose($fp);

    } else {
        // Excel
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            echo json_encode([
                'success' => false,
                'message' => 'Excel membutuhkan PhpSpreadsheet. Jalankan: composer require phpoffice/phpspreadsheet'
            ], JSON_UNESCAPED_UNICODE);
            $responded = true;
            exit;
        }

        $filename = $nameOut . '.xlsx';
        $filepath = $outDir . DIRECTORY_SEPARATOR . $filename;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->fromArray($header, null, 'A1');

        // Rows
        $ridx = 2;
        foreach ($rows as $r) {
            $line = [];
            foreach ($header as $col) {
                if ($col === 'PlacemarkName') {
                    $line[] = $r['PlacemarkName'];
                } elseif ($col === 'Polygon') {
                    $line[] = $r['Polygon'];
                } else {
                    $line[] = $r['descData'][$col] ?? '';
                }
            }
            $sheet->fromArray($line, null, "A{$ridx}");
            $ridx++;
        }

        // Opsi: autosize kolom agar rapi
        foreach (range(1, count($header)) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
    }

    // Response JSON
    $extra = trim(ob_get_clean() ?: '');
    echo json_encode([
        'success' => true,
        'message' => 'Konversi berhasil',
        'download' => 'output/' . $filename,
        'filename' => $filename,
        'debug' => $extra ?: null
    ], JSON_UNESCAPED_UNICODE);

    $responded = true;
    exit;

} catch (Throwable $e) {
    if (ob_get_length())
        ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    $responded = true;
    exit;
}
