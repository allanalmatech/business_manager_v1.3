#!/usr/bin/env python3
"""Static consistency checks for the Flutter sources (no toolchain available)."""
import os, re, sys
from collections import defaultdict

ROOT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'mobile')
LIB = os.path.join(ROOT, 'lib')

def strip_code(src):
    """Strings first, then comments: otherwise "https://" looks like a comment."""
    s = re.sub(r"\'\'\'.*?\'\'\'|\"\"\".*?\"\"\"", "''", src, flags=re.S)
    s = re.sub(r"r?'(?:\\.|[^'\\\n])*'", "''", s)
    s = re.sub(r'r?"(?:\\.|[^"\\\n])*"', "''", s)
    s = re.sub(r"//[^\n]*", "", s)
    s = re.sub(r"/\*.*?\*/", "", s, flags=re.S)
    return s

problems = []
files = []
for dirpath, _, names in os.walk(LIB):
    for n in names:
        if n.endswith('.dart'):
            files.append(os.path.join(dirpath, n))

declared = defaultdict(list)      # class/enum/func name -> files
imports_of = {}
texts = {}

for f in files:
    src = open(f).read()
    texts[f] = src
    # balanced delimiters, ignoring strings and comments
    stripped = strip_code(src)
    for open_c, close_c, label in (('{','}','braces'), ('(',')','parens'), ('[',']','brackets')):
        if stripped.count(open_c) != stripped.count(close_c):
            problems.append(f"{os.path.relpath(f, ROOT)}: unbalanced {label} "
                            f"({stripped.count(open_c)} vs {stripped.count(close_c)})")
    for m in re.finditer(r"^(?:abstract |sealed |final |base )*(?:class|enum|mixin|extension)\s+(\w+)", src, re.M):
        declared[m.group(1)].append(f)
    for m in re.finditer(r"^(?:[\w<>,\s\?]+\s+)?(\w+)\s*\([^)]*\)\s*(?:async\s*)?[{=]", src, re.M):
        pass
    imports_of[f] = re.findall(r"import\s+'([^']+)'", src)

# relative imports resolve to real files
for f, imps in imports_of.items():
    for imp in imps:
        if imp.startswith('package:') or imp.startswith('dart:'):
            continue
        target = os.path.normpath(os.path.join(os.path.dirname(f), imp))
        if not os.path.exists(target):
            problems.append(f"{os.path.relpath(f, ROOT)}: imports missing file '{imp}'")

# duplicate class names across the app (would clash when both are imported)
for name, where in declared.items():
    if len(where) > 1 and not name.startswith('_'):
        problems.append(f"class {name} declared in {len(where)} files: " +
                        ', '.join(os.path.relpath(w, ROOT) for w in where))

# every public class used must be declared or imported from a package
known_sdk = set('''Widget State StatefulWidget StatelessWidget BuildContext Text Icon Icons Colors Color Scaffold AppBar
Padding Center Column Row Expanded SizedBox ListView ListTile Card Divider Future Stream List Map Set String int double bool
Object Duration DateTime Timer Completer File Directory Uri'''.split())

for f, src in texts.items():
    local = set(re.findall(r"^(?:abstract |sealed |final |base )*(?:class|enum|mixin|extension)\s+(\w+)", src, re.M))
    imported = set()
    for imp in imports_of[f]:
        if imp.startswith('package:') or imp.startswith('dart:'):
            continue
        target = os.path.normpath(os.path.join(os.path.dirname(f), imp))
        if os.path.exists(target):
            imported |= set(re.findall(r"^(?:abstract |sealed |final |base )*(?:class|enum|mixin|extension)\s+(\w+)",
                                       open(target).read(), re.M))
    # our own types start with a capital and appear in our own declarations somewhere
    ours = {n for n in declared if not n.startswith('_')}
    used = set(re.findall(r"\b([A-Z]\w+)\b", strip_code(src)))
    for name in sorted(used & ours):
        if name in local or name in imported:
            continue
        problems.append(f"{os.path.relpath(f, ROOT)}: uses {name} but does not import it")

# method existence for our own service classes
api = {}
for name, where in declared.items():
    for w in where:
        src = texts[w]
        m = re.search(r"class\s+%s\b.*?(?=\nclass |\Z)" % re.escape(name), src, re.S)
        if not m:
            continue
        body = m.group(0)
        members = set(re.findall(r"^\s{2}(?:static\s+|final\s+|late\s+|const\s+)*[\w<>,\s\?\[\]]+\s+(\w+)\s*[\(;=]", body, re.M))
        members |= set(re.findall(r"^\s{2}(?:Future<[^>]*>|void|bool|int|double|String|[A-Z]\w*)\??\s+(\w+)\s*\(", body, re.M))
        members |= set(re.findall(r"get\s+(\w+)", body))
        api[name] = members

print(f"checked {len(files)} files, {len(declared)} public types")
if problems:
    print(f"\n{len(problems)} problem(s):")
    for p in sorted(set(problems)):
        print('  -', p)
    sys.exit(1)
print("no structural problems found")

# ---- calls on our own services must exist ----
RECEIVERS = {
    's.cart': 'CartService', 's.sync': 'SyncRepository', 's.auth': 'AuthRepository',
    's.sales': 'SalesRepository', 's.catalog': 'CatalogRepository', 's.images': 'ProductImageCache',
    's.printer': 'BluetoothPrinter', 's.db': 'LocalDb', 's.api': 'ApiClient', 's.session': 'Session',
    's.catalog.dao': 'CatalogDao', 'widget.services.catalog.dao': 'CatalogDao',
    'widget.services.sales': 'SalesRepository',
}
missing = []
for f, src in texts.items():
    body = strip_code(src)
    for recv, cls in sorted(RECEIVERS.items(), key=lambda kv: -len(kv[0])):
        for m in re.finditer(re.escape(recv) + r"\.(\w+)", body):
            member = m.group(1)
            if recv == 's.catalog' and member == 'dao':
                continue
            known = set(api.get(cls, set()))
            # inherited from ChangeNotifier
            if re.search(r"class\s+%s\b[^{]*extends\s+ChangeNotifier" % re.escape(cls), '\n'.join(texts.values())):
                known |= {'addListener', 'removeListener', 'notifyListeners', 'dispose', 'hasListeners'}
            if member not in known:
                missing.append(f"{os.path.relpath(f, ROOT)}: {recv}.{member}() not found on {cls}")
if missing:
    print(f"\n{len(set(missing))} possible missing member(s):")
    for mm in sorted(set(missing)):
        print('  -', mm)
    sys.exit(1)
print("service calls all resolve")
