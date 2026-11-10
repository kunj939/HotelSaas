"""Exercise installer guards without a database or real configuration."""
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]


class InstallerGuards(unittest.TestCase):
    def render(self, config=None):
        with tempfile.TemporaryDirectory() as directory:
            folder = Path(directory)
            shutil.copy(ROOT / 'public/api/install_once.php', folder)
            if config is not None:
                (folder / 'config.local.php').write_text(config)
            result = subprocess.run(['php', str(folder / 'install_once.php')],
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(result.stderr, '')
            return result.stdout

    def test_missing_configuration(self):
        self.assertIn('First copy config.example.php', self.render())

    def test_removed_install_key(self):
        self.assertIn('Installer disabled', self.render('<?php return [];'))

    def test_short_install_key(self):
        self.assertIn('Installer disabled', self.render("<?php return ['install_key'=>'short'];"))

    def test_placeholder_install_key(self):
        config = "<?php return ['install_key'=>'CHANGE_TO_ANOTHER_LONG_RANDOM_STRING'];"
        self.assertIn('Installer disabled', self.render(config))


if __name__ == '__main__':
    unittest.main()
