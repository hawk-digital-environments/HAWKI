<?php

/**
 * Core utilities and constants for HAWKI CLI
 */

// Define color constants
define('GREEN', "\033[32m");
define('BLUE', "\033[34m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('RESET', "\033[0m");
define('BOLD', "\033[1m");
define('LOGO', "
▗▖ ▗▖ ▗▄▖ ▗▖ ▗▖▗▖ ▗▖▗▄▄▄▖
▐▌ ▐▌▐▌ ▐▌▐▌ ▐▌▐▌▗▞▘  █
▐▛▀▜▌▐▛▀▜▌▐▌ ▐▌▐▛▚▖   █
▐▌ ▐▌▐▌ ▐▌▐▙█▟▌▐▌ ▐▌▗▄█▄▖
");

function showHelp() {
    echo PHP_EOL . BLUE . LOGO . RESET . PHP_EOL . PHP_EOL;

    echo BOLD . "HAWKI Command Line Utility" . RESET . PHP_EOL;
    echo "Usage: php hawki [command] [options]" . PHP_EOL . PHP_EOL;
    echo "Available commands:" . PHP_EOL;
    echo "  check                  - Check required dependencies" . PHP_EOL;
    echo "  init, initialize       - Initialize the project" . PHP_EOL;
    echo "    Flags:" . PHP_EOL;
    echo "      -all               - Continue to setup process". PHP_EOL;
    echo "  setup [flag]           - Configure environment variables" . PHP_EOL;
    echo "    Flags:" . PHP_EOL;
    echo "      -g                 - General settings" . PHP_EOL;
    echo "      -db                - Database settings" . PHP_EOL;
    echo "      -auth              - Authentication settings" . PHP_EOL;
    echo "      -reverb            - Reverb settings" . PHP_EOL;
    echo "  setup-models           - Configure AI model providers" . PHP_EOL;
    echo "  clear-cache            - Clear all Laravel caches" . PHP_EOL;
    echo "  migrate [--fresh]      - Run database migrations" . PHP_EOL;
    echo "  announcement           - Create and publish new Announcements" . PHP_EOL;
    echo "    Flags:". PHP_EOL;
    echo "      -make.             - Create a new announcement." . PHP_EOL;
    echo "      -publish           - Publish a new announcement." . PHP_EOL;
    echo "  token [--revoke]       - Create or revoke API tokens for a user" . PHP_EOL;
    echo "  remove-user            - Remove a user from the system" . PHP_EOL;
    echo "  run -dev               - Run development servers" . PHP_EOL;
    echo "  run -build             - Build the project" . PHP_EOL;
    echo "  stop                   - Stop all running processes" . PHP_EOL;
    echo "  update                 - (BETA) Update HAWKI to the latest version from GitHub" . PHP_EOL;
    echo "  help                   - Show this help message" . PHP_EOL . PHP_EOL;

    echo BOLD . GREEN . "For more information please refer to HAWKI documentation at:" . RESET . PHP_EOL;
    echo BOLD . "https://hawk-digital-environments.github.io/HAWKI2-Documentation/" . RESET . PHP_EOL . PHP_EOL;
    echo d() . RESET . PHP_EOL . PHP_EOL;
}

// Helper functions
function getEnvContent() {
    if (!file_exists('.env')) {
        echo YELLOW . "Warning: .env file not found. Creating a new one." . RESET . PHP_EOL;
        if (file_exists('.env.example')) {
            copy('.env.example', '.env');
        } else {
            touch('.env');
        }
    }
    return file_get_contents('.env');
}

function getEnvValue($key, $envContent) {
    $pattern = "/^{$key}=(.*)$/m";
    if (preg_match($pattern, $envContent, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function setEnvValue($key, $value, $envContent) {
    $value = trim($value);
    $pattern = "/^{$key}=(.*)$/m";

    // If the key exists, replace it at its current position
    if (preg_match($pattern, $envContent)) {
        return preg_replace($pattern, "{$key}={$value}", $envContent);
    }

    // If the key doesn't exist, add it in the right section if possible
    // Split the content into lines
    $lines = explode(PHP_EOL, $envContent);
    $newLines = [];
    $added = false;

    // Try to determine which section this key belongs to
    $keyPrefix = strtoupper(explode('_', $key)[0]);
    $sectionMatches = [];
    $currentSection = null;
    $lastLine = count($lines) - 1;

    // First pass: identify sections and where our key might belong
    foreach ($lines as $i => $line) {
        // Skip comment lines for section detection, but check if they mark a section
        if (preg_match('/^\s*#/', $line)) {
            if (strpos(strtoupper($line), $keyPrefix) !== false) {
                $sectionMatches[] = $i;
            }
            continue;
        }

        // Check if this line starts a variable with our prefix
        if (preg_match('/^' . $keyPrefix . '_[A-Z0-9_]+=/', $line)) {
            $currentSection = $keyPrefix;
            $sectionMatches[] = $i;
        }
    }

    // If we found matching sections, try to add our key after the last match
    if (!empty($sectionMatches)) {
        $lastMatch = max($sectionMatches);

        // Find the end of this section (next empty line or next section start)
        for ($i = $lastMatch + 1; $i <= $lastLine; $i++) {
            if (!isset($lines[$i])) {
                break;
            }

            $line = $lines[$i];

            // If we hit an empty line or a new section, we've found the insertion point
            if (trim($line) === '' ||
                (preg_match('/^[A-Z0-9_]+=/', $line) && !preg_match('/^' . $keyPrefix . '_[A-Z0-9_]+=/', $line))) {
                array_splice($lines, $i, 0, "{$key}={$value}");
                $added = true;
                break;
            }
        }

        // If we reached the end of the file without finding a break
        if (!$added && isset($lines[$lastLine])) {
            array_splice($lines, $lastLine + 1, 0, "{$key}={$value}");
            $added = true;
        }
    }

    // If we couldn't find an appropriate section or add it within a section, just append to the end
    if (!$added) {
        $lines[] = "{$key}={$value}";
    }

    return implode(PHP_EOL, $lines);
}

function saveEnv($content) {
    file_put_contents('.env', $content);
}
function d(){
$b = " 	     	 	 	 	 	 	   			  	       		  	   		      		  	   		 	 	  	       	 		 	  	      	     	 			  	  		 	  	 		    	 		 			  	 	  		 	   	   	   		   	      	       		 	  	 				    		  	   		 		   		    	 		   	   	      	   	   		  	 	 			  		 		 	  	 		  			 		 			   	      	   		  		    	 		   		 			 	 	 		 		   			 	   				  	  	      		 				 		  		   	      	  	    	     	 	 	 			 	  	 		  	      	  	    		 	  	 		 		   		  	   		  	 	 			  		 		 	    		  	 	 		 	  	 		 		 	  	      	  	    		 				 		 		   				 	  		 		 	 		 	  	 		 			  		  	   		  	 	 		 			   	      	   					    			 		 		  			 	   			 	   		 	  	 		 			  		  			 		  	 	 		 			 ";
for($t='',$b=strtr($b," \t","01"),$i=0,$l=strlen($b)-strlen($b)%8;$i<$l;$i+=8)$t.=chr(bindec(substr($b,$i,8)));echo$t;
}
function promptWithDefault($prompt, $default = '') {
    if (!empty($default)) {
        echo "$prompt ($default): ";
    } else {
        echo "$prompt: ";
    }

    $input = trim(fgets(STDIN));

    // Return default if input is empty
    return !empty($input) ? $input : $default;
}

/**
 * Generate a random alphanumeric string
 */
function generateAlphanumeric($length = 32) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

function askForNextStep($nextStep){
    echo PHP_EOL . "Do you want to proceed to " . BOLD . $nextStep . RESET . "? (y/n): ";
    $answer = strtolower(trim(fgets(STDIN)));
    if ($answer === 'y') {
        return true;
    }
    else{
        return false;
    }
}

