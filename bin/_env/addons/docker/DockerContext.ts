import type {Context} from '@/Context.ts';
import {promisify} from 'util';
import {exec, execSync} from 'node:child_process';
import {confirm, select} from '@inquirer/prompts';
import process from 'node:process';
import {
    executeCommand,
    type ExecuteCommandOptions,
    type ExecuteCommandResult,
    type InteractiveCommandResult,
    type InteractiveExecuteCommandOptions,
    type NonInteractiveCommandResult,
    type NonInteractiveExecuteCommandOptions
} from '@/executeCommand.ts';
import chalk from 'chalk';

const execAsync = promisify(exec);

interface UpOptions {
    // Basically the opposite of -d, if true it will follow the output, by default it will run in detached mode
    follow?: boolean;
    // Additional arguments to pass to docker-compose
    args?: string[];
}

export class DockerContext {
    private _context: Context;
    private _dockerExecutable: string | null = null;
    private _dockerComposeExecutable: string | null = null;
    private _dockerRuntimeType: ('podman' | 'docker') | null = null;
    private _containerIdCache: Map<string, string> = new Map();

    constructor(context: Context) {
        this._context = context;
    }

    public get isInstalled(): boolean {
        return this._context.env.getGlobal('DOCKER_PROJECT_INSTALLED') === 'true';
    }

    public get defaultServiceName(): string {
        return this._context.env.getGlobal('SERVICE_NAME', 'app');
    }

    public get defaultUid(): string {
        return this._context.env.getGlobal('ENV_UID', '1000');
    }

    public get defaultGid(): string {
        return this._context.env.getGlobal('ENV_GID', '1000');
    }

    public get projectDomainSuffix(): string {
        return this._context.env.getGlobal('DOCKER_PROJECT_DOMAIN_SUFFIX', '.dev.local');
    }

    public get projectDomain(): string {
        return this._context.env.getGlobal('DOCKER_PROJECT_DOMAIN', 'localhost');
    }

    public get projectIp(): string {
        return this._context.env.getGlobal('DOCKER_PROJECT_IP', '127.0.0.1');
    }

    public get projectPort(): string {
        return this._context.env.getGlobal('DOCKER_PROJECT_PORT', '80');
    }

    public get projectHost(): string {
        const expectedPort = this.projectProtocol === 'http' ? '80' : '443';
        const port = this.projectPort === expectedPort ? '' : `:${this.projectPort}`;
        return `${this.projectProtocol}://${this.projectDomain}${port}`;
    }

    public get projectName(): string {
        return this._context.env.getGlobalRequired('PROJECT_NAME');
    }

    public get projectProtocol(): string {
        return this._context.env.getGlobal('DOCKER_PROJECT_PROTOCOL', 'http');
    }

    public get shellsToUse(): string[] {
        return this._context.env.getGlobal('DOCKER_SHELLS_TO_USE', 'bash,sh,zsh,dash,ksh')
            .split(',')
            .map((shell: string) => shell.trim());
    }


    public async executeDockerCommand(command: Array<string>, opt: NonInteractiveExecuteCommandOptions): Promise<NonInteractiveCommandResult>;
    public async executeDockerCommand(command: Array<string>, opt: InteractiveExecuteCommandOptions): Promise<InteractiveCommandResult>;
    public async executeDockerCommand(command: Array<string>, opt?: ExecuteCommandOptions): Promise<NonInteractiveCommandResult>;

    /**
     * Executes a docker command
     * @param command the command to execute split in an array of strings. (e.g. ['ps', '-a'])
     * @param opt
     */
    public async executeDockerCommand(command: Array<string>, opt?: ExecuteCommandOptions) {
        const env = await this.getEnvironmentVariables();
        return executeCommand(await this.getDockerExecutable(), command, {...opt, env: {...env, ...(opt?.env || {})}});
    }

    public async executeComposeCommand(command: Array<string>, opt: NonInteractiveExecuteCommandOptions): Promise<NonInteractiveCommandResult>;
    public async executeComposeCommand(command: Array<string>, opt: InteractiveExecuteCommandOptions): Promise<InteractiveCommandResult>;
    public async executeComposeCommand(command: Array<string>, opt?: ExecuteCommandOptions): Promise<NonInteractiveCommandResult>;

    /**
     * Executes a docker-compose command
     * @param command the command to execute split in an array of strings. (e.g. ['up', '-d'])
     * @param opt
     */
    public async executeComposeCommand(command: Array<string>, opt?: ExecuteCommandOptions) {
        let composeCommand = await this.getComposeExecutable();

        // Special case for "docker compose" (v2) command, because it is not a real executable
        if (composeCommand.endsWith(' compose')) {
            composeCommand = composeCommand.substring(0, composeCommand.length - 8);
            command = ['compose', ...command];
        }

        const env = await this.getEnvironmentVariables();
        return executeCommand(composeCommand, command, {...opt, env: {...env, ...(opt?.env || {})}});
    }

    public async executeCommandInService(serviceName: string, command: Array<string>, opts?: {
        execFlags?: Array<string>
    } & ExecuteCommandOptions): Promise<NonInteractiveCommandResult>
    public async executeCommandInService(serviceName: string, command: Array<string>, opts?: {
        execFlags?: Array<string>
    } & InteractiveExecuteCommandOptions): Promise<InteractiveCommandResult>

    /**
     * Executes a command inside a docker container
     * @param serviceName the name of the docker compose service to execute the command in
     * @param command the command to execute split in an array of strings.
     * @param opts
     */
    public async executeCommandInService(serviceName: string, command: Array<string>, opts?: {
        execFlags?: Array<string>
    } & ExecuteCommandOptions): Promise<ExecuteCommandResult> {
        await this.ensureComposeServiceIsRunning(serviceName);
        return executeCommand(
            await this.getDockerExecutable(),
            [
                'exec',
                ...(opts?.execFlags || (opts?.interactive ? ['-ti'] : ['-t'])),
                await this.getContainerIdFromServiceName(serviceName),
                ...command
            ],
            opts
        );
    }

    /**
     * Determines the docker executable path (docker or podman)
     */
    public async getDockerExecutable(): Promise<string> {
        if (this._dockerExecutable !== null) {
            return this._dockerExecutable;
        }

        try {
            // Check for podman first
            const podmanResult = await execAsync('command -v podman');
            const podmanPath = podmanResult.stdout.trim();

            if (podmanPath) {
                try {
                    // Check if podman service is active
                    await execAsync('systemctl is-active --quiet podman');
                    this._dockerExecutable = podmanPath;
                    return podmanPath;
                } catch (error) {
                    // Podman service is not active, continue to docker check
                }
            }
        } catch (error) {
            // Podman not found, continue to docker check
        }

        try {
            // Check for docker
            const dockerResult = await execAsync('command -v docker');
            const dockerPath = dockerResult.stdout.trim();

            if (dockerPath) {
                this._dockerExecutable = dockerPath;
                return dockerPath;
            }
        } catch (error) {
            // Docker not found
        }

        throw new Error('Sorry, but I did not find docker or podman on your system');
    }

    /**
     * Determines the compose executable path
     */
    public async getComposeExecutable(): Promise<string> {
        if (this._dockerComposeExecutable !== null) {
            return this._dockerComposeExecutable;
        }

        try {
            // Check for podman-compose first
            const podmanComposeResult = await execAsync('command -v podman-compose');
            const podmanComposePath = podmanComposeResult.stdout.trim();

            if (podmanComposePath) {
                try {
                    // Check if podman service is active
                    await execAsync('systemctl is-active --quiet podman');
                    this._dockerComposeExecutable = podmanComposePath;
                    return podmanComposePath;
                } catch (error) {
                    // Podman service is not active, continue to next check
                }
            }
        } catch (error) {
            // podman-compose not found, continue to next check
        }

        try {
            // Check for podman with compose subcommand
            const podmanResult = await execAsync('command -v podman');
            const podmanPath = podmanResult.stdout.trim();

            if (podmanPath) {
                try {
                    // Check if podman service is active
                    await execAsync('systemctl is-active --quiet podman');
                    const podmanCompose = `${podmanPath} compose`;
                    this._dockerComposeExecutable = podmanCompose;
                    return podmanCompose;
                } catch (error) {
                    // Podman service is not active, continue to next check
                }
            }
        } catch (error) {
            // podman not found, continue to next check
        }

        try {
            // Check for docker-compose
            const composeResult = await execAsync('command -v docker-compose');
            const composePath = composeResult.stdout.trim();

            if (composePath) {
                // Check if it's not in WSL path (not starting with /mnt/)
                if (!composePath.startsWith('/mnt/')) {
                    this._dockerComposeExecutable = composePath;
                    return composePath;
                }
            }
        } catch (error) {
            // docker-compose not found, continue to next check
        }

        try {
            // Check for docker compose v2
            const dockerExecutable = await this.getDockerExecutable();
            const composeVersionResult = await execAsync(`${dockerExecutable} compose version`);

            if (composeVersionResult.stdout.includes('v2')) {
                const dockerCompose = 'docker compose';
                this._dockerComposeExecutable = dockerCompose;
                return dockerCompose;
            }
        } catch (error) {
            // docker compose v2 not found
        }

        throw new Error('Sorry, but I did not find docker-compose or \'docker compose\' on your system');
    }

    /**
     * Determines if using docker or podman runtime
     */
    public async getRuntimeType(): Promise<'podman' | 'docker'> {
        if (this._dockerRuntimeType !== null) {
            return this._dockerRuntimeType;
        }

        const composeExecutable = await this.getComposeExecutable();
        if (composeExecutable.includes('podman')) {
            this._dockerRuntimeType = 'podman';
            return 'podman';
        }

        this._dockerRuntimeType = 'docker';
        return 'docker';
    }

    /**
     * Checks if a docker-compose service is running
     */
    public async isComposeServiceRunning(serviceName?: string): Promise<boolean> {
        try {
            return await this.isContainerRunning(await this.getContainerIdFromServiceName(serviceName));
        } catch (error) {
            return false;
        }
    }

    /**
     * Checks if a docker container is running
     */
    public async isContainerRunning(containerId: string): Promise<boolean> {
        try {
            const result = await this.executeDockerCommand(['inspect', '-f', '{{.State.Running}}', containerId]);
            return result.stdout.trim() === 'true';
        } catch (error) {
            return false;
        }
    }

    public async ensureComposeServiceIsRunning(serviceName?: string): Promise<void> {
        const isRunning = await this.isComposeServiceRunning(serviceName);
        if (!isRunning) {
            console.log(chalk.yellow(`The container for the service ${chalk.bold(`"${serviceName}"`)} is not running. I can try to start it for you.`));
            const doStart = await select({
                message: 'Should I start the container for you?',
                choices: [{value: 'all'}, {value: serviceName, name: 'only: ' + serviceName}, {value: 'no'}]
            });

            if (doStart === 'no') {
                throw new Error('Please start the required docker container and try again.');
            }

            if (doStart === 'all') {
                await this.up();
            } else {
                await this.up({args: [doStart]});
            }
        }
    }

    /**
     * Gets container ID from service name
     */
    public async getContainerIdFromServiceName(serviceName?: string, create?: boolean): Promise<string> {
        const service = serviceName || this.defaultServiceName;

        if (this._containerIdCache.has(service)) {
            return this._containerIdCache.get(service)!;
        }

        let containerId: string | undefined;
        try {
            const result = await this.executeComposeCommand(['ps', '-a', '-q', service]);
            containerId = result.stdout.trim();
        } catch (e) {
            // Silence...
        }

        if (!containerId) {
            if (create !== false) {
                console.log(chalk.yellow(`I could not find a container for the service ${chalk.bold(`"${service}"`)}. This might be because the container was removed.
I can try to let docker compose create the containers (without starting them) for you.`));
                const tryContainerCreate = await confirm({
                    message: 'Should I try to create the container for you?',
                    default: true
                });

                if (!tryContainerCreate) {
                    throw new Error(`No container found for service: ${service}`);
                }

                await this.executeComposeCommand(['up', '--no-start', service]);

                return this.getContainerIdFromServiceName(service, false);
            }

            throw new Error(`No container found for service: ${service}`);
        }

        this._containerIdCache.set(service, containerId);

        return containerId;
    }

    /**
     * Starts containers
     */
    public async up(opt?: UpOptions) {
        const args = new Set(opt?.args || []);
        if (opt?.follow !== true) {
            args.add('-d');
        }
        args.add('--remove-orphans');

        await this._context.events.trigger('docker:up:before', {args});
        await this.executeComposeCommand(['up', ...args], {interactive: true});
    }

    /**
     * Restarts the containers
     */
    public async restart(opt?: UpOptions & {
        // If true, a "down" and "up" is performed instead of a restart
        force?: boolean;
    }) {
        if (opt?.force) {
            await this.down();
        } else {
            await this.stop();
        }
        return this.up(opt);
    }

    /**
     * Stops containers
     */
    public async stop(args?: string[]) {
        await this.executeComposeCommand(['stop', ...(args ?? [])], {interactive: true});
    }

    /**
     * Stops and removes containers
     */
    public async down(args?: string[]) {
        await this.executeComposeCommand(['down', ...(args ?? [])], {interactive: true});
    }

    /**
     * Removes all containers and volumes of the project
     */
    public async clean(doConfirm?: boolean): Promise<void> {
        if (!doConfirm) {
            doConfirm = await confirm({
                message: 'Are you sure you want to remove all containers and volumes?',
                default: true
            });
        }

        if (!doConfirm) {
            return;
        }

        if (await this.getRuntimeType() === 'docker') {
            await this.executeComposeCommand(['down', '--rmi', 'all', '--volumes'], {interactive: true});
            await this.executeComposeCommand(['rm', '--force', '--stop', '--volumes'], {interactive: true});
        } else {
            await this.executeComposeCommand(['down'], {interactive: true});
        }
    }

    /**
     * Shows the logs of the containers
     */
    public async logs(opts?: {
        all?: boolean,
        args?: Array<string>,
    }) {
        opts = opts || {};
        const args = new Set(opts.args || []);

        const someArgsStartWithoutDash = Array.from(args).filter(arg => arg !== '').some(arg => !arg.startsWith('-'));

        if (!opts.all && !someArgsStartWithoutDash) {
            args.add(this.defaultServiceName);
        }

        await this.executeComposeCommand(['logs', ...args], {foreground: true});
    }

    /**
     * Shows the status of the containers
     * @param args
     */
    public async ps(args?: Array<string>) {
        await this.executeComposeCommand(['ps', ...(args ?? [])], {foreground: true});
    }

    /**
     * Returns the help text of a docker-compose command
     * YOU MUST call the getDockerComposeExecutable() method before calling this method! This is a workaround because Command.addHelpText is synchronous and does not support async functions
     */
    public getComposeCommandHelp(command: string): string {
        if (!this._dockerComposeExecutable) {
            throw new Error('Please execute the DockerContext.getDockerComposeExecutable() method, first - This is a workaround because Command.addHelpText is synchronous and does not support async functions');
        }

        const composeExecutable = this._dockerComposeExecutable;
        const result = execSync(`${composeExecutable} ${command} --help`).toString();

        // Remove everything before the "options" section
        const resultTrimmed = result.substring(result.indexOf('Options:'));
        return `\n  Inherited "${command}" docker compose command ${resultTrimmed}`;
    }

    /**
     * Executes a command in a container or opens a shell
     */
    public async ssh(serviceName?: string, cmd?: string) {
        serviceName = serviceName ?? this.defaultServiceName;

        // Start the service if it's not running
        await this.ensureComposeServiceIsRunning(serviceName);

        const shell = await this.findShellOfContainer(await this.getContainerIdFromServiceName(serviceName));

        const command = cmd ? [shell, '-c', cmd] : [shell];

        await this.executeCommandInService(serviceName, command, {interactive: true});
    }

    /**
     * Provides environment variables based on runtime type
     */
    protected async getEnvironmentVariables(): Promise<Record<string, string>> {
        const runtimeType = await this.getRuntimeType();
        const defaultUid = this.defaultUid;
        const defaultGid = this.defaultGid;

        const env: Record<string, string> = {
            ...process.env,
            FORCE_COLOR: '1',
            BUILDKIT_PROGRESS: 'plain',
            COMPOSE_DOCKER_CLI_BUILD: '1',
            DOCKER_BUILDKIT: '1'
        };

        if (runtimeType === 'podman') {
            env.DOCKER_RUNTIME = 'podman';
            env.DOCKER_USER = 'root';
        } else {
            env.DOCKER_RUNTIME = 'docker';
            env.DOCKER_USER = `${defaultUid}:${defaultGid}`;
            env.DOCKER_UID = defaultUid;
            env.DOCKER_GID = defaultGid;
        }

        return env;
    }

    /**
     * Finds the shell of a container
     * @param containerId the ID of the container to find the shell for
     * @returns the path to the shell executable
     */
    protected async findShellOfContainer(containerId: string): Promise<string> {
        const shellOptions = this.shellsToUse;

        // Check if "which" or "command" are available
        let whichCommand: string[] | undefined;

        try {
            await this.executeDockerCommand(['exec', containerId, 'which', 'which'], {throwOnExitCode: true});
            whichCommand = ['which'];
        } catch (e) {
            try {
                await this.executeDockerCommand(['exec', containerId, 'command'], {throwOnExitCode: true});
                whichCommand = ['command', '-v'];
            } catch (e) {
                // Silence...
            }
        }

        // Method 1: Using `docker exec <container_name_or_id> which <shell>` to find the a shell
        if (whichCommand) {
            for (const shell of shellOptions) {
                try {
                    const result = await this.executeDockerCommand(['exec', containerId, ...whichCommand, shell], {throwOnExitCode: true});
                    return result.stdout.trim();
                } catch (e) {
                    // Silence...
                }
            }
        }

        // Method 2: Inspecting the image configuration
        try {
            const inspectResult = await this.executeDockerCommand(['inspect', containerId], {throwOnExitCode: true});
            const inspectData = JSON.parse(inspectResult.stdout);
            if (inspectData && inspectData.length > 0) {
                // Check for explicitly defined shell
                if (inspectData[0].Config && inspectData[0].Config.Shell) {
                    return inspectData[0].Config.Shell[0];
                }
            }
        } catch (e) {
            // Silence...
        }

        // Method 3: Looking at /etc/passwd for real shells
        try {
            const passwdContent = await this.executeDockerCommand(['exec', containerId, 'cat', '/etc/passwd'], {throwOnExitCode: true});
            const lines = passwdContent.stdout.split('\n').map(line => line.trim());
            for (const line of lines) {
                const fields = line.split(':');
                const shell = fields.pop();
                if (shell && shell.startsWith('/')) {
                    for (const commonShell of shellOptions) {
                        if (shell.endsWith(commonShell)) {
                            return shell;
                        }
                    }
                }
            }
        } catch (e) {
            // Silence...
        }

        // If we reached here, throw an error
        throw new Error('Unable to determine shell for container ' + containerId);
    }
}
