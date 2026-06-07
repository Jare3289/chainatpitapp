<?php
$file = 'C:/Users/natch/.gemini/antigravity-ide/brain/20cc82b2-7b37-4295-b03d-00f80e2c0361/.system_generated/logs/transcript.jsonl';
if (file_exists($file)) {
    $lines = file($file);
    foreach (array_reverse($lines) as $line) {
        $data = json_decode($line, true);
        if ($data && isset($data['type']) && $data['type'] === 'USER_INPUT') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $data['content'];
            exit;
        }
    }
} else {
    echo "File not found";
}
