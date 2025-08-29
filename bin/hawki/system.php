<?php

/**
 * System and dependency management commands
 */

function checkDependencies() {
    echo BOLD . "Checking dependencies..." . RESET . PHP_EOL;

    // Check PHP version
    $phpVersion = phpversion();
    $phpRequired = '8.1.0';
    if (version_compare($phpVersion, $phpRequired, '>=')) {
        echo GREEN . "✓ PHP Version: $phpVersion" . RESET . PHP_EOL;
    } else {
        echo RED . "✗ PHP Version: $phpVersion (required: >= $phpRequired)" . RESET . PHP_EOL;
    }

    // Check Composer
    exec('composer --version 2>/dev/null', $composerOutput, $composerReturnVar);
    if ($composerReturnVar === 0) {
        echo GREEN . "✓ Composer: " . $composerOutput[0] . RESET . PHP_EOL;
    } else {
        echo RED . "✗ Composer not found" . RESET . PHP_EOL;
        $missingDeps[] = 'composer';
    }

    // Check Node.js
    exec('node --version 2>/dev/null', $nodeOutput, $nodeReturnVar);
    if ($nodeReturnVar === 0) {
        $nodeVersion = trim($nodeOutput[0]);
        echo GREEN . "✓ Node.js: $nodeVersion" . RESET . PHP_EOL;
    } else {
        echo RED . "✗ Node.js not found" . RESET . PHP_EOL;
        $missingDeps[] = 'nodejs';
    }

    // Check npm
    exec('npm --version 2>/dev/null', $npmOutput, $npmReturnVar);
    if ($npmReturnVar === 0) {
        $npmVersion = trim($npmOutput[0]);
        echo GREEN . "✓ npm: $npmVersion" . RESET . PHP_EOL;
    } else {
        echo RED . "✗ npm not found" . RESET . PHP_EOL;
        $missingDeps[] = 'npm';
    }

    // Check PHP extensions
    $requiredExtensions = ['mbstring', 'xml', 'pdo', 'curl', 'zip', 'json', 'fileinfo', 'openssl'];
    $missingExtensions = [];

    foreach ($requiredExtensions as $extension) {
        if (extension_loaded($extension)) {
            echo GREEN . "✓ PHP extension: $extension" . RESET . PHP_EOL;
        } else {
            echo RED . "✗ PHP extension: $extension (missing)" . RESET . PHP_EOL;
            $missingExtensions[] = $extension;
        }
    }

    // Summary and offer to install missing dependencies
    if (!empty($missingDeps) || !empty($missingExtensions)) {
        echo PHP_EOL . YELLOW . "Some dependencies are missing. Installation instructions:" . RESET . PHP_EOL;

        if (!empty($missingDeps)) {
            echo BOLD . "Missing tools:" . RESET . PHP_EOL;
            foreach ($missingDeps as $dep) {
                switch ($dep) {
                    case 'composer':
                        echo "- Composer: https://getcomposer.org/download/" . PHP_EOL;
                        break;
                    case 'nodejs':
                    case 'npm':
                        echo "- Node.js and npm: https://nodejs.org/en/download/" . PHP_EOL;
                        break;
                }
            }
        }

        if (!empty($missingExtensions)) {
            echo BOLD . "Missing PHP extensions:" . RESET . PHP_EOL;
            echo "You can install them using your package manager, for example:" . PHP_EOL;

            // Detect OS
            $os = PHP_OS;
            if (stripos($os, 'darwin') !== false) {
                // macOS
                echo "  brew install php" . PHP_EOL;
                echo "  For specific extensions: brew install php@8.1-<extension_name>" . PHP_EOL;
            } elseif (stripos($os, 'linux') !== false) {
                // Linux (assume Debian/Ubuntu)
                $extensionsList = implode(' ', array_map(function($ext) {
                    return "php-$ext";
                }, $missingExtensions));
                echo "  sudo apt update" . PHP_EOL;
                echo "  sudo apt install $extensionsList" . PHP_EOL;
            } elseif (stripos($os, 'win') !== false) {
                // Windows
                echo "  Enable extensions in your php.ini file" . PHP_EOL;
                echo "  Find the php.ini file and uncomment the lines with these extensions" . PHP_EOL;
            }
        }

        // Ask if auto-installation should be attempted (for some dependencies)
        if (!empty($missingDeps) && (stripos(PHP_OS, 'linux') !== false || stripos(PHP_OS, 'darwin') !== false)) {
            echo PHP_EOL . "Would you like to try to install the missing tools? (y/n) ";
            $answer = trim(fgets(STDIN));

            if (strtolower($answer) === 'y') {
                foreach ($missingDeps as $dep) {
                    switch ($dep) {
                        case 'composer':
                            echo YELLOW . "Installing Composer..." . RESET . PHP_EOL;
                            passthru('curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer');
                            break;
                        case 'nodejs':
                        case 'npm':
                            if (stripos(PHP_OS, 'darwin') !== false) {
                                echo YELLOW . "Installing Node.js using Homebrew..." . RESET . PHP_EOL;
                                passthru('brew install node');
                            } elseif (stripos(PHP_OS, 'linux') !== false) {
                                echo YELLOW . "Installing Node.js using apt..." . RESET . PHP_EOL;
                                passthru('sudo apt update && sudo apt install nodejs npm');
                            }
                            break;
                    }
                }
                // Recheck dependencies after installation attempts
                echo PHP_EOL . BOLD . "Rechecking dependencies after installation attempts..." . RESET . PHP_EOL;
                checkDependencies();
            }
        }
    } else {
        echo PHP_EOL . GREEN . BOLD . "All dependencies are satisfied!" . RESET . PHP_EOL;
    }
}

/**
 * Clear all Laravel caches
 */
function clearCache() {
    echo BOLD . "Clearing Laravel caches..." . RESET . PHP_EOL;

    // Execute cache clearing commands
    echo YELLOW . "Running config:clear..." . RESET . PHP_EOL;
    passthru('php artisan config:clear');

    echo YELLOW . "Running cache:clear..." . RESET . PHP_EOL;
    passthru('php artisan cache:clear');

    echo YELLOW . "Running view:clear..." . RESET . PHP_EOL;
    passthru('php artisan view:clear');

    echo YELLOW . "Running route:clear..." . RESET . PHP_EOL;
    passthru('php artisan route:clear');

    echo YELLOW . "Running event:clear..." . RESET . PHP_EOL;
    passthru('php artisan event:clear');

    echo YELLOW . "Running compiled:clear..." . RESET . PHP_EOL;
    passthru('php artisan clear-compiled');

    echo YELLOW . "Running optimize:clear..." . RESET . PHP_EOL;
    passthru('php artisan optimize:clear');

    echo GREEN . "✓ All caches have been cleared" . RESET . PHP_EOL;
}