<?php
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoMarker | KML Tools</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="Assets/location-pin-alt.svg">
    <style>
        :root {
            --primary: #FB9824;
            --primary-dark: #e0871f;
            --secondary: #2C425C;
            --secondary-light: #3a5778;
            --light: #F9FCFF;
            --gray: #E3E3E3;
            --dark: #2c3e50;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            background-color: var(--light);
            display: flex;
            min-height: 100vh;
            color: var(--dark);
            position: relative;
            padding-bottom: 50px;
        }

        @font-face {
            font-family: 'Poppins';
            src: url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--secondary);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100%;
            overflow-y: fixed;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar.collapsed .sidebar-header h2,
        .sidebar.collapsed .sidebar-header p,
        .sidebar.collapsed .nav-menu a span {
            display: none;
        }

        .sidebar.collapsed .nav-menu a {
            justify-content: center;
            padding: 12px 0;
        }

        .sidebar-toggle-container {
            position: absolute;
            bottom: 16px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            padding: 10px 0;
            border-top: 1px solid var(--secondary-light);
            background-color: var(--secondary);
        }

        .sidebar-toggle {
            background: var(--primary);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 101;
            border: 2px solid white;
            position: relative;
            margin-top: -18px;
        }

        .sidebar-toggle:hover::after {
            content: 'Toggle Aside';
            position: absolute;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            color: var(--secondary);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar-toggle i {
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--secondary-light);
            text-align: center;
        }

        .sidebar-header h2 {
            color: white;
            margin-bottom: 5px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .sidebar-header p {
            font-size: 0.8rem;
            color: var(--gray);
            opacity: 0.8;
        }

        .nav-menu {
            list-style: none;
            margin-top: 20px;
            flex-grow: 1;
        }

        .nav-menu li {
            margin: 5px 0;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ecf0f1;
            padding: 12px 25px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.95rem;
            border-left: 3px solid transparent;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background-color: var(--secondary-light);
            color: white;
            border-left: 3px solid var(--primary);
        }

        .nav-menu a i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
            background-color: var(--light);
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed~.main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        h1 {
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h1 i {
            color: var(--primary);
        }

        .tool-section {
            margin-bottom: 30px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary);
            font-size: 0.95rem;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid var(--gray);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background-color: var(--light);
        }

        /* File Upload Styles */
        .file-upload-container {
            margin-top: 10px;
        }

        .file-upload-box {
            border: 2px dashed var(--gray);
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            position: relative;
            transition: all 0.3s;
        }

        .file-upload-box.has-file {
            border-color: var(--primary);
            background-color: rgba(251, 152, 36, 0.05);
        }

        .file-upload-box input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-label {
            pointer-events: none;
        }

        .file-upload-label i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .file-upload-label p {
            margin: 5px 0;
            color: var(--secondary);
        }

        .file-info {
            font-size: 0.85rem;
            color: #777;
        }

        .file-selected-info {
            margin-top: 10px;
            font-weight: bold;
            color: var(--primary);
            font-size: 0.9rem;
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .file-selected-info.active {
            display: block;
        }

        input:focus,
        select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(251, 152, 36, 0.2);
        }

        button {
            background-color: var(--primary);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        button:hover {
            background-color: var(--primary-dark);
        }

        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Loading Animation */
        .loading {
            display: none;
            margin-left: 10px;
        }

        .loading.active {
            display: inline-block;
        }

        .loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Result Box */
        .result {
            margin-top: 25px;
            padding: 20px;
            background-color: rgba(76, 175, 80, 0.1);
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
            display: none;
        }

        .result h3 {
            color: #2e7d32;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .result a {
            color: #2e7d32;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .result a:hover {
            color: #1e5a22;
            text-decoration: underline;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .radio-option input {
            width: auto;
            margin: 0;
        }

        .hidden {
            display: none;
        }

        .welcome-section {
            padding: 20px;
        }

        .welcome-section h1 {
            font-size: 2.2rem;
            margin-bottom: 15px;
            color: var(--secondary);
            border: none;
            justify-content: center;
        }

        .welcome-section p.subtitle {
            font-size: 1.1rem;
            color: #555;
            text-align: center;
            max-width: 700px;
            margin: 0 auto 30px;
        }

        .feature-guide {
            max-width: 900px;
            margin: 0 auto;
        }

        .feature-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary);
        }

        .feature-card h3 {
            color: var(--secondary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-card h3 i {
            color: var(--primary);
            font-size: 1.3rem;
        }

        .feature-card p {
            color: #555;
            margin-bottom: 15px;
            line-height: 1.7;
        }

        .feature-card ol {
            padding-left: 20px;
            color: #555;
            line-height: 1.7;
        }

        .feature-card ol li {
            margin-bottom: 10px;
        }

        .icon-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
        }

        .icon-option {
            width: 50px;
            height: 50px;
            border: 2px solid var(--gray);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
        }

        .icon-option:hover,
        .icon-option.selected {
            border-color: var(--primary);
            transform: scale(1.05);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .icon-option img {
            max-width: 80%;
            max-height: 80%;
        }

        .credit {
            position: fixed;
            bottom: 0;
            left: var(--sidebar-width);
            right: 0;
            background: white;
            color: var(--secondary);
            text-align: center;
            padding: 9px 0;
            font-size: 0.8rem;
            z-index: 99;
            transition: all 0.3s ease;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            border-top: 1px solid var(--gray);
        }

        .sidebar.collapsed~.credit {
            left: var(--sidebar-collapsed-width);
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-map-marked-alt"></i> GeoMarker</h2>
            <!-- <p>Tools untuk memproses file KML Google Earth</p> -->
        </div>
        <ul class="nav-menu">
            <li><a href="#" class="nav-link active" data-target="welcome"><i class="fas fa-home"></i>
                    <span>Home</span></a></li>
            <li><a href="#" class="nav-link" data-target="centroid-tool"><i class="fas fa-map-pin"></i> <span>Centroid
                        Polygon</span></a></li>
            <li><a href="#" class="nav-link" data-target="path-tool"><i class="fa-solid fa-route"></i> <span>Placemarks
                        in Path</span></a></li>
            <li><a href="#" class="nav-link" data-target="rename-tool"><i class="fas fa-tags"></i> <span>Rename
                        Placemarks</span></a></li>
            <li><a href="#" class="nav-link" data-target="convert-tool"><i class="fas fa-exchange-alt"></i>
                    <span>Convert WKT To
                        KML</span></a></li>
            <li><a href="#" class="nav-link" data-target="convert_csv-tool"><i class="fas fa-file-csv"></i>
                    <span>Convert
                        KML to CSV</span></a></li>
            <li><a href="#" class="nav-link" data-target="sto-tool"><i class="fas fa-draw-polygon"></i> <span>Overlay
                        Polygon</span></a></li>

        </ul>
        <div class="sidebar-toggle-container">
            <div class="sidebar-toggle" title="Toggle Sidebar">
                <i class="fas fa-chevron-left"></i>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <div id="welcome" class="tool-section welcome-section">
                <h1><i class="fas fa-map-marked-alt"></i> Selamat Datang di GeoMarker</h1>
                <p class="subtitle">Aplikasi untuk memproses file Google Earth (KML) secara otomatis dengan berbagai
                    fitur</p>

                <div class="feature-guide">
                    <div class="feature-card">
                        <h3><i class="fas fa-map-pin"></i> Centroid Polygon</h3>
                        <p>Tambahkan placemark otomatis di tengah setiap polygon pada file KML Anda.</p>
                        <ol>
                            <li>Upload file KML yang berisi polygon</li>
                            <li>Tentukan pola nama untuk placemark (gunakan {n} untuk nomor urut)</li>
                            <li>Pilih icon yang diinginkan dari berbagai pilihan</li>
                            <li>Klik "Proses File" dan download hasilnya</li>
                        </ol>
                    </div>

                    <div class="feature-card">
                        <h3><i class="fa-solid fa-route"></i> Placemark di Path</h3>
                        <p>Buat placemark sepanjang path (LineString) dengan interval jarak tertentu.</p>
                        <ol>
                            <li>Upload file KML yang berisi path (LineString)</li>
                            <li>Tentukan pola nama untuk placemark</li>
                            <li>Pilih icon dan tentukan jarak antar placemark dalam meter</li>
                            <li>Klik "Proses File" untuk menghasilkan placemark sepanjang path</li>
                        </ol>
                    </div>

                    <div class="feature-card">
                        <h3><i class="fas fa-tags"></i> Rename Placemark</h3>
                        <p>Ubah nama placemark secara massal dengan berbagai opsi penamaan.</p>
                        <ol>
                            <li>Upload file KML yang berisi placemark</li>
                            <li>Pilih jenis label: numerik atau custom</li>
                            <li>Untuk numerik: tentukan awalan dan nomor awal (3 digit)</li>
                            <li>Untuk custom: tentukan pola nama dengan {n} untuk nomor urut</li>
                            <li>Klik "Proses File" untuk mendapatkan file dengan nama baru</li>
                        </ol>
                    </div>

                    <div class="feature-card">
                        <h3><i class="fas fa-list-ol"></i> Panduan Konversi</h3>
                        <ol>
                            <li>Pastikan file Excel (.xlsx) Anda memiliki kolom berisi data WKT (contoh: POLYGON, POINT,
                                LINESTRING)</li>
                            <li>Upload file Excel melalui form di bawah</li>
                            <li>Pilih nama kolom yang berisi data WKT (jika diperlukan)</li>
                            <li>Klik tombol "Proses File" untuk mengunduh file KML hasil konversi</li>
                        </ol>
                    </div>

                    <!-- Convert KML to CSV Feature -->
                    <div class="feature-card">
                        <h3><i class="fas fa-file-csv"></i> Convert KML to CSV</h3>
                        <p>Ubah data KML menjadi format CSV atau Excel agar mudah dianalisis atau digunakan di aplikasi
                            lain.</p>
                        <ol>
                            <li>Upload file KML yang berisi polygon, line, atau point.</li>
                            <li>Pilih jenis data yang ingin diekstrak (Polygon, LineString, atau Point).</li>
                            <li>Tentukan kolom yang ingin dimasukkan di CSV atau Excel.</li>
                            <li>Klik "Proses File" untuk mengunduh file hasil konversi.</li>
                        </ol>
                    </div>

                    <!-- Overlay Polygon Feature -->
                    <div class="feature-card">
                        <h3><i class="fas fa-draw-polygon"></i> Overlay Polygon</h3>
                        <p>Tampilkan beberapa polygon di peta secara interaktif untuk analisis area atau perbandingan
                            distribusi.</p>
                        <ol>
                            <li>Upload satu atau lebih file WKT/KML yang berisi polygon.</li>
                            <li>Sistem akan mem-parsing koordinat setiap polygon.</li>
                            <li>Polygon ditampilkan di peta menggunakan Leaflet.js.</li>
                            <li>Gunakan fitur zoom, pan, atau klik polygon untuk melihat detail koordinat.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Centroid Polygon Tool -->
            <div id="centroid-tool" class="tool-section hidden">
                <h1><i class="fas fa-map-pin"></i> Tambahkan Placemark di Centroid Polygon</h1>
                <form id="centroid-form" action="process.php?action=centroid" method="post"
                    enctype="multipart/form-data">

                    <!-- File Upload -->
                    <div class="form-group">
                        <label for="kmlFile1">Pilih File KML:</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="centroid-upload-box">
                                <input type="file" id="kmlFile1" name="kmlFile" accept=".kml,.kmz" required>
                                <div class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Klik untuk upload atau drag & drop</p>
                                    <p class="file-info">Format file: .kml atau .kmz</p>
                                </div>
                                <div class="file-selected-info" id="centroid-file-info"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Placemark Name -->
                    <div class="form-group">
                        <label for="placemarkName1">Pola Nama Placemark:</label>
                        <div class="input-group">
                            <i class="fas fa-tag"></i>
                            <input type="text" id="placemarkName1" name="placemarkName" value="Rumah {n}"
                                placeholder="Gunakan {n} untuk nomor urut">
                        </div>
                    </div>

                    <!-- Icon Selection -->
                    <div class="form-group">
                        <label>Pilih Icon Placemark:</label>
                        <div class="icon-preview">
                            <?php
                            $icons = [
                                'http://maps.google.com/mapfiles/kml/shapes/homegardenbusiness.png',
                                'http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png',
                                'http://maps.google.com/mapfiles/kml/shapes/shaded_dot.png',
                                'http://maps.google.com/mapfiles/kml/shapes/placemark_square.png',
                                'http://maps.google.com/mapfiles/kml/shapes/flag.png',
                                'http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png',
                                'http://maps.google.com/mapfiles/kml/paddle/ylw-circle.png',
                                'http://maps.google.com/mapfiles/kml/paddle/red-circle.png',
                            ];
                            foreach ($icons as $icon): ?>
                                <div class="icon-option" data-url="<?= $icon ?>">
                                    <img src="<?= $icon ?>" alt="Icon">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="placemarkIcon1" name="placemarkIcon" value="">
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="centroid-submit">Proses File</button>
                    <span class="loading" id="centroid-loading" style="display:none;"><i
                            class="fas fa-spinner fa-spin"></i> Memproses...</span>
                </form>

                <!-- Result / Download -->
                <div id="centroid-result" class="result hidden">
                    <h3><i class="fas fa-check-circle"></i> File berhasil diproses!</h3>
                    <p>Download file hasil:
                        <a href="#" id="centroid-download" download>
                            <i class="fas fa-download"></i> <span id="centroid-filename"></span>
                        </a>
                    </p>
                </div>

                <!-- JS Centroid -->
                <script src="scriptcentroid.js"></script>
            </div>

            <!-- Path Placemark Tool -->
            <div id="path-tool" class="tool-section hidden">
                <h1><i class="fa-solid fa-route"></i> Tambahkan Placemark di Path dengan Jarak Tertentu</h1>
                <form id="path-form" action="process.php?action=path" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="kmlFile2">Pilih File KML:</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="path-upload-box">
                                <input type="file" id="kmlFile2" name="kmlFile" accept=".kml,.kmz" required>
                                <div class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Klik untuk upload atau drag & drop</p>
                                    <p class="file-info">Format file: .kml</p>
                                </div>
                                <div class="file-selected-info" id="path-file-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="placemarkName2">Pola Nama Placemark:</label>
                        <div class="input-group">
                            <i class="fas fa-tag"></i>
                            <input type="text" id="placemarkName2" name="placemarkName" value="Titik {n}"
                                placeholder="Gunakan {n} untuk nomor urut">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Pilih Icon Placemark:</label>
                        <div class="icon-preview">
                            <?php
                            $pathIcons = [
                                'http://maps.google.com/mapfiles/kml/shapes/homegardenbusiness.png',
                                'http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png',
                                'http://maps.google.com/mapfiles/kml/shapes/shaded_dot.png',
                                'http://maps.google.com/mapfiles/kml/shapes/placemark_square.png',
                                'http://maps.google.com/mapfiles/kml/shapes/flag.png',
                                'http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png',
                                'http://maps.google.com/mapfiles/kml/paddle/ylw-circle.png',
                                'http://maps.google.com/mapfiles/kml/paddle/red-circle.png',
                            ];
                            foreach ($pathIcons as $icon): ?>
                                <div class="icon-option" data-url="<?= $icon ?>">
                                    <img src="<?= $icon ?>" alt="Icon">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Hidden input yang benar -->
                        <input type="hidden" id="placemarkIcon2" name="placemarkIcon" value="">
                    </div>

                    <div class="form-group">
                        <label for="distance">Jarak Antar Placemark (meter):</label>
                        <div class="input-group">
                            <i class="fas fa-ruler"></i>
                            <input type="number" id="distance" name="distance" value="100" min="1" required>
                        </div>
                    </div>

                    <button type="submit" id="path-submit"> Proses File
                        <div class="loading" id="path-loading"><i class="fas fa-spinner fa-spin"></i></div>
                    </button>
                </form>

                <div id="path-result" class="result">
                    <h3><i class="fas fa-check-circle"></i> File berhasil diproses!</h3>
                    <p>Download file hasil: <a href="#" id="path-download"><i class="fas fa-download"></i> <span
                                id="path-filename"></span></a></p>
                </div>
            </div>

            <!-- Script pilih icon -->
            <script>
                $(document).on('click', '.icon-option', function () {
                    // Hapus pilihan sebelumnya
                    $('.icon-option').removeClass('selected');

                    // Tandai icon terpilih
                    $(this).addClass('selected');

                    // Simpan URL ke hidden input
                    $('#placemarkIcon2').val($(this).data('url'));
                });
            </script>


            <!-- Rename Placemark Tool -->
            <div id="rename-tool" class="tool-section hidden">
                <h1><i class="fas fa-tags"></i> Ubah Nama Placemark Secara Massal</h1>
                <form id="rename-form" action="process.php?action=rename" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="kmlFile3">Pilih File KML:</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="rename-upload-box">
                                <input type="file" id="kmlFile3" name="kmlFile" accept=".kml,.kmz" required>
                                <div class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Klik untuk upload atau drag & drop</p>
                                    <p class="file-info">Format file: .kml</p>
                                </div>
                                <div class="file-selected-info" id="rename-file-info"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Pilih Jenis Label:</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="labelType" value="numeric" checked> <i
                                    class="fas fa-list-ol"></i> Numerik
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="labelType" value="custom"> <i class="fas fa-font"></i> Custom
                                Nama
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="numericOptions">
                        <label for="numericPrefix">Awalan Nama:</label>
                        <div class="input-group">
                            <i class="fas fa-heading"></i>
                            <input type="text" id="numericPrefix" name="numericPrefix" value="Lokasi"
                                placeholder="Contoh: TB atau Lokasi">
                        </div>
                        <label for="startNumber" style="margin-top: 15px;">Nomor Awal (3 digit):</label>
                        <div class="input-group">
                            <i class="fas fa-sort-numeric-up"></i>
                            <input type="number" id="startNumber" name="startNumber" value="1" min="1" max="999">
                        </div>
                        <label style="margin-top: 15px;">Penomoran:</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="numbering" value="sequential" checked> <i
                                    class="fas fa-sort-amount-up"></i> Berurutan
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="numbering" value="random"> <i class="fas fa-random"></i> Acak
                            </label>
                        </div>
                    </div>
                    <div class="form-group hidden" id="customOptions">
                        <label for="customName">Pola Nama:</label>
                        <div class="input-group">
                            <i class="fas fa-font"></i>
                            <input type="text" id="customName" name="customName" placeholder="Contoh: Titik {n}"
                                disabled>
                        </div>
                    </div>
                    <button type="submit" id="rename-submit"> Proses File
                        <div class="loading" id="rename-loading"><i class="fas fa-spinner fa-spin"></i></div>
                    </button>
                </form>

                <div id="rename-result" class="result">
                    <h3><i class="fas fa-check-circle"></i> File berhasil diproses!</h3>
                    <p>Download file hasil: <a href="#" id="rename-download"><i class="fas fa-download"></i> <span
                                id="rename-filename"></span></a></p>
                </div>
            </div>

            <!-- Convert Excel WKT to KML Section -->
            <div id="convert-tool" class="tool-section hidden">
                <h1><i class="fas fa-file-excel"></i> Convert Excel WKT to KML</h1>
                <form id="convert-form" action="wkt_to_kml.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="excelFile">Pilih File Excel:</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="convert-upload-box">
                                <input type="file" id="excelFile1" name="excelFile" accept=".xlsx,.xls" required>
                                <div class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Klik untuk upload atau drag & drop</p>
                                    <p class="file-info">Format file: .xlsx, .xls</p>
                                </div>
                                <div class="file-selected-info" id="convert-file-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="wktColumn">Nama Kolom WKT:</label>
                        <div class="input-group">
                            <i class="fas fa-draw-polygon"></i>
                            <input type="text" id="wktColumn" name="wktColumn" placeholder="Contoh: geometry" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nameColumn">Nama Kolom Label (opsional):</label>
                        <div class="input-group">
                            <i class="fas fa-tag"></i>
                            <input type="text" id="nameColumn" name="nameColumn" placeholder="Contoh: kelurahan">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="outputName">Nama File Output:</label>
                        <div class="input-group">
                            <i class="fas fa-file-signature"></i>
                            <input type="text" id="outputName" name="outputName" placeholder="contoh: hasil_kml"
                                required>
                        </div>
                    </div>

                    <button type="submit" id="convert-submit"> Proses File
                        <div class="loading" id="convert-loading"><i class="fas fa-spinner fa-spin"></i></div>
                    </button>
                </form>

                <div id="convert-result" class="result">
                    <h3><i class="fas fa-check-circle"></i> File berhasil diproses!</h3>
                    <p>Download file hasil:
                        <a href="#" id="convert-download"><i class="fas fa-download"></i>
                            <span id="convert-filename"></span>
                        </a>
                    </p>
                </div>
                <script src="wkt_to_kml.js"></script>
            </div>

            <!-- Convert Deskripsi Polygon KML to CSV/Excel Section -->
            <div id="convert_csv-tool" class="tool-section hidden">
                <h1><i class="fas fa-tags"></i> Convert Deskripsi Polygon KML</h1>
                <form id="kml-form" action="kml_to_csv.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="convert_kml_csv">

                    <div class="form-group">
                        <label for="kmlFile">Pilih File KML:</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="kml-upload-box">
                                <input type="file" id="kmlFile5" name="kmlFile" accept=".kml" required>
                                <div class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Klik untuk upload atau drag & drop</p>
                                    <p class="file-info">Format file: .kml</p>
                                </div>
                                <div class="file-selected-info" id="kml-file-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="outputCsvName">Nama File Output:</label>
                        <div class="input-group">
                            <i class="fas fa-file-signature"></i>
                            <input type="text" id="outputCsvName" name="outputCsvName" placeholder="contoh: hasil_data"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="outputFormat">Pilih Format Output:</label>
                        <select id="outputFormat" name="outputFormat" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel (.xlsx)</option>
                        </select>
                    </div>

                    <button type="submit" id="kml-submit"> Proses File
                        <div class="loading" id="kml-loading"><i class="fas fa-spinner fa-spin"></i></div>
                    </button>
                </form>

                <div id="kml-result" class="result">
                    <h3><i class="fas fa-check-circle"></i> File berhasil diproses!</h3>
                    <p>Download file hasil:
                        <a href="#" id="kml-download"><i class="fas fa-download"></i>
                            <span id="kml-filename"></span>
                        </a>
                    </p>
                </div>
                <script src="kml_to_csv.js"></script>
            </div>

            <!-- STO Overlay Section -->
            <div id="sto-tool" class="tool-section hidden">
                <h1><i class="fas fa-draw-polygon"></i> Microdemand - STO Overlay</h1>
                <form id="sto-form" action="overlay_polygon.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="microFile">File KML Microdemand:</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="micro-upload-box">
                                <input type="file" id="microFile" name="microFile" accept=".kml" required>
                                <div class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Klik untuk upload atau drag & drop</p>
                                    <p class="file-info">Format file: .kml</p>
                                </div>
                                <!-- info hasil pilih file -->
                                <div class="file-selected-info" id="micro-file-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="stoFile">File KML STO:</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="sto-upload-box">
                                <input type="file" id="stoFile" name="stoFile" accept=".kml" required>
                                <div class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Klik untuk upload atau drag & drop</p>
                                    <p class="file-info">Format file: .kml</p>
                                </div>
                                <!-- info hasil pilih file -->
                                <div class="file-selected-info" id="sto-file-info"></div>
                            </div>
                        </div>
                    </div>


                    <button type="submit" id="sto-submit"> Proses Overlay
                        <div class="loading" id="sto-loading"><i class="fas fa-spinner fa-spin"></i></div>
                    </button>
                </form>

                <div id="sto-result" class="result">
                    <h3><i class="fas fa-check-circle"></i> Hasil berhasil diproses!</h3>
                    <p>Download file hasil:
                        <a href="#" id="sto-download"><i class="fas fa-download"></i>
                            <span id="sto-filename"></span>
                        </a>
                    </p>
                </div>

                <script src="overlay_polygon.js"></script>
            </div>



            <script>
                // JS untuk menampilkan nama file setelah dipilih
                document.getElementById('excelFile').addEventListener('change', function (e) {
                    let fileName = e.target.files.length ? e.target.files[0].name : "";
                    document.getElementById('convert-file-info').innerText = fileName ? "File dipilih: " + fileName : "";
                });
            </script>
        </div>
    </div>

    <div class="credit">
        2025 | SDI Surabaya Utara
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Navigation
        document.querySelectorAll('.nav-link').forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const target = this.dataset.target;

                // Hide all sections
                document.querySelectorAll('.tool-section').forEach(section => {
                    section.classList.add('hidden');
                });

                // Show target section
                document.getElementById(target).classList.remove('hidden');

                // Update active nav
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                });
                this.classList.add('active');

                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        // Sidebar toggle
        document.querySelector('.sidebar-toggle').addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('collapsed');
        });

        // Icon selection
        document.querySelectorAll('.icon-option').forEach(icon => {
            icon.addEventListener('click', function () {
                const container = this.closest('.form-group');
                const hiddenInput = container.querySelector('input[type="hidden"]');

                // Remove selected class from all icons in this group
                container.querySelectorAll('.icon-option').forEach(i => {
                    i.classList.remove('selected');
                });

                // Add selected class to clicked icon
                this.classList.add('selected');

                // Update hidden input value
                hiddenInput.value = this.dataset.url;
            });
        });

        // Label type toggle
        document.querySelectorAll('input[name="labelType"]').forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.value === 'numeric') {
                    document.getElementById('numericOptions').classList.remove('hidden');
                    document.getElementById('customOptions').classList.add('hidden');
                    document.getElementById('customName').disabled = true;
                } else {
                    document.getElementById('numericOptions').classList.add('hidden');
                    document.getElementById('customOptions').classList.remove('hidden');
                    document.getElementById('customName').disabled = false;
                }
            });
        });

        // Initialize first icon as selected
        document.querySelectorAll('.icon-preview').forEach(container => {
            const firstIcon = container.querySelector('.icon-option');
            if (firstIcon) {
                firstIcon.classList.add('selected');
            }
        });

        // File upload handling
        function setupFileUpload(inputId, boxId, infoId) {
            const fileInput = document.getElementById(inputId);
            const uploadBox = document.getElementById(boxId);
            const fileInfo = document.getElementById(infoId);

            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    uploadBox.classList.add('has-file');

                    // Format file size
                    const sizeInKB = file.size / 1024;
                    let sizeText;
                    if (sizeInKB > 1024) {
                        sizeText = (sizeInKB / 1024).toFixed(2) + ' MB';
                    } else {
                        sizeText = Math.round(sizeInKB) + ' KB';
                    }

                    // Set file info
                    fileInfo.textContent = `${file.name} (${sizeText})`;
                    fileInfo.classList.add('active');
                } else {
                    uploadBox.classList.remove('has-file');
                    fileInfo.classList.remove('active');
                }
            });
        }

        // Setup file upload handlers for each form
        setupFileUpload('kmlFile1', 'centroid-upload-box', 'centroid-file-info');
        setupFileUpload('kmlFile2', 'path-upload-box', 'path-file-info');
        setupFileUpload('kmlFile3', 'rename-upload-box', 'rename-file-info');

        // Form submission with AJAX
        $(document).ready(function () {
            // Centroid Form
            $('#centroid-form').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);
                var submitBtn = $('#centroid-submit');
                var loading = $('#centroid-loading');
                var resultBox = $('#centroid-result');

                // Validasi file
                var fileInput = $('#kmlFile1')[0];
                if (fileInput.files.length === 0) {
                    alert('Silakan pilih file KML/KMZ terlebih dahulu');
                    return;
                }

                // Reset result box
                resultBox.hide();

                // Show loading
                submitBtn.prop('disabled', true);
                loading.addClass('active');

                // Prepare FormData
                var formData = new FormData(this);

                // AJAX request
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (data) {
                        if (data.success) {
                            // Update result box
                            $('#centroid-filename').text(data.filename);
                            $('#centroid-download').attr('href', 'output/' + data.filename);
                            resultBox.fadeIn();

                            // Scroll to result
                            $('html, body').animate({
                                scrollTop: resultBox.offset().top - 100
                            }, 500);
                        } else {
                            alert('Error: ' + (data.message || 'Gagal memproses file'));
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('Error: ' + error);
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false);
                        loading.removeClass('active');
                    }
                });
            });

            // Path Form
            $('#path-form').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);
                var submitBtn = $('#path-submit');
                var loading = $('#path-loading');
                var resultBox = $('#path-result');

                // Validasi file
                var fileInput = $('#kmlFile2')[0];
                if (fileInput.files.length === 0) {
                    alert('Silakan pilih file KML/KMZ terlebih dahulu');
                    return;
                }

                // Reset result box
                resultBox.hide();

                // Show loading
                submitBtn.prop('disabled', true);
                loading.addClass('active');

                // Prepare FormData
                var formData = new FormData(this);

                // AJAX request
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (data) {
                        if (data.success) {
                            // Update result box
                            $('#path-filename').text(data.filename);
                            $('#path-download').attr('href', 'output/' + data.filename);
                            resultBox.fadeIn();

                            // Scroll to result
                            $('html, body').animate({
                                scrollTop: resultBox.offset().top - 100
                            }, 500);
                        } else {
                            alert('Error: ' + (data.message || 'Gagal memproses file'));
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('Error: ' + error);
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false);
                        loading.removeClass('active');
                    }
                });
            });

            // Rename Form
            $('#rename-form').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);
                var submitBtn = $('#rename-submit');
                var loading = $('#rename-loading');
                var resultBox = $('#rename-result');

                // Validasi file
                var fileInput = $('#kmlFile3')[0];
                if (fileInput.files.length === 0) {
                    alert('Silakan pilih file KML/KMZ terlebih dahulu');
                    return;
                }

                // Reset result box
                resultBox.hide();

                // Show loading
                submitBtn.prop('disabled', true);
                loading.addClass('active');

                // Prepare FormData
                var formData = new FormData(this);

                // AJAX request
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (data) {
                        if (data.success) {
                            // Update result box
                            $('#rename-filename').text(data.filename);
                            $('#rename-download').attr('href', 'output/' + data.filename);
                            resultBox.fadeIn();

                            // Scroll to result
                            $('html, body').animate({
                                scrollTop: resultBox.offset().top - 100
                            }, 500);
                        } else {
                            alert('Error: ' + (data.message || 'Gagal memproses file'));
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('Error: ' + error);
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false);
                        loading.removeClass('active');
                    }
                });
            });
        });
    </script>
</body>

</html>
</body>

</html>