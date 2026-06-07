<?php
// Read full transcript to get rubric data
$transcriptPath = 'C:/Users/natch/.gemini/antigravity-ide/brain/20cc82b2-7b37-4295-b03d-00f80e2c0361/.system_generated/logs/transcript.jsonl';

$lines = file($transcriptPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$found = false;
foreach ($lines as $line) {
    $data = json_decode($line, true);
    if ($data && isset($data['type']) && $data['type'] === 'USER_INPUT' && isset($data['content'])) {
        $content = $data['content'];
        if (strlen($content) > 1000 && strpos($content, 'ตอนที่') !== false && strpos($content, 'รายการประเมิน') !== false) {
            file_put_contents('D:/cnpapp/rubric_full.txt', $content);
            echo "OK step=" . ($data['step_index'] ?? 0) . " len=" . strlen($content) . "\n\n";
            echo substr($content, 0, 5000);
            $found = true;
            break;
        }
    }
}

if (!$found) echo "NOT FOUND";
?>
