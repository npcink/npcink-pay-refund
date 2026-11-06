"""Each removed payment permission guard must make the real callback tests fail."""
from pathlib import Path
import shutil
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix="npcink-permission-mutation-") as directory:
    target = Path(directory) / "plugin"
    shutil.copytree(root, target, ignore=shutil.ignore_patterns(".git", "vendor", "build"))
    (target / "vendor").symlink_to(root / "vendor", target_is_directory=True)
    killed = 0
    guard = "Npcink_Pay_Refund_Admin_Authority::require_refund_ajax_permission();"
    for channel in ("wx", "zfb"):
        file = target / f"admin/pay/npcink-pay-refund-admin-{channel}.php"
        original = file.read_text()
        for index in range(original.count(guard)):
            parts = original.split(guard)
            mutated = guard.join(parts[:index + 1]) + "/* mutation: guard removed */" + guard.join(parts[index + 1:])
            file.write_text(mutated)
            result = subprocess.run(["php", "tests/run.php"], cwd=target, capture_output=True, text=True)
            if result.returncode == 0 or "FAIL: all five real payment AJAX entrypoints" not in result.stderr:
                raise SystemExit(f"Permission mutant {channel}/{index} survived or failed for an unrelated reason:\n{result.stdout}\n{result.stderr}")
            killed += 1
            print(f"PASS: removed {channel} guard {index + 1} detected")
        file.write_text(original)
    if killed != 5:
        raise SystemExit(f"Expected 5 permission mutants, found {killed}")
    print("5/5 permission mutants killed.")
