<?php
session_start();
$conn = new mysqli("localhost:3307", "root", "", "girlscodingacademydb");
if ($conn->connect_error) die("DB connection failed");
$conn->set_charset("utf8mb4");

// Year filter
$year_filter = $_GET['year'] ?? 'all';

// Collect images & videos
$media = [];
$folders = ['Uploads/', 'imageuploads/', 'images/gallery/'];

foreach ($folders as $folder) {
    if (!is_dir($folder)) continue;

    $files = scandir($folder);
    foreach ($files as $file) {                     // ← FIXED: added "as $file"
        if ($file === '.' || $file === '..') continue;

        $path = $folder . $file;
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Images
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $year = date('Y', filemtime($path));
            if ($year_filter === 'all' || $year_filter == $year) {
                $media[] = [
                    'type' => 'image',
                    'src'  => $path,
                    'year' => $year
                ];
            }
        }

        // Videos
        if (in_array($ext, ['mp4', 'webm', 'mov', 'avi'])) {
            $year = date('Y', filemtime($path));
            if ($year_filter === 'all' || $year_filter == $year) {
                $media[] = [
                    'type' => 'video',
                    'src'  => $path,
                    'thumb'=> 'images/video-thumb.jpg', // change if you want different placeholder
                    'year' => $year
                ];
            }
        }
    }
}

// Sort newest first
usort($media, fn($a, $b) => $b['year'] <=> $a['year']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery • Girls Coding Academy</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Lightbox2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(to bottom, #f8fafc, #e0e7ff); }
        .masonry { column-count: 2; column-gap: 1rem; }
        @media (min-width: 768px) { .masonry { column-count: 3; } }
        @media (min-width: 1024px) { .masonry { column-count: 4; } }

        .masonry-item {
            break-inside: avoid;
            margin-bottom: 1rem;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: all 0.4s ease;
        }
        .masonry-item:hover { transform: translateY(-10px) scale(1.02); box-shadow: 0 20px 40px rgba(0,0,0,0.25); }

        }

        .play-icon {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(236,72,153,0.9);
            color: white;
            width: 80px; height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            opacity: 0;
            transition: opacity 0.4s;
            pointer-events: none;
        }
        .masonry-item:hover .play-icon { opacity: 1; }

        .filter-btn.active {
            background: linear-gradient(135deg, #ec4899, #a855f7);
            color: white;
            transform: scale(1.05);
        }
    </style>
</head>
<body>

    <!-- Hero -->
    <section class="relative h-96 bg-gradient-to-br from-purple-700 via-pink-600 to-rose-600 flex items-center justify-center text-white">
        <div class="text-center z-10">
            <h1 class="text-6xl md:text-8xl font-black mb-6">Gallery</h1>
            <p class="text-2xl md:text-3xl font-light opacity-90">Moments That Inspire • Memories That Last</p>
        </div>
        <div class="absolute inset-0 bg-black/40"></div>
    </section>

    <!-- Filter -->
    <section class="py-10 bg-white shadow-lg sticky top-0 z-40">
        <div class="container mx-auto px-6 text-center">
            <div class="flex flex-wrap justify-center gap-4">
                <?php $years = ['all', '2025', '2024', '2023']; ?>
                <?php foreach($years as $y): ?>
                <a href="?year=<?= $y ?>" 
                   class="filter-btn px-8 py-3 rounded-full font-bold text-lg <?= ($year_filter==$y || ($y=='all' && !$year_filter)) ? 'active text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                    <?= $y=='all' ? 'All Time' : $y ?>
                </a>
                <?php endforeach; ?>
            </div>
            <p class="mt-6 text-lg text-gray-600">
                Showing <strong><?= count($media) ?></strong> beautiful memories
            </p>
        </div>
    </section>

    <!-- Masonry Gallery -->
    <section class="py-16 px-6">
        <div class="container mx-auto">
            <?php if (empty($media)): ?>
                <div class="text-center py-20">
                    <i class="bi bi-image text-8xl text-gray-300 mb-6"></i>
                    <p class="text-3xl text-gray-500">No media found for this year.</p>
                </div>
            <?php else: ?>
                <div class="masonry">
                    <?php foreach($media as $item): ?>
                        <div class="masonry-item relative">
                            <?php if ($item['type'] === 'video'): ?>
                                <a href="<?= $item['src'] ?>" data-lightbox="gca-gallery" data-title="GCA Video">
                                    <img src="<?= $item['thumb'] ?>" alt="Video" class="w-full">
                                    <div class="play-icon">
                                        <i class="bi bi-play-fill"></i>
                                    </div>
                                </a>
                            <?php else: ?>
                                <a href="<?= $item['src'] ?>" data-lightbox="gca-gallery" data-title="Girls Coding Academy <?= $item['year'] ?>">
                                    <img src="<?= $item['src'] ?>" alt="Photo" class="w-full">
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-24 bg-gradient-to-r from-purple-700 to-pink-700 text-white text-center">
        <div class="container mx-auto px-6">
            <h2 class="text-5xl md:text-6xl font-black mb-8">Be Part of Our Next Memory</h2>
            <p class="text-2xl mb-12 max-w-3xl mx-auto opacity-90">
                Join Girls Coding Academy and create moments you'll cherish forever
            </p>
            <a href="prospectus.php" class="inline-block px-20 py-7 bg-white text-purple-700 rounded-full text-3xl font-bold hover:bg-gray-100 transform hover:scale-110 transition shadow-2xl">
                Enroll Today
            </a>
        </div>
    </section>

    <script>
        lightbox.option({
            resizeDuration: 300,
            wrapAround: true,
            albumLabel: "Image %1 of %2"
        });
    </script>
</body>
</html>