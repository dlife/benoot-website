<?php
/**
 * prints.php — dynamische galerij voor benoot.be (Synology Web Station)
 * Scant automatisch:
 *   web_images/   -> foto's van prints (.jpg / .jpeg / .png / .webp)
 *   web_videos/   -> optionele video's (.mp4), gekoppeld op bestandsnaam
 *   captions.json -> optionele metadata per print (zie onderaan)
 * Nieuwe print toevoegen = foto in web_images/ droppen. Klaar.
 */

header('Content-Type: application/json; charset=utf-8');

const IMAGE_DIR = __DIR__ . '/web_images/';
const VIDEO_DIR = __DIR__ . '/web_videos/';
const CAPTIONS_FILE = __DIR__ . '/captions.json';

function scanDirectory(string $dir, array $extensions): array
{
    $files = [];
    if (!is_dir($dir)) {
        return $files;
    }
    foreach (scandir($dir) as $entry) {
        if ($entry[0] === '.') continue;
        $path = $dir . $entry;
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (in_array($ext, $extensions, true)) {
            $files[$entry] = filemtime($path);
        }
    }
    arsort($files); // nieuwste eerst
    return array_keys($files);
}

function titleFromFile(string $filename): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $base = preg_replace('/[-_]+/', ' ', $base);
    return mb_convert_case(trim($base), MB_CASE_TITLE, 'UTF-8');
}

$captions = [];
if (is_file(CAPTIONS_FILE)) {
    $decoded = json_decode(file_get_contents(CAPTIONS_FILE), true);
    if (is_array($decoded)) {
        $captions = $decoded;
    }
}

$images = scanDirectory(IMAGE_DIR, ['jpg', 'jpeg', 'png', 'webp']);
$videos = scanDirectory(VIDEO_DIR, ['mp4']);

$videoByName = [];
foreach ($videos as $video) {
    $videoByName[pathinfo($video, PATHINFO_FILENAME)] = $video;
}

$prints = [];
foreach ($images as $image) {
    $id = pathinfo($image, PATHINFO_FILENAME);
    $meta = $captions[$id] ?? [];

    $prints[] = [
        'id'           => $id,
        'titel'        => $meta['titel'] ?? titleFromFile($image),
        'omschrijving' => $meta['omschrijving'] ?? '',
        'categorie'    => $meta['categorie'] ?? 'functioneel',
        'afbeelding'   => 'web_images/' . $image,
        'video'        => isset($videoByName[$id]) ? 'web_videos/' . $videoByName[$id] : null,
        'materiaal'    => $meta['materiaal'] ?? 'PLA',
        'printtijd'    => $meta['printtijd'] ?? null,
        'datum'        => date('Y-m-d', isset($meta['datum']) ? strtotime($meta['datum']) : filemtime(IMAGE_DIR . $image)),
    ];
}

$imageIds = array_column($prints, 'id');
foreach ($videos as $video) {
    $id = pathinfo($video, PATHINFO_FILENAME);
    if (!in_array($id, $imageIds, true)) {
        $meta = $captions[$id] ?? [];
        $prints[] = [
            'id'           => $id,
            'titel'        => $meta['titel'] ?? titleFromFile($video),
            'omschrijving' => $meta['omschrijving'] ?? '',
            'categorie'    => $meta['categorie'] ?? 'functioneel',
            'afbeelding'   => null,
            'video'        => 'web_videos/' . $video,
            'materiaal'    => $meta['materiaal'] ?? 'PLA',
            'printtijd'    => $meta['printtijd'] ?? null,
            'datum'        => date('Y-m-d', filemtime(VIDEO_DIR . $video)),
        ];
    }
}

echo json_encode($prints, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/* ===========================================================================
 * OPTIONELE METADATA: captions.json (naast prints.php)
 * {
 *   "keukenrolhouder": {
 *     "titel": "Keukenrolhouder",
 *     "omschrijving": "Wandhouder in PETG met afneembare klep.",
 *     "categorie": "functioneel",
 *     "materiaal": "PETG",
 *     "printtijd": 6.5,
 *     "datum": "2026-10-20"
 *   }
 * }
 * Alle velden zijn optioneel; ontbrekende vullen zich automatisch aan.
 * =========================================================================== */
