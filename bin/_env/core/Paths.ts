import * as process from 'node:process';
import * as path from 'node:path';
import * as fs from 'node:fs';

export class Paths {
    public readonly scriptDir: string;
    public readonly envDir: string;
    public readonly projectDir: string;
    public readonly addonPaths: string[];
    public readonly envFilePath: string;
    public readonly envFileHashPath: string;
    public readonly envFileTemplatePath: string;
    public readonly configFilePath: string;
    public readonly envHomeDir: string;
    public readonly certsDir: string;

    public constructor(
        scriptDir: string,
        envDir: string,
        projectDir: string,
        addonPaths: string[],
        envFilePath: string,
        envFileHashPath: string,
        envFileTemplatePath: string,
        configFilePath: string,
        envHomeDir: string,
        certsDir: string
    ) {
        this.scriptDir = scriptDir;
        this.envDir = envDir;
        this.projectDir = projectDir;
        this.addonPaths = addonPaths;
        this.envFilePath = envFilePath;
        this.envFileHashPath = envFileHashPath;
        this.envFileTemplatePath = envFileTemplatePath;
        this.configFilePath = configFilePath;
        this.envHomeDir = envHomeDir;
        this.certsDir = certsDir;
    }
}

export function createPaths(): Paths {
    if (!process.env.SCRIPT_DIR) {
        throw new Error('SCRIPT_DIR is not defined');
    }
    if (!fs.existsSync(process.env.SCRIPT_DIR)) {
        throw new Error(`SCRIPT_DIR does not exist: ${process.env.SCRIPT_DIR}`);
    }
    const scriptDir = process.env.SCRIPT_DIR;

    if (!process.env.ENV_DIR) {
        throw new Error('ENV_DIR is not defined');
    }
    if (!fs.existsSync(process.env.ENV_DIR)) {
        throw new Error(`ENV_DIR does not exist: ${process.env.ENV_DIR}`);
    }
    const envDir = process.env.ENV_DIR;

    if (!process.env.PROJECT_DIR) {
        throw new Error('PROJECT_DIR is not defined');
    }
    if (!fs.existsSync(process.env.PROJECT_DIR)) {
        throw new Error(`PROJECT_DIR does not exist: ${process.env.PROJECT_DIR}`);
    }
    const projectDir = process.env.PROJECT_DIR;

    if (!process.env.ENV_HOME) {
        throw new Error('ENV_HOME is not defined');
    }
    if (!fs.existsSync(process.env.ENV_HOME)) {
        throw new Error(`ENV_HOME does not exist: ${process.env.ENV_HOME}`);
    }
    const envHomeDir = process.env.ENV_HOME;

    const commandPaths = [envDir, path.resolve(envDir, 'addons')];
    const envFilePath = path.resolve(projectDir, '.env');
    const envFileHashPath = path.resolve(envDir, '.env.hash');
    const envFileTemplatePath = path.resolve(projectDir, '.env.example');
    const configFilePath = path.resolve(projectDir, 'env.config.json');
    const certsDir = path.resolve(projectDir, 'docker/certs');

    return new Paths(
        scriptDir,
        envDir,
        projectDir,
        commandPaths,
        envFilePath,
        envFileHashPath,
        envFileTemplatePath,
        configFilePath,
        envHomeDir,
        certsDir
    );
}
