#!/usr/bin/env python3

import re

with open('src/Entity/User.php', 'r', encoding='utf-8') as f:
    content = f.read()

lines = content.split('\n')
seen_properties = {}  # property_name -> line_number of first occurrence
output_lines = []
i = 0

property_pattern = re.compile(r'^\s*private\s+(?:[^\s]+ )*\$(\w+)')

while i < len(lines):
    line = lines[i]
    match = property_pattern.match(line)
    
    if match:
        prop_name = match.group(1)
        
        # If we've seen this property before, skip the entire duplicate block
        if prop_name in seen_properties:
            # Skip until we find the next @ORM attribute or private property
            while i < len(lines):
                if i > 0 and (lines[i].strip().startswith('#[') or property_pattern.match(lines[i])):
                    break
                i += 1
            continue
        
        seen_properties[prop_name] = i
    
    output_lines.append(line)
    i += 1

with open('src/Entity/User.php', 'w', encoding='utf-8') as f:
    f.write('\n'.join(output_lines))

print("Done! Duplicates removed from User.php")
