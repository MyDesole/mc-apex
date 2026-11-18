"""File-manager regression checks in an isolated temporary filesystem."""
from pathlib import Path
import os, subprocess, tempfile
script=Path(__file__).resolve().parents[2]/'backend/deploy/game-server/apex-files.sh'
with tempfile.TemporaryDirectory(prefix='apex-panel-auth-') as temp:
    base=Path(temp);root=base/'server';(root/'plugins').mkdir(parents=True)
    auth=root/'plugins/ApexAuth.jar';auth.write_bytes(b'original-auth')
    env=dict(os.environ,APEX_DIR=str(root),APEX_TRASH=str(base/'trash'),APEX_PB=str(base/'backup'))
    for args in [('trash','plugins/ApexAuth.jar'),('trash','plugins'),('mv','plugins/ApexAuth.jar','disabled.jar'),('mv','plugins','disabled'),('mv','other.jar','plugins/ApexAuth.jar'),('put-new','plugins/ApexAuth.jar')]:
        result=subprocess.run(['bash',str(script),*args],env=env,input=b'replacement',capture_output=True)
        assert result.returncode==1 and result.stdout.strip()==b'protected-auth',(args,result.stdout)
        assert auth.read_bytes()==b'original-auth'
    ordinary=root/'plugins/optional.jar';ordinary.write_bytes(b'optional')
    result=subprocess.run(['bash',str(script),'trash','plugins/optional.jar'],env=env,capture_output=True)
    assert result.returncode==0 and not ordinary.exists(),result.stdout
    assert list((base/'trash').rglob('optional.jar'))
print('7 panel checks passed; protected authentication preserved, ordinary trash works')
