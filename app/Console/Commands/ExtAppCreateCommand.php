<?php

namespace App\Console\Commands;

use App\Services\ExtApp\AppCreator;
use App\Services\ExtApp\Db\AppDb;

class ExtAppCreateCommand extends AbstractExtAppCommand
{
    public function __construct(
        private readonly AppCreator $appCreator,
        private readonly AppDb      $appDb
    )
    {
        parent::__construct();
    }
    
    protected $signature = 'ext-app:create';
    
    protected $description = 'A wizard to create a new external app that provides HAWKI features in a third-party interface.';
    
    /** @noinspection BypassedUrlValidationInspection */
    public function handle(): void
    {
        $this->assertAppsAreEnabled();
        
        $this->info("You are about to create an external app that allows API access for HAWKI features in an external interface.");
        
        $name = $this->output->ask('Please enter the name of the app (e.g. "HAWKI App")', null, function ($name) {
            if (empty($name)) {
                throw new \InvalidArgumentException('The name cannot be empty.');
            }
            if ($this->appDb->findByName($name)->isNotEmpty()) {
                throw new \InvalidArgumentException('An app with this name already exists. Please choose a different name.');
            }
            return $name;
        });
        
        $appUrl = $this->output->ask('Please enter the URL of the external app. This is the URL where the app is hosted (e.g. "https://example.com") (Will be shown when a user wants to connect the app to HAWKI)', null, function ($url) {
            if (empty($url)) {
                throw new \InvalidArgumentException('The app URL cannot be empty.');
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('The app URL must be a valid URL.');
            }
            return $url;
        });
        
        $redirectUrl = $this->output->ask(
            'Please enter the redirect URL of the external app. This is the URL that HAWKI will redirect to after a user has connected the app (e.g. "https://example.com/redirect")',
            $appUrl,
            function ($url) {
                if (empty($url)) {
                    throw new \InvalidArgumentException('The redirect URL cannot be empty.');
                }
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException('The redirect URL must be a valid URL.');
                }
                return $url;
            });
        
        $description = $this->output->ask('Please enter a description for the external app. This is shown to your users when connecting the app with HAWKI (optional)', null);
        
        $logoUrl = $this->output->ask('Please enter the URL of the app logo. This will be shown when your users are connecting the app to their HAWKI profile (optional)', null);
        
        $result = $this->appCreator->create(
            name: $name,
            redirectUrl: $redirectUrl,
            url: $appUrl,
            description: $description,
            logoUrl: $logoUrl
        );
        
        $this->line('External app created successfully!');
        $this->line('Here you can find the details of the created app:');
        $this->output->section('External App Details');
        $this->line('Name: ' . $result->app->name);
        $this->line('Redirect URL: ' . $result->app->redirect_url);
        $this->line('Description: ' . ($result->app->description ?? 'No description provided'));
        $this->line('Logo URL: ' . ($result->app->logo_url ?? 'No logo provided'));
        $this->newLine();
        $this->output->section('Client Connection Details');
        $this->line('IMPORTANT: Please store the private key securely, as it is only shown once and is NOT stored in the database!');
        $this->newLine();
        $this->line('API Token: ' . $result->token->plainTextToken);
        $this->newLine();
        $this->line('Private Key: ' . $result->keypair->privateKey);
    }
}
