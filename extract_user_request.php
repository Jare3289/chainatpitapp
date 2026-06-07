<?php
$file = 'C:/Users/natch/.gemini/antigravity-ide/brain/20cc82b2-7b37-4295-b03d-00f80e2c0361/.system_generated/logs/transcript.jsonl';
if (file_exists($file)) {
    $lines = file($file);
    foreach (array_reverse($lines) as $line) {
        $data = json_decode($line, true);
        if ($data && isset($data['type']) && $data['type'] === 'USER_INPUT') {
            file_put_contents('d:/cnpapp/user_request_untruncated.txt', $data['content']);
            echo "SUCCESS: Saved to user_request_untruncated.txt, length: " . strlen($data['content']);
            exit;
        }
    }
    echo "USER_INPUT not found";
} else {
    echo "File not found";
}
