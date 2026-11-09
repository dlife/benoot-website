<?php
/**
 * prints.php — dynamische galerij + 3D-modellen voor benoot.be (Synology Web Station)
 * Scant automatisch:
 *   web_images/   -> foto's van prints (.jpg / .jpeg / .png / .webp)
 *   web_videos/   -> optionele video's (.mp4), gekoppeld op bestandsnaam
 *   web_models/   -> 3D-modellen (.stl / .glb), ook gekoppeld op bestandsnaam
 *                    (modellen in web_images/ worden eveneens herkend)
 *   captions.json -> optionele metadata per print (zie onderaan)
 * Nieuwe print = foto droppen. Nieuw 3D-object = .stl droppen. Klaar.
 */

header('Content-Type: application/json; charset=utf-8');

const IMAGE_DIR = __DIR__ . '/web_images/';
const VIDEO_DIR = __DIR__ . '/web_videos/';
const MODEL_DIR = __DIR__ . '/web_models/';
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

function collectModels(): array
{
    $out = [];
    $dirs = [
        'web_models' => ['dir' => MODEL_DIR, 'ext' => ['stl', 'glb']],
        'web_images' => ['dir' => IMAGE_DIR, 'ext' => ['stl', 'glb']],
    ];
    foreach ($dirs as $rel => $cfg) {
        foreach (scanDirectory($cfg['dir'], $cfg['ext']) as $f) {
            $id = pathinfo($f, PATHINFO_FILENAME);
            if (!isset($out[$id])) {
                $out[$id] = $rel . '/' . rawurlencode($f);
            }
        }
    }
    return $out;
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
$models = collectModels();

$videoByName = [];
foreach ($videos as $video) {
    $videoByName[pathinfo($video, PATHINFO_FILENAME)] = $video;
}

$prints = [];
foreach ($images as $image) {
    $id = pathinfo($image, PATHINFO_FILENAME);
    $meta = $captions[$id] ?? [];

    if (!empty($meta['verborgen'])) continue; // bv. portret, geen print

    $prints[] = [
        'id'           => $id,
        'titel'        => $meta['titel'] ?? titleFromFile($image),
        'omschrijving' => $meta['omschrijving'] ?? '',
        'categorie'    => $meta['categorie'] ?? 'functioneel',
        'afbeelding'   => 'web_images/' . rawurlencode($image),
        'video'        => isset($videoByName[$id]) ? 'web_videos/' . rawurlencode($videoByName[$id]) : null,
        'model'        => $models[$id] ?? null,
        'materiaal'    => $meta['materiaal'] ?? 'PLA',
        'printtijd'    => $meta['printtijd'] ?? null,
        'datum'        => date('Y-m-d', isset($meta['datum']) ? strtotime($meta['datum']) : filemtime(IMAGE_DIR . $image)),
    ];
}

$videoOnly = [];
foreach ($videos as $video) {
    $id = pathinfo($video, PATHINFO_FILENAME);
    if (isset($videoByName[$id]) && array_filter($prints, fn($p) => $p['id'] === $id)) {
        continue; // al gekoppeld aan een foto
    }
    $meta = $captions[$id] ?? [];
    if (!empty($meta['verborgen'])) continue;

    $videoOnly[] = [
        'id'           => $id,
        'titel'        => $meta['titel'] ?? titleFromFile($video),
        'omschrijving' => $meta['omschrijving'] ?? '',
        'categorie'    => $meta['categorie'] ?? 'functioneel',
        'afbeelding'   => null,
        'video'        => 'web_videos/' . rawurlencode($video),
        'model'        => $models[$id] ?? null,
        'materiaal'    => $meta['materiaal'] ?? 'PLA',
        'printtijd'    => $meta['printtijd'] ?? null,
        'datum'        => date('Y-m-d', filemtime(VIDEO_DIR . $video)),
    ];
}

// Modellen zonder foto en zonder video: eigen galerij-kaartje (3D-only)
$usedIds = [];
foreach (array_merge($prints, $videoOnly) as $p) {
    $usedIds[] = $p['id'];
}
foreach ($models as $id => $rel) {
    if (in_array($id, $usedIds, true)) continue;
    $meta = $captions[$id] ?? [];
    if (!empty($meta['verborgen'])) continue;

    $prints[] = [
        'id'           => $id,
        'titel'        => $meta['titel'] ?? titleFromFile($id),
        'omschrijving' => $meta['omschrijving'] ?? '',
        'categorie'    => $meta['categorie'] ?? 'functioneel',
        'afbeelding'   => null,
        'video'        => null,
        'model'        => $rel,
        'materiaal'    => $meta['materiaal'] ?? 'PLA',
        'printtijd'    => $meta['printtijd'] ?? null,
        'datum'        => date('Y-m-d'),
    ];
}

echo json_encode(array_merge($prints, $videoOnly), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/* ===========================================================================
 * OPTIONELE METADATA: captions.json (naast prints.php)
 * Sleutel = bestandsnaam zonder extensie. Alle velden optioneel.
 * "verborgen": true laat een bestand buiten de galerij.
 * =========================================================================== */
