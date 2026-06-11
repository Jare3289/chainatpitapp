import os
import zipfile
import re

SRC = r"D:\cnpapp"
DST = r"D:\cnpapp\cnp_deploy_20260611h.zip"

EXCLUDE_DIRS = {".git", "uploads"}  # root-level or any level
EXCLUDE_SUBPATH = ["public\\uploads", "public/uploads"]

def should_skip(rel_path):
    parts = rel_path.replace("\\", "/").split("/")
    # Skip .git anywhere
    if ".git" in parts:
        return True
    # Skip public/uploads
    norm = rel_path.replace("\\", "/")
    if norm.startswith("public/uploads"):
        return True
    # Skip root-level uploads dir
    if parts[0] == "uploads":
        return True
    # Skip old zip files at root
    if len(parts) == 1 and parts[0].endswith(".zip"):
        return True
    # Skip SQL dump files at root
    if len(parts) == 1 and re.match(r"admin_cnpapp.*\.sql", parts[0]):
        return True
    # Skip .env (server has its own credentials)
    if parts[-1] == ".env":
        return True
    # Skip .bak files
    if parts[-1].endswith(".bak"):
        return True
    # Skip brain/ folder (Claude session data)
    if parts[0] == "brain":
        return True
    return False

count = 0
skipped = 0

if os.path.exists(DST):
    os.remove(DST)
    print(f"Removed existing {DST}")

with zipfile.ZipFile(DST, "w", zipfile.ZIP_DEFLATED, allowZip64=True) as zf:
    for root, dirs, files in os.walk(SRC):
        rel_root = os.path.relpath(root, SRC)
        if rel_root == ".":
            rel_root = ""

        # Prune excluded dirs in-place
        dirs[:] = [d for d in dirs if not should_skip(
            (rel_root + "/" + d).lstrip("/").replace("\\", "/")
        )]

        for fname in files:
            abs_path = os.path.join(root, fname)
            rel_path = os.path.join(rel_root, fname) if rel_root else fname
            rel_fwd = rel_path.replace("\\", "/")

            if should_skip(rel_fwd):
                skipped += 1
                continue

            zf.write(abs_path, rel_fwd)
            count += 1

size_mb = os.path.getsize(DST) / 1024 / 1024
print(f"Done: {count} files added, {skipped} skipped")
print(f"Output: {DST} ({size_mb:.1f} MB)")
