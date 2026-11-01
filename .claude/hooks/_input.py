"""Print one field from the hook JSON on stdin, e.g. `tool_input.file_path`."""
import json
import sys

value = json.load(sys.stdin)
for key in sys.argv[1].split('.'):
    value = value.get(key, '') if isinstance(value, dict) else ''
print(value if isinstance(value, str) else '')
