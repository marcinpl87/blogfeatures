#!/usr/bin/env python3
"""Upload every file in plugins/ to the FTP server described in .env."""

import os
import sys
from ftplib import FTP, FTP_TLS, error_perm
from pathlib import Path

ROOT = Path(__file__).resolve().parent
PLUGINS = ROOT / "plugins"
ENV_PATH = ROOT / ".env"


def load_env(path):
    if not path.is_file():
        sys.exit(f"Missing {path.name}. See README.md for the values it needs.")
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip("'\""))


def require(name):
    value = os.environ.get(name, "").strip()
    if not value:
        sys.exit(f"{name} is missing from .env. See README.md.")
    return value


def use_tls():
    return os.environ.get("FTP_TLS", "0").strip().lower() in {"1", "true", "yes", "on"}


def open_remote_dir(ftp, remote_dir):
    try:
        ftp.cwd(remote_dir)
    except error_perm as exc:
        sys.exit(f"Could not open FTP_DIR {remote_dir}: {exc}")


def upload_tree(ftp, local_root):
    files = sorted(path for path in local_root.rglob("*") if path.is_file())
    if not files:
        sys.exit(f"No files to upload in {local_root}.")
    base = ftp.pwd()
    for path in files:
        relative = path.relative_to(local_root)
        ftp.cwd(base)
        if relative.parent != Path("."):
            for part in relative.parent.parts:
                try:
                    ftp.cwd(part)
                except error_perm:
                    ftp.mkd(part)
                    ftp.cwd(part)
        with path.open("rb") as handle:
            ftp.storbinary(f"STOR {path.name}", handle)
        print("uploaded", relative.as_posix())


def main():
    load_env(ENV_PATH)
    host = require("FTP_HOST")
    user = require("FTP_USER")
    password = require("FTP_PASS")
    remote_dir = os.environ.get("FTP_DIR", "/").strip() or "/"

    if not PLUGINS.is_dir():
        sys.exit("plugins/ directory is missing.")

    ftp = FTP_TLS(host) if use_tls() else FTP(host)
    ftp.login(user, password)
    if use_tls():
        ftp.prot_p()
    open_remote_dir(ftp, remote_dir)
    upload_tree(ftp, PLUGINS)
    ftp.quit()


if __name__ == "__main__":
    main()
