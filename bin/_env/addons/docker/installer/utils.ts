import {URL} from 'url';
import process from 'node:process';
import {exec} from 'child_process';
import {promisify} from 'util';

const execAsync = promisify(exec);

export type LinuxPackageManager = 'apt' | 'yum' | 'pacman' | 'zypper';

export async function detectLinuxPackageManager(): Promise<'apt' | 'yum' | 'pacman' | 'zypper'> {
    const {stdout} = await execAsync('which apt || which yum || which pacman || which zypper').catch(() => ({stdout: ''}));
    if (stdout.includes('apt')) {
        return 'apt';
    } else if (stdout.includes('yum')) {
        return 'yum';
    } else if (stdout.includes('pacman')) {
        return 'pacman';
    } else if (stdout.includes('zypper')) {
        return 'zypper';
    }

    throw new Error('No supported package manager found.');
}

export function getMkcertDownloadUrl(): URL {
    const platform = (process.platform === 'win32') ? 'windows' : process.platform;
    const arch = process.arch === 'x64' ? 'amd64' : process.arch;
    return new URL(`https://dl.filippo.io/mkcert/latest?for=${platform}/${arch}`);
}

export async function downloadFile(url: string | URL, location: string): Promise<void> {
    // Download the file using curl or if curl is not available, use wget
    const commands = [
        `curl -L -o ${location} ${url}`,
        `wget -O ${location} ${url}`
    ];

    for (const command of commands) {
        try {
            await execAsync(command);
            return;
        } catch (error) {
        }
    }

    throw new Error(`Failed to download file from ${url}. Please install curl or wget.`);
}

export function addToHostsFile(content: string, domain: string, ip: string): string {
    const entry = `${ip}\t${domain}`;
    if (content.includes(entry)) {
        return content;
    }

    // Remove any existing entry for the domain
    content = content.split('\n').filter(line => !line.includes(domain)).join('\n');

    return content.trim() + '\n' + entry;
}
