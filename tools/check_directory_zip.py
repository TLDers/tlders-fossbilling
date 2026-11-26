#!/usr/bin/env python3
"""Pre-check a ZIP against the published rules of extensions.fossbilling.org.

This is MY re-implementation of the rules in FOSSBilling/api (libraries/extension-packaging) and the
manifest v1 schema (FOSSBilling/modules manifest.schema.json). It is not the official validator, so a pass
here is a good sign, not a guarantee. Usage: python3 tools/check_directory_zip.py <zip>
"""
import json, re, sys, zipfile

LIMITS = dict(source=5 * 1024 * 1024, expanded=16 * 1024 * 1024, entries=500, manifest=256 * 1024, path=1024, depth=32)
SEMVER = re.compile(r"^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$")
TYPES = {"module", "theme", "payment-gateway", "server-manager", "domain-registrar", "translation"}
RESERVED = re.compile(r"^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)", re.I)


def fail(msg):
    print("REJECTED:", msg)
    sys.exit(1)


def check_manifest(m):
    allowed = {"manifest_version", "type", "id", "name", "version", "description", "author", "license", "homepage_url", "requires"}
    extra = set(m) - allowed
    if extra:
        fail(f"manifest has unsupported keys: {sorted(extra)}")
    for key in ("manifest_version", "type", "id", "name", "version", "description", "author", "license", "requires"):
        if key not in m:
            fail(f"manifest is missing '{key}'")
    if m["manifest_version"] != 1 or isinstance(m["manifest_version"], bool):
        fail("manifest_version must be the integer 1")
    if m["type"] not in TYPES:
        fail(f"type must be one of {sorted(TYPES)} (got {m['type']!r})")
    if not re.fullmatch(r"[a-z0-9_-]{1,64}", m["id"]):
        fail("id must match ^[a-z0-9_-]+$ (max 64)")
    if not (1 <= len(m["name"]) <= 100):
        fail("name must be 1-100 characters")
    if not SEMVER.match(m["version"]) or len(m["version"]) > 100:
        fail("version must be semver")
    if not (1 <= len(m["description"]) <= 500):
        fail("description must be 1-500 characters")
    a = m["author"]
    if not isinstance(a, dict) or not a.get("name") or set(a) - {"name", "url"}:
        fail("author must be an object with 'name' and optional 'url'")
    if "url" in a and not re.match(r"^https?://", a["url"]):
        fail("author.url must be http(s)")
    if not re.fullmatch(r"[A-Za-z0-9.\-+_:() |]+", m["license"]) or len(m["license"]) > 1000:
        fail("license has unsupported characters")
    if "homepage_url" in m and not re.match(r"^https?://", m["homepage_url"]):
        fail("homepage_url must be http(s)")
    r = m["requires"]
    if not isinstance(r, dict) or set(r) - {"fossbilling", "php", "modules"}:
        fail("requires may only contain fossbilling, php and modules")
    if not r.get("fossbilling"):
        fail("requires.fossbilling is required")
    if not r.get("php"):
        fail("requires.php is required by the published schema")


def main(path):
    import os

    size = os.path.getsize(path)
    if size > LIMITS["source"]:
        fail(f"ZIP is {size} bytes, over the {LIMITS['source']} limit")
    with open(path, "rb") as fh:
        if fh.read(4) != b"PK\x03\x04":
            fail("expected a ZIP without a prepended payload")
    z = zipfile.ZipFile(path)
    infos = z.infolist()
    if len(infos) > LIMITS["entries"]:
        fail("too many entries")
    seen, expanded, files, manifest_bytes, icons = set(), 0, 0, None, []
    for i in infos:
        name = i.filename
        p = name.rstrip("/") if i.is_dir() else name
        parts = p.split("/")
        if len(p) > LIMITS["path"] or len(parts) > LIMITS["depth"]:
            fail(f"path too long or too deep: {name}")
        if re.search(r'[\\:<>"|?*]', p) or any(ord(c) < 32 or ord(c) == 127 for c in p) or any(x in ("", ".", "..") for x in parts):
            fail(f"unsafe path: {name}")
        if any(re.search(r"[. ]$", x) or RESERVED.match(x) for x in parts):
            fail(f"unsafe path segment: {name}")
        key = p.lower()
        if key in seen:
            fail(f"duplicate path: {name}")
        seen.add(key)
        if i.flag_bits & 0x1:
            fail(f"encrypted entry: {name}")
        if i.compress_type not in (0, 8):
            fail(f"unsupported compression in {name}")
        mode = (i.external_attr >> 16) & 0o170000
        if mode and mode != (0o040000 if i.is_dir() else 0o100000):
            fail(f"entry is not a regular file/directory (symlink?): {name}")
        expanded += i.file_size
        if expanded > LIMITS["expanded"]:
            fail("expanded size over limit")
        if not i.is_dir() and re.fullmatch(r"icon\.(svg|png|webp|avif)", p):
            icons.append(p)
        if p.lower() == "manifest.json" and p != "manifest.json":
            fail("manifest must be named manifest.json (lowercase)")
        if p == "manifest.json":
            if i.is_dir() or i.file_size > LIMITS["manifest"]:
                fail("expected a root manifest.json file up to 256 KiB")
            manifest_bytes = z.read(i)
        elif not i.is_dir():
            files += 1
    if manifest_bytes is None:
        fail("package must contain a root manifest.json (not inside a folder)")
    if not files:
        fail("package contains no source files")
    if len(icons) > 1:
        fail("include only one package icon")
    if manifest_bytes.startswith(b"\xef\xbb\xbf"):
        fail("manifest.json must be UTF-8 without a BOM")
    check_manifest(json.loads(manifest_bytes.decode("utf-8")))
    m = json.loads(manifest_bytes)
    print(f"PASSES my checks: {os.path.basename(path)}")
    print(f"  id={m['id']} type={m['type']} version={m['version']} requires={m['requires']} icon={icons[0] if icons else None} files={files}")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    main(sys.argv[1])
