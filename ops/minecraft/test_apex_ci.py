import importlib.machinery
import importlib.util
import io
from pathlib import Path
import tarfile
import tempfile
import unittest
from unittest.mock import patch
import zipfile

loader = importlib.machinery.SourceFileLoader('apex_ci', str(Path(__file__).with_name('apex-ci')))
spec = importlib.util.spec_from_loader(loader.name, loader)
ci = importlib.util.module_from_spec(spec)
loader.exec_module(ci)


def artifact(name='ApexAuth.jar'):
    jar = io.BytesIO()
    with zipfile.ZipFile(jar, 'w') as z:
        z.writestr('plugin.yml', 'name: ApexAuth\nversion: 1\nmain: Example\n')
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz') as tar:
        member = tarfile.TarInfo(name)
        member.size = len(jar.getvalue())
        tar.addfile(member, io.BytesIO(jar.getvalue()))
    return output.getvalue()


class StageTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.sha = 'a' * 40

    def tearDown(self):
        self.temp.cleanup()

    def invoke(self, command, data=b''):
        stdin = type('Input', (), {'buffer': io.BytesIO(data)})()
        with patch.object(ci, 'ROOT', self.root), patch.object(ci.sys, 'argv', ['apex-ci', command]), patch.object(ci.sys, 'stdin', stdin), patch.object(ci, 'panel') as panel:
            ci.main()
            panel.assert_not_called()

    def test_stage_does_not_restart_and_is_repeatable(self):
        data = artifact()
        self.invoke('stage ' + self.sha, data)
        self.invoke('stage ' + self.sha, data)
        self.assertTrue((self.root/'releases'/self.sha/'ApexAuth.jar').is_file())

    def test_path_traversal_and_unknown_plugins_are_rejected(self):
        for name in ['../../evil.jar', '/tmp/evil.jar', 'Unknown.jar']:
            with self.assertRaises(ValueError):
                self.invoke('stage ' + self.sha, artifact(name))
        self.assertFalse((self.root/'releases'/self.sha).exists())

    def test_arbitrary_commands_and_bad_release_ids_are_rejected(self):
        for command in ['bash', 'stage ../../etc', 'deploy abc', 'status; id', 'deps extra']:
            with self.assertRaises(ValueError):
                self.invoke(command)

    def test_deployed_release_cannot_be_replaced(self):
        self.invoke('stage ' + self.sha, artifact())
        (self.root/'backups'/self.sha).mkdir(parents=True)
        with self.assertRaises(ValueError):
            self.invoke('stage ' + self.sha, artifact('ApexSkins.jar'))


if __name__ == '__main__':
    unittest.main()
