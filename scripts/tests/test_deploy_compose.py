"""Render only: no containers, production credentials or real .env changes."""
import json, os, subprocess, tempfile
from pathlib import Path
ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='stridebr-compose-test-') as tmp:
    directory = Path(tmp)
    compose = directory / 'compose.dokploy.yaml'
    compose.write_text((ROOT / 'compose.dokploy.yaml').read_text())
    values = {
        'STRIDEBR_APP_ENV': 'staging',
        'STRIDEBR_APP_URL': 'https://staging.example.invalid',
        'STRIDEBR_DB_HOST': 'db-fixture',
        'STRIDEBR_DB_NAME': 'fixture',
        'STRIDEBR_DB_USER': 'fixture',
        'STRIDEBR_DB_PASSWORD': 'fixture-only-not-a-real-password',
        'STRIDEBR_MAIL_FROM': 'noreply@example.invalid',
        'STRIDEBR_SUPPORT_EMAIL': 'support@example.invalid',
    }
    dotenv = directory / '.env'
    dotenv.write_text(''.join(f'{key}={value}\n' for key,value in values.items()))
    env = {key:value for key,value in os.environ.items() if not key.startswith(('STRIDEBR_', 'COMPOSE_'))}
    for manual in [False, True]:
        args = ['docker', 'compose', '-f', str(compose)]
        if manual:
            alternative = directory / 'private.env'
            dotenv.rename(alternative)
            env['STRIDEBR_DEPLOY_ENV_FILE'] = str(alternative)
            args += ['--env-file', str(alternative)]
        result = subprocess.run(args + ['config', '--format', 'json'], env=env, cwd=directory, capture_output=True, check=True)
        config = json.loads(result.stdout)
        for name in ['app', 'migrate']:
            service = config['services'][name]
            for key,value in values.items():
                assert service['environment'][key] == value, (name,key)
            assert not service.get('ports')
        assert all(volume['type'] != 'bind' for volume in config['services']['app']['volumes'])
print('PASS Compose default .env and optional manual env: configuration reaches both services, no host ports or source bind mounts')
