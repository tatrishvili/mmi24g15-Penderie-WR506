"""Regression checks for recovery decisions without touching real containers."""

import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


class RecoveryTests(unittest.TestCase):
    def run_scenario(self, scenario):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "docker").mkdir()
            script = root / "docker/recover-wsl-mounts.sh"
            shutil.copyfile(Path(__file__).with_name(script.name), script)
            for file in ("backend/public/index.php", "backend/vendor/autoload.php", "frontend/package.json"):
                path = root / file
                path.parent.mkdir(parents=True, exist_ok=True)
                path.touch()
            binary = root / "bin"
            binary.mkdir()
            fake_docker = binary / "docker"
            fake_docker.write_text("""#!/bin/bash
case "$*" in
  info) test "$SCENARIO" != daemon_off ;;
  'inspect --format {{.State.Running}} penderie-api')
    if test "$SCENARIO" = stopped; then echo false; else echo true; fi ;;
  'inspect --format {{.State.Running}} penderie-db') echo true ;;
  'inspect --format {{.State.Error}} penderie-nginx')
    if test "$SCENARIO" = nginx_mount; then echo 'error mounting: not a directory'; fi ;;
  'compose exec -T api '*)
    if test "$SCENARIO" = api_missing; then exit 42; fi ;;
  'compose exec -T frontend '*)
    if test "$SCENARIO" = frontend_missing; then exit 42; fi
    if test "$SCENARIO" = frontend_stopped; then exit 1; fi ;;
  *) exit 99 ;;
esac
""")
            fake_docker.chmod(0o755)
            fake_make = binary / "make"
            fake_make.write_text('#!/bin/bash\nprintf "%s\\n" "$*" > "$RECOVERY_RESULT"\n')
            fake_make.chmod(0o755)
            result = root / "result"
            env = {**os.environ, "PATH": str(binary) + os.pathsep + os.environ["PATH"],
                   "SCENARIO": scenario, "RECOVERY_RESULT": str(result), "XDG_RUNTIME_DIR": str(root)}
            subprocess.run(["bash", str(script)], env=env, check=True, capture_output=True)
            return result.read_text().strip() if result.exists() else None

    def test_healthy_stack_is_untouched(self):
        self.assertIsNone(self.run_scenario("healthy"))

    def test_stopped_stack_is_untouched(self):
        self.assertIsNone(self.run_scenario("stopped"))

    def test_stopped_frontend_is_untouched(self):
        self.assertIsNone(self.run_scenario("frontend_stopped"))

    def test_unavailable_daemon_is_untouched(self):
        self.assertIsNone(self.run_scenario("daemon_off"))

    def test_empty_backend_mount_is_repaired(self):
        self.assertEqual("restart", self.run_scenario("api_missing"))

    def test_empty_frontend_mount_is_repaired(self):
        self.assertEqual("restart", self.run_scenario("frontend_missing"))

    def test_failed_nginx_config_mount_is_repaired(self):
        self.assertEqual("restart", self.run_scenario("nginx_mount"))


if __name__ == "__main__":
    unittest.main()
