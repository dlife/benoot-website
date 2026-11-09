<?php
/**
 * prints.php — dynamische galerij + 3D-modellen voor benoot.be (Synology Web Station)
 * Scant automatisch:
 *   web_images/   -> foto's van prints (.jpg / .jpeg / .png / .webp)
 *   web_videos/   -> optionele video's (.mp4), gekoppeld op bestandsnaam
 *   web_models/   -> 3D-modellen (.stl / .glb)
 *   captions.json -> optionele metadata per print; kan ook een model koppelen
 *                    aan een print met "model": "<bestandsnaam zonder extensie>"
 * Koppeling gebeurt standaard op bestandsnaam (foto.jpg <-> foto.stl);
 * met captions.json kan een model aan een andere print gekoppeld worden.
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
    $base = trim($base);
    if (function_exists('mb_convert_case')) {
        return mb_convert_case($base, MB_CASE_TITLE, 'UTF-8');
    }
    return ucwords($base); // fallback zonder mbstring-extensie
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

/* Model zoeken: eerst de koppeling uit captions.json, anders de eigen bestandsnaam.
   Geeft [pad => pad of null, sleutel => gebruikte modelsleutel] terug. */
function resolveModel(array $meta, string $id, array $models): array
{
    $key = $meta['model'] ?? $id;
    $key = preg_replace('/\.(stl|glb)$/i', '', (string)$key);
    return [$models[$key] ?? null, $key];
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
$usedIds = [];
$usedModelKeys = [];

foreach ($images as $image) {
    $id = pathinfo($image, PATHINFO_FILENAME);
    $meta = $captions[$id] ?? [];
    if (!empty($meta['verborgen'])) continue;

    [$modelPath, $modelKey] = resolveModel($meta, $id, $models);
    if ($modelPath !== null) $usedModelKeys[] = $modelKey;

    $prints[] = [
        'id'           => $id,
        'titel'        => $meta['titel'] ?? titleFromFile($image),
        'omschrijving' => $meta['omschrijving'] ?? '',
        'categorie'    => $meta['categorie'] ?? 'functioneel',
        'afbeelding'   => 'web_images/' . rawurlencode($image),
        'video'        => isset($videoByName[$id]) ? 'web_videos/' . rawurlencode($videoByName[$id]) : null,
        'model'        => $modelPath,
        'materiaal'    => $meta['materiaal'] ?? 'PLA',
        'printtijd'    => $meta['printtijd'] ?? null,
        'datum'        => date('Y-m-d', isset($meta['datum']) ? strtotime($meta['datum']) : filemtime(IMAGE_DIR . $image)),
    ];
    $usedIds[] = $id;
}

$videoOnly = [];
foreach ($videos as $video) {
    $id = pathinfo($video, PATHINFO_FILENAME);
    if (in_array($id, $usedIds, true)) continue; // al gekoppeld aan een foto
    $meta = $captions[$id] ?? [];
    if (!empty($meta['verborgen'])) continue;

    [$modelPath, $modelKey] = resolveModel($meta, $id, $models);
    if ($modelPath !== null) $usedModelKeys[] = $modelKey;

    $videoOnly[] = [
        'id'           => $id,
        'titel'        => $meta['titel'] ?? titleFromFile($video),
        'omschrijving' => $meta['omschrijving'] ?? '',
        'categorie'    => $meta['categorie'] ?? 'functioneel',
        'afbeelding'   => null,
        'video'        => 'web_videos/' . rawurlencode($video),
        'model'        => $modelPath,
        'materiaal'    => $meta['materiaal'] ?? 'PLA',
        'printtijd'    => $meta['printtijd'] ?? null,
        'datum'        => date('Y-m-d', filemtime(VIDEO_DIR . $video)),
    ];
    $usedIds[] = $id;
}

/* Modellen zonder foto en video: eigen 3D-kaartje in de galerij */
foreach ($models as $mid => $rel) {
    if (in_array($mid, $usedIds, true)) continue;
    if (in_array($mid, $usedModelKeys, true)) continue; // al aan een print gekoppeld
    $meta = $captions[$mid] ?? [];
    if (!empty($meta['verborgen'])) continue;

    $prints[] = [
        'id'           => $mid,
        'titel'        => $meta['titel'] ?? titleFromFile($mid),
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
