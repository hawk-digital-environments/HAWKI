import type {ConcreteInstaller} from './types.ts';
import {promisify} from 'util';
import {exec, execSync} from 'child_process';
import {promises as fs} from 'fs';
import path from 'node:path';
import {confirm} from '@inquirer/prompts';
import chalk from 'chalk';
import {detectLinuxPackageManager, type LinuxPackageManager} from '../utils.ts';

const execAsync = promisify(exec);

export class LinuxWslInstaller implements ConcreteInstaller {
    async checkDependencies(): Promise<void> {
        const packageManager = await detectLinuxPackageManager();

        console.log(`
☝ ${chalk.bold('Prepare your trigger finger!')}

While we install the project, you will probably see multiple prompts asking for your passwort / authentication.
This is, ${chalk.italic('unfortunately')}, required to install the dependencies and to modify the hosts file.
`);

        await confirm({
            message: 'Okay, let\'s click it!',
            default: true
        });

        try {
            await execAsync('which wget');
        } catch {
            await installWget(packageManager);
        }

        try {
            await runWindowsCommand('gsudo -v');
        } catch {
            await installWithScoop('gsudo');
        }

        try {
            await runWindowsCommand('mkcert -version');
        } catch {
            try {
                await runWindowsCommand('git --version');
            } catch {
                console.log('To install mkcert, we need to install git first...');
                await installWithScoop('git');
            }

            await installWithScoop('mkcert', async () => {
                try {
                    await runWindowsCommand('scoop bucket add extras');
                } catch {
                    // Silence (probably already installed)...
                }
            });
        }
    }

    async registerLoopbackIp(ip: string): Promise<void> {
        console.log(`Registering loopback IP ${ip} on Windows host...`);

        // Check if IP exists on Windows
        const checkCommand = `Get-NetIPAddress -IPAddress "${ip}" -ErrorAction SilentlyContinue`;
        let exists = false;
        try {
            exists = !!(await runWindowsCommand(checkCommand));
        } catch {
            // Silence...
        }

        if (!exists) {
            // Add the IP to loopback adapter
            await runElevatedWindowsCommand(`New-NetIPAddress -InterfaceAlias "Loopback" -IPAddress "${ip}" -PrefixLength 32`);
        }
    }

    async registerDomainToIp(domain: string, ip: string): Promise<void> {
        console.log(`Mapping domain ${domain} to ${ip} in Windows hosts file...`);

        const windowsHostsPath = 'C:\\Windows\\System32\\drivers\\etc\\hosts';
        const entry = `${ip}\t${domain}`;

        // Check if entry exists
        const checkCommand = `Select-String -Path "${windowsHostsPath}" -Pattern "${ip}\\s+${domain}" -Quiet`;
        const exists = await runWindowsCommand(checkCommand);

        if (exists.toLowerCase() !== 'true') {
            await runElevatedWindowsCommand(`Add-Content -Path "${windowsHostsPath}" -Value "${entry}" -Force`);
        }
    }

    async buildCertificate(domain: string, storageDir: string): Promise<void> {
        console.log(`Building SSL certificate for ${domain} on Windows host and storing in WSL...`);

        // Create certificate directory on Windows
        const winCertDirCmd = `\$certPath = "\$env:USERPROFILE\\.local\\certs"; New-Item -Path \$certPath -ItemType Directory -Force | Out-Null; \$certPath`;
        const windowsPath = (await runWindowsCommand(winCertDirCmd)).trim();

        // Generate certificates on Windows
        await runElevatedWindowsCommand('mkcert -install');
        await runWindowsCommand(`cd "${windowsPath}"; mkcert "${domain}"`);

        // Create storage directory in WSL
        await fs.mkdir(storageDir, {recursive: true});

        // Read certificate content from Windows and write to WSL
        console.log('Transferring certificate from Windows to WSL...');
        const certContent = await runWindowsCommand(
            `[Convert]::ToBase64String([System.IO.File]::ReadAllBytes("${windowsPath}\\${domain}.pem"))`
        );
        const keyContent = await runWindowsCommand(
            `[Convert]::ToBase64String([System.IO.File]::ReadAllBytes("${windowsPath}\\${domain}-key.pem"))`
        );

        // Write files in WSL from base64 content
        await fs.writeFile(path.join(storageDir, `cert.pem`), Buffer.from(certContent, 'base64'));
        await fs.writeFile(path.join(storageDir, `key.pem`), Buffer.from(keyContent, 'base64'));

        console.log(`Windows certificates created at: ${windowsPath}\\${domain}.pem`);
        console.log(`Certificates transferred to WSL at: ${storageDir}/cert.pem`);
    }
}

async function installWithScoop(dependency: string, before?: () => Promise<void>): Promise<void> {
    try {
        await runWindowsCommand('scoop');
    } catch {
        console.log(`
Whoops, a dependency is missing a dependency 😅.

To install the project, we need: ${chalk.bold(`${dependency}`)} but it is not installed.

I can fix that using ${chalk.bold('scoop')} (https://scoop.sh), however it is not installed either - big sad.
So before we continue, I would like to install scoop for you?

Alternatively you can install the missing requirement manually.
`);
        if (await confirm({
            message: `Should I install scoop?`,
            default: true
        })) {
            console.log('🔨 Installing scoop...');
            await runWindowsCommand('iwr -useb https://get.scoop.sh | iex', true);
            await execAsync('/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"');
        } else {
            console.log(`😦 Please install either scoop, or ${dependency} manually!`);
            return;
        }
    }

    if (before) {
        await before();
    }

    console.log(`🔨 Installing dependency ${chalk.bold(dependency)}...`);
    await runWindowsCommand('scoop install ' + dependency, true);
}

async function runWindowsCommand(command: string, foreground?: boolean): Promise<string> {
    return new Promise((resolve, reject) => {
        try {
            const pathExtension = `$env:PATH += ";$env:USERPROFILE\\scoop\\shims";`;
            const extendedCommand = `${pathExtension} ${command}`;
            const escapedCommand = extendedCommand.replace(/"/g, '\\"').replace(/\$/g, '\\$');
            const wrappedCommand = `powershell.exe -Command "${escapedCommand}"`;
            if (foreground) {
                const result = execSync(
                    wrappedCommand,
                    foreground ? {stdio: 'inherit'} : undefined
                );
                resolve('');
            } else {
                execAsync(wrappedCommand)
                    .then(({stdout}) => resolve(stdout.trim()))
                    .catch(error => reject(new Error(`Windows command failed: ${error.message}`)));
            }
        } catch (error) {
            reject(new Error(`Windows command failed: ${error.message}`));
        }
    });
}

async function runElevatedWindowsCommand(command: string): Promise<string> {
    return runWindowsCommand(`gsudo { ${command.replace(/"/g, '\\"')} }`);
}

async function installWget(packageManager: LinuxPackageManager): Promise<void> {
    switch (packageManager) {
        case 'apt':
            await execAsync('sudo apt-get install -y wget');
            break;
        case 'yum':
            await execAsync('sudo yum install -y wget');
            break;
        case 'pacman':
            await execAsync('sudo pacman -S --noconfirm wget');
            break;
        case 'zypper':
            await execAsync('sudo zypper install -y wget');
            break;
        default:
            throw new Error('Unsupported package manager.');
    }
}
