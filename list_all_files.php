<?php
function list_all_files($dir) {
    $result = [];
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            $result = array_merge($result, list_all_files($path));
        } else {
            $result[] = $path;
        }
    }
    return $result;
}

$all_files = list_all_files('d:/cnpapp');
foreach ($all_files as $f) {
    if (strpos($f, 'node_modules') !== false || strpos($f, '.git') !== false || strpos($f, '.gemini') !== false) continue;
    echo $f . " (" . filesize($f) . " bytes)\n";
}
