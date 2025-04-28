import type {ConcreteInstaller} from './types.ts';
import {promisify} from 'util';
import {exec} from 'child_process';
import {promises as fs} from 'fs';
import {confirm} from '@inquirer/prompts';
import {addToHostsFile} from '../utils.ts';
import chalk from 'chalk';

const execAsync = promisify(exec);

export class DarwinInstaller implements ConcreteInstaller {
    requiresLoopbackMonitoring = true;
    
    async checkDependencies(): Promise<void> {
        try {
            await execAsync('which mkcert');
        } catch {
            await installWithBrew('mkcert');
            await installWithBrew('nss');
        }
    }

    async registerLoopbackIp(ip: string): Promise<void> {
        console.log(`Registering loopback IP ${ip} on macOS...`);

        // Check if IP already exists
        const {stdout} = await execAsync(`ifconfig lo0 | grep '${ip}'`).catch(() => ({stdout: ''}));

        if (!stdout.includes(ip)) {
            await execAsync(`sudo ifconfig lo0 alias ${ip} up`);
        }
    }

    async registerDomainToIp(domain: string, ip: string): Promise<void> {
        console.log(`Mapping domain ${domain} to ${ip} in macOS hosts file...`);

        const hostsPath = '/etc/hosts';
        const hostsContent = await fs.readFile(hostsPath, 'utf8');

        if (!hostsContent.includes(`${ip}\t${domain}`)) {
            const tmpFile = '/tmp/hosts.new';
            await fs.writeFile(tmpFile, addToHostsFile(hostsContent, domain, ip));
            await execAsync(`sudo mv ${tmpFile} ${hostsPath}`);
        }
    }

    async buildCertificate(domain: string, storageDir: string): Promise<void> {
        console.log(`Building SSL certificate for ${domain} on macOS...`);

        await execAsync('mkcert -install');
        await execAsync(`mkcert -cert-file "${storageDir}/cert.pem" -key-file "${storageDir}/key.pem" "${domain}"`);

        console.log(`Certificate created at: ${storageDir}/cert.pem`);
    }
}

async function installWithBrew(dependency: string, before?: () => Promise<void>): Promise<void> {
    try {
        await execAsync('which brew');
    } catch {
        console.log(`
Whoops, a dependency is missing a dependency 😅.

To install the project, we need: ${chalk.bold(`${dependency}`)} but it is not installed.

I can fix that using ${chalk.bold('brew')} (https://brew.sh), however it is not installed either - big sad.
So before we continue, I would like to install brew for you?

Alternatively you can install the missing requirement manually.
`);
        if (await confirm({
            message: `Should I install brew?`,
            default: true
        })) {
            console.log('🔨 Installing brew...');
            await execAsync('/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"');
        } else {
            console.log(`😦 Please install either brew, or ${dependency} manually!`);
            return;
        }
    }

    if (before) {
        await before();
    }

    console.log(`🔨 Installing dependency ${chalk.bold(dependency)}...`);
    await execAsync(`brew install ${dependency}`);
}
