<?php

/**
 * Announcement management commands
 */

function handleAnnouncementCommand($flags) {
    if(isset($flags[0])){
        switch($flags[0]){
            case '-make':
                makeAnnouncement();
                break;
            case '-publish':
                publishAnnouncement();
                break;
            default:
                echo RED . "Invalid flag for announcement command: {$flags[0]}" . RESET . PHP_EOL;
                showHelp();
        }
    }
    else {
        echo RED . "Missing flag for announcement command. Use -make or -publish" . RESET . PHP_EOL;
        showHelp();
    }
}

function makeAnnouncement() {
    echo "Making a new announcement...\nTitle: ";
    $title = trim(fgets(STDIN));

    if (empty($title)) {
        echo "Title cannot be empty.\n";
        return;
    }

    $command = "php artisan announcement:make " . escapeshellarg($title); // no --edit
    // passthru keeps STDIN/STDOUT attached, no hanging
    passthru($command, $returnVar);

    if ($returnVar !== 0) {
        echo "Could not create announcement. Artisan returned code $returnVar.\n";
        return;
    }
}

function publishAnnouncement(?string $title = "", ?string $view = ""){
    // Title & View
    echo "Publishing an announcement...\n";
    echo $view;
    if(empty($title)){
        echo "Title: ";
        $title = trim(fgets(STDIN));
    }
    if(empty($view)){
        echo "View (e.g. summer_update): ";
        $view = trim(fgets(STDIN));
    }

    // Type (with default)
    echo "Type [policy, news, system, event, info] (default: info): ";
    $type = trim(fgets(STDIN));
    if (empty($type)) $type = 'info';

    // Start and Expire
    echo "Start datetime (Y-m-d H:i:s, leave blank for none): ";
    $start = trim(fgets(STDIN));

    echo "Expire datetime (Y-m-d H:i:s, leave blank for none): ";
    $expire = trim(fgets(STDIN));

    // Global or target users
    echo "Is this a GLOBAL announcement? (y/n): ";
    $isGlobal = trim(fgets(STDIN));
    $globalFlag = '';
    $users = [];

    if (strtolower($isGlobal) === 'y') {
        $globalFlag = '--global';
    } else {
        echo "Target User IDs (comma-separated): ";
        $userInput = trim(fgets(STDIN));
        $userInput = str_replace(' ', '', $userInput); // Remove whitespace
        if (!empty($userInput)) {
            $users = explode(',', $userInput);
        }
    }

    // Build command
    $cmd = "php artisan announcement:publish "
        . escapeshellarg($title) . " " . escapeshellarg($view)
        . " --type=" . escapeshellarg($type);

    if (!empty($globalFlag)) $cmd .= " $globalFlag";
    foreach ($users as $userId) {
        if (!empty($userId)) {
            $cmd .= " --users=" . escapeshellarg($userId);
        }
    }
    if (!empty($start)) {
        $cmd .= " --start=" . escapeshellarg($start);
    }
    if (!empty($expire)) {
        $cmd .= " --expire=" . escapeshellarg($expire);
    }

    echo "\nRunning: $cmd\n\n";
    passthru($cmd);
}
