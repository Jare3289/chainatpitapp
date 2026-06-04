import re
import js2py

try:
    with open('views/supervision_booking.html', 'r', encoding='utf-8') as f:
        content = f.read()
    
    script_match = re.search(r'<script>(.*?)</script>', content, re.DOTALL)
    if script_match:
        script = script_match.group(1)
        print("Script extracted. Length:", len(script))
        # Simple brace counting
        open_braces = script.count('{')
        close_braces = script.count('}')
        print("Braces: { =", open_braces, "} =", close_braces)
        
        # We can also check paren matching
        open_parens = script.count('(')
        close_parens = script.count(')')
        print("Parens: ( =", open_parens, ") =", close_parens)
    else:
        print("No script found.")
except Exception as e:
    print(e)
