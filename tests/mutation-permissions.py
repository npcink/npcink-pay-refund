"""Verify that each AJAX permission guard has a failing regression test."""
from pathlib import Path
import json
import re
import shutil
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
fixtures = subprocess.check_output(
    ["php", "-r", 'require "tests/permission-cases.php"; echo json_encode(npcink_permission_cases());'],
    cwd=root, text=True,
)
cases = json.loads(fixtures)
gate = "Npcink_Pay_Refund_Admin_Authority::require_refund_ajax_permission();"
nonce = "if (!check_ajax_referer('npcink_pay_refund_action', 'nonce', false))"
admin = "if (!current_user_can('manage_options'))"
refund = "if (!self::current_user_can_refund())"
files = {
    "Zfb": "admin/pay/npcink-pay-refund-admin-zfb.php",
    "Wx": "admin/pay/npcink-pay-refund-admin-wx.php",
    "Query": "admin/partials/npcink-pay-refund-admin-query.php",
    "Config": "admin/partials/npcink-pay-refund-admin-config.php",
    "Authority": "admin/partials/npcink-pay-refund-admin-authority.php",
}
mutants = []
for group, method, *_ in cases:
    guards = ((nonce, "if (false)"), (admin, "if (false)")) if group == "Config" else ((gate, ""),)
    mutants.extend((group, method, old, new, method) for old, new in guards)
mutants.extend(("Authority", "require_refund_ajax_permission", old, "if (false)", cases[0][1])
               for old in (nonce, refund))

with tempfile.TemporaryDirectory(prefix="npcink-permission-mutation-") as directory:
    target = Path(directory) / "plugin"
    shutil.copytree(root, target, ignore=shutil.ignore_patterns(".git", "vendor", "build"))
    (target / "vendor").symlink_to(root / "vendor", target_is_directory=True)
    for group, method, old, new, expected in mutants:
        file = target / files[group]
        original = file.read_text()
        signature = re.search(rf"\bfunction {re.escape(method)}\([^\n]*\)\s*\{{", original)
        if not signature:
            raise SystemExit(f"Missing method: {group}::{method}")
        start = signature.end()
        next_method = re.search(r"\bfunction [a-zA-Z_][a-zA-Z_0-9]*\(", original[start:])
        end = start + next_method.start() if next_method else len(original)
        body = original[start:end]
        if body.count(old) != 1:
            raise SystemExit(f"Expected one guard in {group}::{method}: {old}")
        try:
            file.write_text(original[:start] + body.replace(old, new) + original[end:])
            result = subprocess.run(["php", "tests/run.php"], cwd=target, capture_output=True, text=True)
            if result.returncode != 1 or f"FAIL: AJAX permission {expected}:" not in result.stderr:
                raise SystemExit(f"Undetected guard change {group}::{method} ({old}):\n{result.stdout}\n{result.stderr}")
            print(f"PASS: guard change detected in {group}::{method}: {old}")
        finally:
            file.write_text(original)
    print(f"{len(mutants)}/{len(mutants)} permission mutants killed.")
