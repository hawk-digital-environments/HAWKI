import type {ConcreteInstaller} from './types.ts';
import {promises as fs} from 'fs';
import {promisify} from 'util';
import {exec} from 'child_process';
import {
    addToHostsFile,
    detectLinuxPackageManager,
    downloadFile,
    getMkcertDownloadUrl,
    type LinuxPackageManager
} from '../utils.ts';

const execAsync = promisify(exec);

export class LinuxInstaller implements ConcreteInstaller {
    async checkDependencies(): Promise<void> {
        console.log('Checking Linux dependencies...');
        const packageManager = await detectLinuxPackageManager();

        try {
            await execAsync('which ip');
        } catch {
            console.log('Installing ip dependency...');
            await installIpDependency(packageManager);
        }

        try {
            await execAsync('which mkcert');
        } catch {
            console.log('Installing mkcert...');
            await installMkcertDependencies(packageManager);
            await downloadFile(getMkcertDownloadUrl(), '/tmp/mkcert');
            await execAsync(`
                chmod +x /tmp/mkcert &&
                sudo mv /tmp/mkcert /usr/local/bin/
            `);
        }
    }

    async registerLoopbackIp(ip: string): Promise<void> {
        console.log(`Registering loopback IP ${ip} on Linux...`);

        // Check if IP already exists
        const {stdout} = await execAsync(`ip addr show dev lo | grep '${ip}'`).catch(() => ({stdout: ''}));

        if (!stdout.includes(ip)) {
            await execAsync(`sudo ip addr add ${ip}/32 dev lo`);
        }
    }

    async registerDomainToIp(domain: string, ip: string): Promise<void> {
        console.log(`Mapping domain ${domain} to ${ip} in Linux hosts file...`);

        const hostsPath = '/etc/hosts';
        let hostsContent = await fs.readFile(hostsPath, 'utf8');

        if (!hostsContent.includes(`${ip}\t${domain}`)) {
            const tmpFile = '/tmp/hosts.new';
            await fs.writeFile(tmpFile, addToHostsFile(hostsContent, domain, ip));
            await execAsync(`sudo mv ${tmpFile} ${hostsPath}`);
        }
    }

    async buildCertificate(domain: string, storageDir: string): Promise<void> {
        console.log(`Building SSL certificate for ${domain} on Linux...`);

        await execAsync('mkcert -install');
        await execAsync(`mkcert -cert-file "${storageDir}/cert.pem" -key-file "${storageDir}/key.pem" "${domain}"`);

        console.log(`Certificate created at: ${storageDir}/cert.pem`);
    }
}

async function installIpDependency(packageManager: LinuxPackageManager): Promise<void> {
    switch (packageManager) {
        case 'apt':
            await execAsync('sudo apt-get install -y iproute2');
            break;
        case 'yum':
            await execAsync('sudo yum install -y iproute');
            break;
        case 'pacman':
            await execAsync('sudo pacman -S --noconfirm iproute2');
            break;
        case 'zypper':
            await execAsync('sudo zypper install -y iproute2');
            break;
        default:
            throw new Error('Unsupported package manager.');
    }
}

async function installMkcertDependencies(packageManager: LinuxPackageManager): Promise<void> {
    switch (packageManager) {
        case 'apt':
            await execAsync('sudo apt-get update && sudo apt-get install -y libnss3-tools');
            break;
        case 'yum':
            await execAsync('sudo yum install -y nss-tools');
            break;
        case 'pacman':
            await execAsync('sudo pacman -S --noconfirm nss');
            break;
        case 'zypper':
            await execAsync('sudo zypper install -y mozilla-nss-tools');
            break;
        default:
            throw new Error('Unsupported package manager.');
    }
}
