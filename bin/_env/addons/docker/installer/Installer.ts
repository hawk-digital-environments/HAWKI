import type {Context} from '@/Context.js';
import {IpAddressStorage} from './IpAddressStorage.js';
import type {ConcreteInstaller} from './concrete/types.js';
import {LinuxInstaller} from './concrete/LinuxInstaller.js';
import {confirm} from '@inquirer/prompts';
import chalk from 'chalk';
import {DarwinInstaller} from './concrete/DarwinInstaller.js';
import {LinuxWslInstaller} from './concrete/LinuxWslInstaller.js';
import path from 'node:path';

export class Installer {
    private readonly _context: Context;
    private readonly _ip: IpAddressStorage;
    private readonly _installer: ConcreteInstaller;

    constructor(context: Context) {
        this._context = context;
        this._installer = context.platform.choose({
            linux: new LinuxInstaller(),
            linuxWsl: new LinuxWslInstaller(),
            darwin: new DarwinInstaller()
        });
        this._ip = new IpAddressStorage(path.join(this._context.paths.envHomeDir + 'next-ip.txt'));
    }

    public async install(): Promise<void> {
        console.log(`
|    o        /o          |         |    |
|---..,---.  / .,---.,---.|--- ,---.|    |    ,---.,---.
|   |||   | /  ||   |\`---.|    ,---||    |    |---'|
\`---'\`\`   '/   \`\`   '\`---'\`---'\`---^\`---'\`---'\`---'\`

Hello! You are about to "install" the project on your computer.
This is a one-time setup, so you can run this command once and forget about it.
You can always change the URL and IP address in the .env file later.

${chalk.bold('IMPORTANT: While installing, the script will ask for your password to modify the hosts file or to install the ssl certificates.')}

What the script will do:

- Check if you have the required dependencies installed - if not, it will install them for you if possible
- Register a loopback IP address for your project (this is a unique IP address that will be used for your project)
- Register a domain name for your project (this is a unique URL derived from the project name)
- Create a SSL certificate using the mkcert tool (this is a tool that creates SSL certificates for local development)
- Update the .env file with the new IP address and domain name
`);

        if (!(await confirm({
            message: 'Do you want to continue with the installation?',
            default: true
        }))) {
            console.log('Installation cancelled.');
            return;
        }

        const {events, paths, docker} = this._context;
        const installer = this._installer;

        await events.trigger('installer:before', {installer: installer});

        console.log('Stopping running docker containers...');
        await docker.down();

        await events.trigger('installer:dependencies:before', undefined);
        await installer.checkDependencies();

        const projectIp = this.getProjectIpAddress();
        await events.trigger('installer:loopbackIp:before', {ip: projectIp});
        await installer.registerLoopbackIp(projectIp);

        const projectHost = this.getProjectHost();
        await events.trigger('installer:domain:before', {domain: projectHost, ip: projectIp});
        await installer.registerDomainToIp(projectHost, projectIp);

        await events.trigger('installer:certificates:before', undefined);
        await installer.buildCertificate(projectHost, path.join(paths.projectDir, 'docker', 'certs'));

        await this.updateEnvFile(projectIp, projectHost);
        this._ip.persistNextIpAddress();

        await events.trigger('installer:after', undefined);

        console.log('Bringing your project up...');
        await docker.up();

        console.log(chalk.green(`
🥳 ${chalk.bold('Yay!')}

Installation complete!

Your project ip address is: ${chalk.bold(projectIp)}
Your project domain is: https://${chalk.bold(projectHost)}

Open the url in your browser or call ${chalk.yellow.italic('bin/env open')}.
`));
    }

    public async ensureLoopbackIp(): Promise<void> {
        if (this._installer.requiresLoopbackMonitoring) {
            const configuredIpAddress = this._context.docker.projectIp;
            if (configuredIpAddress !== '127.0.0.1') {
                await this._installer.registerLoopbackIp(configuredIpAddress);
            }
        }
    }

    private getProjectIpAddress(): string {
        const configuredIpAddress = this._context.docker.projectIp;
        if (configuredIpAddress !== '127.0.0.1') {
            return configuredIpAddress;
        }

        return this._ip.getNextIpAddress();
    }

    private getProjectHost(): string {
        const configuredHost = this._context.docker.projectHost;
        if (configuredHost !== 'localhost') {
            return configuredHost;
        }

        const domainSuffix = this._context.docker.projectHostSuffix;

        return this._context.docker.projectName + '.' + (domainSuffix).trim().replace(/^\./, '');
    }

    private async updateEnvFile(projectIp: string, projectHost: string): Promise<void> {
        const envFile = this._context.env;

        envFile
            .set('DOCKER_PROJECT_IP', projectIp)
            .set('DOCKER_PROJECT_HOST', projectHost)
            .set('DOCKER_PROJECT_PATH', '/')
            .set('DOCKER_PROJECT_PROTOCOL', 'https')

        await this._context.events.trigger('installer:envFile:filter', {envFile});

        envFile.write();
    }
}
