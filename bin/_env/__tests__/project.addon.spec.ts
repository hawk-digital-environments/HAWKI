import { beforeEach, describe, expect, it, jest } from "@jest/globals";
import { Command } from "commander";
import { addon } from "../project.addon.ts"; // Path to the file with export const addon

describe("Addons", () => {
    describe("Artisan addon", () => {
        let program: Command;
        let mockContext: any;

        beforeEach(async () => {
            program = new Command();
            mockContext = {
                docker: {
                    executeCommandInService: jest
                        .fn<any>()
                        .mockResolvedValue(undefined as any),
                },
                paths: { projectDir: "/tmp/project" },
                composer: {
                    exec: jest.fn<any>().mockResolvedValue(undefined as any),
                },
            };
            const addonInstance = await addon(mockContext);
            if (addonInstance.commands) {
                await addonInstance.commands(program);
            }
        });

        it('should translate "./env artisan [command]" to the correct docker call', async () => {
            // Simulate the exact arguments passed when running: ./env artisan route:list
            // Commander parseAsync expects [node, script, ...args]
            const argv = ["node", "index.ts", "artisan", "test"];

            await program.parseAsync(argv);

            // Assert the exact mapping logic inside your addon.action()
            expect(
                mockContext.docker.executeCommandInService,
            ).toHaveBeenCalledWith("app", ["gosu", "www-data", "php", "artisan", "test"], {
                interactive: true,
            });
        });

        it('should forward phpunit flags through a composer "--" separator', async () => {
            // Simulates: ./env test php feature -- --filter MyTest — commander
            // strips the "--", the addon must re-insert it so composer's
            // run-script forwards the flags to phpunit instead of failing.
            const argv = ["node", "index.ts", "test", "php", "feature", "--", "--filter", "MyTest"];

            await program.parseAsync(argv);

            expect(mockContext.composer.exec).toHaveBeenCalledWith([
                "run",
                "test:feature",
                "--",
                "--filter",
                "MyTest",
            ]);
        });

        it('should not add a composer separator when no arguments are forwarded', async () => {
            const argv = ["node", "index.ts", "test", "php", "feature"];

            await program.parseAsync(argv);

            expect(mockContext.composer.exec).toHaveBeenCalledWith(["run", "test:feature"]);
        });
    });
});
