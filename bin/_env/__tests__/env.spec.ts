import { describe, expect, it } from "@jest/globals";
import { exec } from "node:child_process";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { promisify } from "node:util";

const execAsync = promisify(exec);
const __dirname = dirname(fileURLToPath(import.meta.url));

describe("CLI utils with .env.example as .env", () => {
    const runEnvScript = async () => {
        const BIN_PATH = resolve(__dirname, "../../env");
        return await execAsync(`sh ${BIN_PATH}`, {
            env: {
                ...process.env,
                TESTING_SKIP_CLI_INPUT: "true", // Note: There is no reasonable way execute "env" with cli input in tests
            },
        });
    };
    it("should output help text containing specific commands when run with no args", async () => {
        const { stdout, stderr } = await runEnvScript();

        const fullOutput = stdout + stderr;

        expect(fullOutput).toContain(
            "This is a command line tool to manage your HAWKI project.",
        );
        expect(fullOutput).toContain("artisan");
        expect(fullOutput).toContain("clear-cache");
        expect(fullOutput).toContain("composer");
        expect(fullOutput).toContain("dev");
        expect(fullOutput).toContain("docker:build:prod [options]");
        expect(fullOutput).toContain("docker:clean|clean [options]");
        expect(fullOutput).toContain("docker:down|down");
        expect(fullOutput).toContain("docker:install|install");
        expect(fullOutput).toContain("docker:logs|logs [options]");
        expect(fullOutput).toContain("docker:open|open ");
        expect(fullOutput).toContain("docker:ps|ps");
        expect(fullOutput).toContain("docker:restart|restart");
        expect(fullOutput).toContain("docker:ssh|ssh");
        expect(fullOutput).toContain("docker:stop|stop");
        expect(fullOutput).toContain("docker:up|up [options]");
        expect(fullOutput).toContain("env:reset");
        expect(fullOutput).toContain("docs");
        expect(fullOutput).toContain("hawki");
        expect(fullOutput).toContain("help [command");
        expect(fullOutput).toContain("mailhog");
        expect(fullOutput).toContain("npm");
        expect(fullOutput).toContain("queue");
        expect(fullOutput).toContain("setup-models");
        expect(fullOutput).toContain("start-docker-production-test [options]");
        expect(fullOutput).toContain("websocket|reverb");
    });
});
