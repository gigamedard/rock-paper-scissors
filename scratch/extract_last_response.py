import json
import sys

log_path = r'C:\Users\GWX1223153\.gemini\antigravity\brain\33d66d7a-87f8-4de7-b581-def39e27e227\.system_generated\logs\overview.txt'
output_path = r'd:\dev\PHP\rock-paper-scissors\scratch\last_response.md'

with open(log_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Find the last MODEL response
for line in reversed(lines):
    try:
        data = json.loads(line)
        if data.get('source') == 'MODEL' and data.get('type') in ['PLANNER_RESPONSE', 'EXECUTION_RESPONSE']:
            content = data.get('content', '')
            if content:
                with open(output_path, 'w', encoding='utf-8') as out:
                    out.write(content)
                print(f"Content written to {output_path}")
                sys.exit(0)
    except Exception as e:
        continue

print("No MODEL response found")
sys.exit(1)
