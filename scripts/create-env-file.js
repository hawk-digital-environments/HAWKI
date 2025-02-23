/**
 * Small platform independent script to create a new .env file, if it does
 * not already exist. This is used by the "npm run init" command to setup
 * a new developer machine.
 */
import shell from "shelljs";

if (!shell.test("-e", ".env")) {
    console.log("No .env file found. Copying example file.");
    shell.cp(".env.example", ".env");
} else {
    console.log(".env file already exists");
}
