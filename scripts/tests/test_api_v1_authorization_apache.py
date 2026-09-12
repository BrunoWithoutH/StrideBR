#!/usr/bin/env python3
"""Regression: Apache must expose Authorization to the API v1 PHP helper."""

from __future__ import annotations

import glob
import json
import shutil
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
APACHE = shutil.which("apache2") or shutil.which("httpd")

if not APACHE:
    print("○ API v1 Authorization/Apache: Apache indisponível, teste ignorado")
    raise SystemExit(0)

module_dirs = [
    Path("/usr/lib/apache2/modules"),
    Path("/usr/lib64/httpd/modules"),
    Path("/usr/lib/httpd/modules"),
]

def first_module(pattern: str) -> Path | None:
    for directory in module_dirs:
        matches = sorted(Path(p) for p in glob.glob(str(directory / pattern)))
        if matches:
            return matches[0]
    return None

php_module = first_module("libphp*.so")
mpm_module = first_module("mod_mpm_prefork.so")
authz_module = first_module("mod_authz_core.so")
setenvif_module = first_module("mod_setenvif.so")
rewrite_module = first_module("mod_rewrite.so")
mime_module = first_module("mod_mime.so")

required = [php_module, mpm_module, authz_module, setenvif_module, rewrite_module, mime_module]
if any(module is None for module in required):
    print("○ API v1 Authorization/Apache: módulos Apache/mod_php indisponíveis, teste ignorado")
    raise SystemExit(0)

# Pick an ephemeral-ish high port. The test is short-lived and bound to loopback only.
port = 18191

with tempfile.TemporaryDirectory(prefix="stridebr-api-auth-") as td:
    root = Path(td)
    root.chmod(0o755)
    (root / ".htaccess").write_text((ROOT / "public/api/v1/.htaccess").read_text())

    helper_path = (ROOT / "src/function/api_v1.php").resolve()
    helper_literal = str(helper_path).replace("\\", "\\\\").replace("'", "\\'")
    (root / "index.php").write_text(
        "<?php\n"
        f"require_once '{helper_literal}';\n"
        "$token = stridebr_api_bearer_token();\n"
        "http_response_code(401);\n"
        "header('Content-Type: application/json');\n"
        "echo json_encode(['error' => ['code' => $token === null ? 'authentication_required' : 'token_expired']]);\n"
    )

    conf = root / "apache.conf"
    conf.write_text(
        f'''ServerRoot "{root}"\n'''
        f'''PidFile "{root / 'apache.pid'}"\n'''
        f'''Listen 127.0.0.1:{port}\n'''
        f'''LoadModule mpm_prefork_module {mpm_module}\n'''
        f'''LoadModule authz_core_module {authz_module}\n'''
        f'''LoadModule setenvif_module {setenvif_module}\n'''
        f'''LoadModule rewrite_module {rewrite_module}\n'''
        f'''LoadModule mime_module {mime_module}\n'''
        f'''LoadModule php_module {php_module}\n'''
        '''TypesConfig /etc/mime.types\n'''
        '''ServerName localhost\n'''
        f'''ErrorLog "{root / 'error.log'}"\n'''
        f'''DocumentRoot "{root}"\n'''
        '''<FilesMatch ".+\\.ph(?:ar|p|tml)$">\n'''
        '''    SetHandler application/x-httpd-php\n'''
        '''</FilesMatch>\n'''
        f'''<Directory "{root}">\n'''
        '''    AllowOverride All\n'''
        '''    Require all granted\n'''
        '''</Directory>\n'''
    )

    config_test = subprocess.run(
        [APACHE, "-t", "-f", str(conf)],
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    if config_test.returncode != 0:
        raise RuntimeError(f"Apache fixture inválida: {config_test.stderr.strip()}")

    proc = subprocess.Popen(
        [APACHE, "-X", "-f", str(conf)],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    try:
        time.sleep(0.4)
        fake_token = "A" * 43  # valid base64url shape, intentionally not a real session
        request = urllib.request.Request(
            f"http://127.0.0.1:{port}/me",
            headers={"Authorization": f"Bearer {fake_token}"},
        )
        try:
            urllib.request.urlopen(request, timeout=3)
            raise AssertionError("Fixture deveria responder 401 para token inexistente")
        except urllib.error.HTTPError as response:
            payload = json.loads(response.read().decode("utf-8"))
            code = payload.get("error", {}).get("code")
            if response.code != 401:
                raise AssertionError(f"Status inesperado: {response.code}")
            if code != "token_expired":
                raise AssertionError(
                    "Authorization não chegou ao helper PHP; "
                    f"esperado token_expired, recebido {code!r}"
                )
    finally:
        proc.terminate()
        try:
            proc.wait(timeout=3)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=3)

print("✓ API v1 Authorization/Apache: Bearer header disponível ao helper PHP")
