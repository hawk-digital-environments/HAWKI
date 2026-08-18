import * as childProcess from 'child_process';

interface BaseExecuteCommandOptions {
    /**
     * Additional environment variables to pass to the command.
     */
    env?: NodeJS.ProcessEnv;
    /**
     * Whether a non-zero exit code is turned into a thrown {@link CommandFailedError}.
     * Defaults to `true`: a command that fails aborts the CLI invocation and its
     * exit code becomes the CLI's own (see `Application.run()`).
     *
     * Set to `false` only when a non-zero code is a normal outcome rather than a
     * failure — the caller inspects `result.code` itself, or the command is an
     * interactive session the user ends (a shell, `logs`, an attached `up`),
     * whose exit code is theirs and not ours to report.
     */
    throwOnExitCode?: boolean;

    /**
     * The working directory to execute the command in. If omitted, the current working directory will be used.
     */
    cwd?: string;
}

interface GenericExecuteCommandOptions extends BaseExecuteCommandOptions {
    /**
     * True, will print stdout and stderr to the console as they are produced.
     * If omitted (and interactive is not set), the command will be run in the background and
     * stdout and stderr will be captured and returned as a string.
     */
    foreground?: boolean;

    /**
     * If true, the command will be run interactively. This is useful for commands that require user input.
     * If this is set to true, the foreground option will be ignored, because the command will always be run in the foreground.
     */
    interactive?: boolean;
}

export interface NonInteractiveExecuteCommandOptions extends BaseExecuteCommandOptions {
    foreground?: boolean;
    interactive?: false;
}

export interface InteractiveExecuteCommandOptions extends BaseExecuteCommandOptions {
    foreground?: boolean;
    interactive: true;
}

export interface InteractiveCommandResult {
    code: number;
}

export interface NonInteractiveCommandResult {
    stdout: string;
    stderr: string;
    code: number;
}

export type ExecuteCommandOptions = InteractiveExecuteCommandOptions | NonInteractiveExecuteCommandOptions;
export type ExecuteCommandResult = InteractiveCommandResult | NonInteractiveCommandResult;

/**
 * Thrown when a command exits non-zero and `throwOnExitCode` was not disabled.
 *
 * Carries the child's exit code so `Application.run()` can adopt it as the
 * CLI's own instead of flattening every failure to `1` — `bin/env test` has to
 * report what the test runner reported for CI to read it.
 */
export class CommandFailedError extends Error {
    public readonly code: number;

    constructor(command: string, args: string[], code: number) {
        super(`Command failed with exit code ${code}: ${command} ${args.join(' ')}`);
        this.name = 'CommandFailedError';
        this.code = code;
    }
}

export async function executeCommand(command: string, args: string[], opt: NonInteractiveExecuteCommandOptions): Promise<NonInteractiveCommandResult>;
export async function executeCommand(command: string, args: string[], opt: InteractiveExecuteCommandOptions): Promise<InteractiveCommandResult>;
export async function executeCommand(command: string, args: string[], opt?: GenericExecuteCommandOptions): Promise<NonInteractiveCommandResult>;
export async function executeCommand(command: string, args: string[], opt?: GenericExecuteCommandOptions): Promise<ExecuteCommandResult> {
    const env = opt?.env || process.env;
    args = args.filter((arg) => arg !== undefined && arg !== null && arg !== '');
    const exitCodeHandler = (res: ExecuteCommandResult) => {
        if (res.code !== 0 && opt?.throwOnExitCode !== false) {
            throw new CommandFailedError(command, args, res.code);
        }
        return res;
    };
    const cwd = opt?.cwd;
    if (opt?.interactive) {
        return executeInteractiveCommand(command, args, env, cwd).then(exitCodeHandler);
    }
    if (opt?.foreground) {
        return executeCommandInForeground(command, args, env, cwd).then(exitCodeHandler);
    } else {
        return executeCommandInBackground(command, args, env, cwd).then(exitCodeHandler);
    }
}

/**
 * Executes a command asynchronously in the background and returns the result.
 * @param command
 * @param args
 * @param env
 * @param cwd
 */
async function executeCommandInBackground(
    command: string,
    args: string[],
    env: NodeJS.ProcessEnv,
    cwd?: string
): Promise<{ stdout: string, stderr: string, code: number }> {
    return new Promise((resolve, reject) => {
        let stdout = '';
        let stderr = '';

        const proc = childProcess.spawn(command, args, {
            // No need to inherit stdio since we're capturing all output
            stdio: ['ignore', 'pipe', 'pipe'],
            env,
            cwd
        });

        proc.stdout.on('data', (data) => {
            stdout += data.toString();
        });

        proc.stderr.on('data', (data) => {
            stderr += data.toString();
        });

        proc.on('close', (code) => {
            resolve({
                stdout,
                stderr,
                code: code ?? 0
            });
        });

        proc.on('error', (err) => {
            reject(err);
        });
    });
}

/**
 * Executes a command asynchronously in the foreground and returns the result.
 * Basically the same as executeCommandInBackground, but dumps stdout and stderr into the
 * process streams while they happen.
 *
 * @param command
 * @param args
 * @param env
 * @param cwd
 */
async function executeCommandInForeground(
    command: string,
    args: string[],
    env: NodeJS.ProcessEnv,
    cwd?: string
): Promise<{ stdout: string, stderr: string, code: number }> {
    return new Promise((resolve, reject) => {
        let stdout = '';
        let stderr = '';

        const proc = childProcess.spawn(command, args, {
            stdio: ['inherit', 'pipe', 'pipe'],
            env,
            cwd
        });

        proc.stdout.on('data', (data) => {
            const output = data.toString();
            stdout += output;
            process.stdout.write(output);
        });

        proc.stderr.on('data', (data) => {
            const output = data.toString();
            stderr += output;
            process.stderr.write(output);
        });

        proc.on('close', (code) => {
            resolve({
                stdout,
                stderr,
                code: code ?? 0
            });
        });

        proc.on('error', (err) => {
            reject(err);
        });
    });
}

/**
 * Executes a command interactively in the foreground and returns the result.
 * Use this for commands that require user input or tools like nano, vim, etc.
 * @param command
 * @param args
 * @param env
 * @param cwd
 */
async function executeInteractiveCommand(
    command: string,
    args: string[],
    env: NodeJS.ProcessEnv,
    cwd?: string
): Promise<{ code: number }> {
    return new Promise((resolve, reject) => {
        try {
            // For other platforms, use standard spawn with inherit
            const proc = childProcess.spawn(command, args, {
                stdio: 'inherit',
                env,
                cwd
            });

            proc.on('close', (code) => {
                resolve({code: code ?? 0});
            });

            proc.on('error', (err) => {
                reject(err);
            });
        } catch (e) {
            reject(e);
        }
    });
}
