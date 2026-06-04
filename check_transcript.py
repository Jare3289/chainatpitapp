import json

log_path = r"C:\Users\natch\.gemini\antigravity-ide\brain\d41f3279-dd35-4567-8c2a-e23d669a8a73\.system_generated\logs\transcript.jsonl"

with open(log_path, 'r', encoding='utf-8') as f:
    for line in f:
        try:
            data = json.loads(line)
            content = data.get('content', '')
            if "นี่คือแบบประเมินแผน" in content:
                print("FOUND!")
                print(content)
                break
        except Exception as e:
            pass
