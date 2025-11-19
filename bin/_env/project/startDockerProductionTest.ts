import type {Context} from '@/Context.js';
import path from 'path';
import fs from 'fs';
import {executeCommand} from '@/executeCommand.js';

export async function startDockerProductionTest(context: Context) {
    const testDirectory = path.join(context.paths.projectDir, '_docker_production_test');
    if (fs.existsSync(testDirectory)) {
        console.log(`Shutting down existing test environment in ${testDirectory}...`);
        await context.docker.executeComposeCommand(['down', '--volumes'], {cwd: testDirectory, foreground: true});
        try {
            fs.rmSync(testDirectory, {recursive: true, force: true});
        } catch (e) {
            // Failed to remove directory, probably because of missing permissions, try as root
            if (context.platform.isLinux || context.platform.isDarwin) {
                console.log('Failed to remove test directory, trying with sudo...');
                await executeCommand('sudo', ['rm', '-rf', testDirectory], {foreground: true});
            } else {
                throw new Error(`Failed to remove test directory ${testDirectory}: ${e}`);
            }
        }
    }

    console.log(`Setting up test environment in ${testDirectory}...`);
    fs.mkdirSync(testDirectory);
    fs.cpSync(path.join(context.paths.projectDir, '_docker_production'), testDirectory, {recursive: true});

    await createDummyCertificates(testDirectory);

    // Add additional config to .env file
    let env = `
LDAP_HOST="ldaps://ldap.forumsys.com"
LDAP_BIND_DN="cn=read-only-admin,dc=example,dc=com"
LDAP_BIND_PW="password"
LDAP_BASE_DN="dc=example,dc=com"
LDAP_ATTR_EMPLOYEETYPE="cn"
LDAP_ATTR_NAME="cn,sn"
LDAP_FILTER="(|(uid=username))"
`;

    // If in the project directory there is a .env.private file, append its contents to the test .env
    const privateEnvPath = path.join(context.paths.projectDir, '.env.private');
    if (fs.existsSync(privateEnvPath)) {
        const privateEnv = fs.readFileSync(privateEnvPath, 'utf-8');
        env += `\n# Contents from .env.private\n${privateEnv}\n`;
    }

    const envPath = path.join(testDirectory, '.env');
    fs.appendFileSync(envPath, env);

    // Check if there are orphan volumes from previous test runs and remove them
    const {stdout: volumes} = await context.docker.executeComposeCommand(['volumes', '--format', '{{.Name}}'], {cwd: testDirectory});
    const volumeList = volumes.split('\n').map(v => v.trim()).filter(v => v.length > 0);
    for (const volume of volumeList) {
        if (volume.startsWith('docker_production_test_')) {
            console.log(`Removing orphan volume ${volume}...`);
            await context.docker.executeDockerCommand(['volume', 'rm', volume], {cwd: testDirectory});
        }
    }

    // Rewrite deploy.sh to NOT start in detached mode
    const dockerCommand = 'docker compose up --pull always -d';
    let deployScript = fs.readFileSync(path.join(testDirectory, 'deploy.sh'), 'utf-8');
    if (!deployScript.includes(dockerCommand)) {
        throw new Error('Unexpected deploy.sh content, cannot rewrite for test mode.');
    }
    deployScript = deployScript.replace(dockerCommand, 'docker compose up --pull always');
    fs.writeFileSync(path.join(testDirectory, 'deploy.sh'), deployScript, 'utf-8');

    await executeCommand('chmod', ['+x', './deploy.sh'], {cwd: testDirectory, foreground: true});

    console.log('Starting Docker containers for production test...');
    await executeCommand('./deploy.sh', [], {cwd: testDirectory, interactive: true});
}

async function createDummyCertificates(testDirectory: string) {
    const certsDir = path.join(testDirectory, 'certs');
    fs.mkdirSync(certsDir, {recursive: true});

    const hasOpenssl = await executeCommand('which', ['openssl']).then(() => true).catch(() => false);
    if (!hasOpenssl) {
        throw new Error('OpenSSL is required to create dummy certificates for the production test.');
    }

    const certPath = path.join(certsDir, 'cert.pem');
    const keyPath = path.join(certsDir, 'key.pem');
    await executeCommand('openssl', ['req', '-x509', '-nodes', '-days', '365', '-newkey', 'rsa:2048',
        '-keyout', keyPath,
        '-out', certPath,
        '-subj', '/CN=localhost'], {cwd: testDirectory, foreground: true});

    console.log('Dummy certificates created for production test.');
}
