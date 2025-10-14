---
sidebar_position: 2
---


This chapter provides detailed instructions for deploying HAWKI on an nginx web server.

---

## Server Preparation

To prepare your server, ensure communication ports are properly configured. HAWKI deployment requires the HTTPS protocol, though testing locally or alternatives using HTTP are possible but not recommended. For local testing, refer to the ["Getting Started"](../2-GettingStarted/1-Local%20Installation.md) chapter.

For this guide, we'll assume port usage as follows:

- HTTP: Port 80
- HTTPS: Port 443


Ensure your server meets the following requirements to run Laravel/PHP applications:

1. PHP version 8.2 or higher
2. Required PHP Extensions:

```
- PHP >= 8.2
- Ctype PHP Extension
- cURL PHP Extension
- DOM PHP Extension
- Fileinfo PHP Extension
- Filter PHP Extension
- Hash PHP Extension
- Mbstring PHP Extension
- OpenSSL PHP Extension
- PCRE PHP Extension
- PDO PHP Extension
- Session PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
```

Ensure output buffering is enabled in your php.ini file by un-commenting:

```
output_buffering = 4096
or setting:
output_buffering = On
```

Additionally, verify that **Node** and **Composer** are installed and updated on your machine.
We'd recommend:
```
composer >= v2.8.x,
node >= v20.19.x
npm >= v10.8.x
```


You can use the HAWKI CLI to check if your system meets all requirements. Navigate to project root and run:

```
php hawki check
```

This command checks for:
- PHP version (8.1+)
- Composer 
- Node.js and npm
- Required PHP extensions (mbstring, xml, pdo, curl, zip, json, fileinfo, openssl)

---
## Nginx Server Configuration

Nginx requires specific configuration to work properly with Laravel applications and WebSocket connections.

1. Install Nginx if not already installed:
```js
sudo apt update
sudo apt install nginx
```


2. Create a new Nginx server block configuration:
sudo nano /etc/nginx/sites-available/hawki

3. Add the following configuration:

```js
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name your-domain.com;

    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;

    root /var/www/html/hawki-project/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    # WebSocket Proxy for Laravel Reverb
    location /app {
        proxy_pass http://localhost:8080/app;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /apps {
        proxy_pass http://localhost:8080/apps;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

This configuration creates a redirect from HTTP to HTTPS and sets up the necessary proxy rules for WebSocket connections.

4. Create a symbolic link to enable the site:
```
sudo ln -s /etc/nginx/sites-available/hawki /etc/nginx/sites-enabled/
```

5. Test the Nginx configuration:
```
sudo nginx -t
```

6. Restart Nginx:
```
sudo service nginx restart
```


---

## Project Deployment

1. Copy the HAWKI project content to the desired webserver location, typically at `/var/www/html/hawki-project`. 
This can be done via cloning the Git repository:
```
git clone https://github.com/HAWK-Digital-Environments/HAWKI.git
```

or **download** the latest version files from [Releases Page](https://github.com/hawk-digital-environments/HAWKI/releases)

2. Configure your server to use `/var/www/html/hawki-project/public` as the Document Root for port 443.

3. Now that you have the project files on your machine, navigate to the project folder and run:

   ```
   php hawki init
   ```

This command will:
- Create `.env` file from `.env.example`
- Set up required configuration files
- Install Composer dependencies
- Install npm packages
- Create storage symlinks
- Generate application keys and security salts



Alternatively, if you can install dependencies and prepare the project manually:

1. Locate the `.env.example` file in the root directory. Rename it to `.env` by removing the .example extension.
2. Navigate to `/storage/app` and rename `test_users.json.example` to `test_users.json`
3. Run:
    ```
    composer install
    npm install
    ```

4. Generate an application key:

```
php artisan key:generate
```

At this point, the project is transferred to the server, but you may encounter a Laravel error if the database connection is not configured.




---

### Configuration

To configure the environment variable open `.env` file in the root directory.
For a complete guide to environment variables please refer to [.env section]() of the documentation.

><details>
><summary>Use HAWKI CLI for step-by-step configuration</summary>
>
>You can also configure your environment variables using the HAWKI CLI interactive setup
>```
> php hawki setup
>```
>This command will guide you through configuring all aspects of your HAWKI installation. Alternatively, you can configure specific sections:
>```
> php hawki setup -g    # General settings
> php hawki setup -auth # Authentication settings
> php hawki setup -reverb # Reverb settings
>```
></details>

**General**

>If you have used HAWKI CLI to initialize the project in the previous step, some variables like Keys and Salts must have been automatically filled.
>
>Moreover, some of the variables have already default values, which do not necessarily need be changed.

Here we go through some of the important variables that need to be set before running HAWKI on your local machine.
For local testing make sure in "Global Application Settings" the following values exits:

```
APP_NAME="HAWKI2"                   //or your preferred name
APP_KEY=XXX                         // a random hash must be here
APP_URL="http://127.0.0.1:8000"     //for local dev, change the port if needed
```

**AI Connection**

In AI Connection section you can add your API Keys and URLs.
To start you need to have at least one API key to establish connection with one the AI providers (
[OpenAI](https://platform.openai.com/docs/quickstart),
[GWDG](https://docs.hpc.gwdg.de/index.html),
[Google](https://ai.google.dev/),
[OpenWebUI](https://openwebui.com/))

Add your API key in one of the following:

```
OPENAI_API_KEY=""
GWDG_API_KEY=""
GOOGLE_API_KEY=""
OPEN_WEB_UI_API_KEY=""
```

alternatively you can host your own model using [Ollama](https://ollama.com/). Then add the following variables in your `.env`:

```
OLLAMA_ACTIVE=true
```
then navigate to `config/model_lists/ollama_models.php` and add your model variables in the following structure:

```
[
    'active'=> false,
    'id' => 'model-id',
    'label' => 'Model label',
    "input"=> [
        "text",
    ],
    "output"=> [
        "text"
    ],
    'tools' => [
        'stream' => true,
        'vision' => false,
        'file_upload' => false,
    ],
],
```

>Before hosting models, make sure your system has the minimum required resources.



**Authentication**


in the .env file find AUTHENTICATION_METHOD variable.
Currently HAWKI supports LDAP, OpenID, and Shibboleth authentication services. A built-in Test User Athentication for internal testing purposes is also available.

Set the variable to one of the following:

```
- AUTHENTICATION_METHOD="LDAP"
- AUTHENTICATION_METHOD="OIDC"
- AUTHENTICATION_METHOD="Shibboleth"
```

According to your authentication method set the necessary variables. For more information refer to the documentation in .env file.

Using HAWKI CLI, you can configure authentication settings with:

```
php hawki setup -auth
```

>If you are using **LDAP** make sure the structure of the LDAP response is set up correctly on the HAWKI side. To do that first make sure the `LDAP_variables` are set correctly and in the correct order: Username, Email Address, Employee Type, Name. By default HAWKI looks for the `element zero/ variable name/ element zero` `($info[0][$ldapAttr][0])`. If for any reason the response from your LDAP server has a different structure, your can change this in `/app/Services/Auth/LdapService.php`.

**Test User**

To login using test users, set the authentication method to LDAP.
In `/storage/app/` locate `test_users.json` file and update it with your desired profiles as below:

```
[
    {
        "username": "tester",
        "password": "123",
        "name": "TheTester",
        "email": "tester@MyUni.de",
        "employeetype": "Tester",
        "avatar_id": ""
    },
    ...
]
```

---

## Database

1- If not already installed, set up a preferred database. This documentation employs MySQL, but selection depends on your usage and specific requirements.

***!!! Please ensure that your database has adequate security !!!***

2- Create a new, empty database, such as ***HAWKI_DB***.

3- Update the database connection settings in the .env file with:

```
DB_CONNECTION= mysql
DB_HOST= 127.0.0.1      #Database host IP
DB_PORT= 3306           #Database host port
DB_DATABASE= HAWKI_DB   #Database name
DB_USERNAME= root       #Database username
DB_PASSWORD= root       #Database password
```

You can also use the HAWKI CLI to configure your database settings interactively:
```
php hawki setup -db
```
This interactive command will prompt you for database connection details with sensible defaults.


4- Run database migrations by navigating to the project directory and executing:

```
php hawki migrate
```

For a fresh database (which will reset all data), use:

```
php hawki migrate --fresh
```

Alternatively, you can use artisan commands:
```
php artisan migrate
// or
php artisan migrate:fresh
```

At this stage, the database tables should be set up and operational.
You should now be able to see empty tables created on your database.


**Create Storage Link**

To allow clients to read files from the storage folder, we need to create a symbolic link for the storage.
Use the following command to create the symbolic link:

```
php artisan storage:link
```

You should be able to see the storage shortcut inside public folder.

>Please note that after changing the sturcture of your files in the storage folder you may need to recreate the virtual link:

```
sudo rm -rf public/storage
php artisan storage:link
```



**Server Salts**

For encryption purposes, HAWKI utilises individual salts for each component. Though not mandatory, unique hash keys are recommended:
If you ran `php hawki init` these variables are already populated with random hashes.


| Variable                 | Value               |
|--------------------------|---------------------|
| USERDATA_ENCRYPTION_SALT | base64:RandomHash== |
| INVITATION_SALT          | base64:RandomHash== |
| AI_CRYPTO_SALT           | base64:RandomHash== |
| PASSKEY_SALT             | base64:RandomHash== |
| BACKUP_SALT              | base64:RandomHash== |




---
##  PROJECT CONFIGURATION

Edit the .env file within the root folder. Most variables can remain at their default values unless specific adjustments are
required.

You can configure your environment using the HAWKI CLI interactive setup:

```
php hawki setup
```

This command will guide you through configuring all aspects of your HAWKI installation. Alternatively, you can configure specific sections:

```
php hawki setup -g    # General settings
php hawki setup -auth # Authentication settings
php hawki setup -reverb # Reverb settings
```

### Create Storage Link

To allow clients to read files from the storage folder, we need to create a symbolic link for the storage.
Use the following command to create the symbolic link:
```
php artisan storage:link
```

This step is automatically handled if you used `php hawki init`.

You should be able to see the storage shortcut inside public folder.

Please note that after changing the structure of your files in the storage folder you may need to recreate the virtual link:
```
sudo rm -rf public/storage
php artisan storage:link
```

### Setup Server Salts 

For encryption purposes, HAWKI utilises individual salts for each component. Though not mandatory, unique hash keys are
recommended. These can be configured during the HAWKI CLI setup process.

### Setup Authentication Methods

In the .env file find AUTHENTICATION_METHOD variable.
Currently HAWKI supports LDAP, OpenID, and Shibboleth authentication services. A built-in Test User Authentication for
internal testing purposes is also available.

Set the variable to one of the following:
```
- AUTHENTICATION_METHOD="LDAP"
- AUTHENTICATION_METHOD="OIDC"
- AUTHENTICATION_METHOD="Shibboleth"
```

According to your authentication method, set the necessary variables. For more information refer to the documentation in .env
file.

Using HAWKI CLI, you can configure authentication settings with:

```
php hawki setup -auth
```

### Add Data Protection and Imprint

Data protection and imprint notes are linked in the login page. To set your organization specific legal notes:

1. In the .env file find IMPRINT_LOCATION and add the URL to your organization imprint page.
2. Locate DataProtection Files in the /resources/language folder and add the data protection notes for each language in HTML 
format.

### Adding API Keys

Navigate to config folder. There you'll find model_providers.php.example. Rename it to model_providers.php.
Open the file and update the configuration as you need. HAWKI currently supports OpenAI, GWDG, and Google.

You can also use the HAWKI CLI to configure AI model providers interactively:

```
php hawki setup-models
```

This command will allow you to:
- Activate or deactivate AI providers
- Set API keys for each active provider
- Configure the default model
- Set models for system tasks (title generation, prompt improvement, etc.)

Alternatively, edit the configuration file manually:

```js
// The Default Model (use the id of one of model you wish)
'defaultModel' => 'gpt-4o',

// The model which generates the chat names.
'titleGenerationModel' => 'gpt-4o-mini',

'providers' =>[
    'openai' => [
        'id' => 'openai',
        'active' => true, //set to false if you want to disable the provider
        'api_key' => ' ** YOUR SECRET API KEY ** ',
        'api_url' => 'https://api.openai.com/v1/chat/completions',
        'ping_url' => 'https://api.openai.com/v1/models',
        'models' => [
            [
                'id' => 'gpt-4o',
                'label' => 'OpenAI GPT 4o',
                'streamable' => true,
            ],
            ...
        ]
    ],
    ...
]
```


### Broadcasting & Workers

HAWKI uses https://reverb.laravel.com/ for real-time communication between client and server.
In the .env file you simply need to set reverb variables:

```js
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret

REVERB configuration defaults to port 8080. Use HTTPS for secure communication:

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

However, to secure the communication between the client and the server you should use https protocol and port 443 for
websocket as well.
Set the variables to:
```js
REVERB_HOST=yourDomain.com // set your domain without any prefixes
REVERB_PORT=443
REVERB_SCHEME=https //reverb scheme must be set to https instead of http.
```
Also add the path to the SSL certificate chain and key path:
```js
SSL_CERTIFICATE="/path/to/certificate.crt"
SSL_CERTIFICATE_KEY="/path/to/private.key"
```

rebuild the node packages to update the config variables:
```
npm run build
```

Ensure Port 8080 is blocked by the server firewall to prevent direct access. The WebSocket proxy configuration added to the
Nginx server block handles the secure redirection.

---
### SERVICES

Before broadcasting messages to users, each message is queued on the server and processed by Laravel workers.
In order to automate the Reverb broadcasting and Laravel workers, we need to create system services:

1. Create a new file for Reverb at `/etc/systemd/system/reverb.service`. Insert this content:

>**Don't forget to update paths: `/var/www/html/hawki-project`**

```
[Unit]
Description=Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/hawki-project
ExecStart=/usr/bin/php /var/www/html/hawki-project/artisan reverb:start
Restart=always
TimeoutSec=300
LimitNOFILE=4096

[Install]
WantedBy=multi-user.target
```

2. Create a new file for queue workers and call it "queue-worker.service". Insert this content:

>**Don't forget to update paths: `/var/www/html/hawki-project`**

```
[Unit]
Description=Laravel Worker Service
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/hawki-project
ExecStart=/usr/bin/php /var/www/html/hawki-project/artisan queue:work --queue=default,mails,message_broadcast --tries=3 --timeout=90

Restart=always
RestartSec=5
TimeoutSec=300
LimitNOFILE=4096

ExecStartPost=/usr/bin/php /var/www/html/hawki-project/artisan queue:restart

StandardOutput=append:/var/www/html/hawki-project/storage/logs/worker.log
StandardError=append:/var/www/html/hawki-project/storage/logs/worker-error.log

[Install]
WantedBy=multi-user.target

```

3. Create a new file for schedule worker and call it "schedule-worker.service". Insert this content:

>**Don't forget to update paths: `/var/www/html/hawki-project`**

```
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/hawki-project
ExecStart=/usr/bin/php /var/www/html/hawki-project/artisan schedule:work
Restart=always
TimeoutSec=300
LimitNOFILE=4096

[Install]
WantedBy=multi-user.target
```

4- Reload Systemd Manager Configuration:
```
sudo systemctl daemon-reload
```

5- Enable services:

```
sudo systemctl enable reverb.service
sudo systemctl enable queue-worker.service
sudo systemctl enable schedule-worker.service
```

6- Start the Services:

```
sudo systemctl start reverb.service
sudo systemctl start queue-worker.service
sudo systemctl start schedule-worker.service
```

7-Check Status (to ensure they started correctly):

```
sudo systemctl status reverb.service
sudo systemctl status queue-worker.service
sudo systemctl status schedule-worker.service
```

##  File Converter

The new Attachments feature in HAWKI allows user to upload files in the chat. But since the models mostly do not accept document files as input, we need to first convert these to text.
This task can be done either by the new converter module in HAWKI Toolkit or be the provided document endpoint from GWDG.
If you do not uncomment the FILE_CONVERTER variables in .env file, HAWKI turns off the document support for attachments automatically.

If you prefer to use HAWKI FIle Converter, and you are not using the docker you can host the converter separately from the project.
For more information refer to the [File Converter Repo](https://github.com/hawk-digital-environments/hawki-toolkit-file-converter) of this documentation.


---

Now that workers are running the queued messages should be successfully broadcasted to the users.

You can also use the HAWKI CLI to clear all Laravel caches (which may be necessary after configuration changes):

```
php hawki clear-cache
```

This clears:
- Configuration cache
- Application cache
- View cache
- Route cache
- Event cache
- Compiled files

---
## FAQs

<details>
<summary> 1. Updates are not applied on the production server.</summary>

By default, laravel caches every configuration in the project. Don't forget to clear the cached data after each adjustment.
 *for example by using:*

 ```
 php hawki clear-cache
 ```
</details>
---

<details>
<summary>2. Styles or Javascript updates are not applied</summary>

If the styles in browser seem incorrect or the changes are not visible on a live server, the issue may be due to the cached style or scripts in your browser.
Try to hard reload or empty browser cache and reload.
Some changes in javascript may be also cached in view caches. Use `php artisan view:clear` to clear the cache.
Or simply use 
```
php hawki clear-cache
```
</details>
---

<details>
<summary>3. Failed to fetch Server Salt</summary>

Clear Config and cache:
```
php hawki clear-cache
```

</details>
---

<details>
<summary>4. Vite Packages are not loaded. (md is not defined)</summary>

Make sure node packages are built `npm run build`.
If you were previously using the dev server, locate and remove "hot" file in the public folder.

</details>
---

<details>
<summary>5.Database is created but throws error when trying to migrate</summary>

- double check your username and password.
- make sure the database name and the .env variable are identical and there are no typos.
</details>
---


<details>
<summary>6. Login page is accessible in browser but other routes are not responding</summary>

If your server is rendering the login page correctly but other functions (like login) are not working, there may be a problem reading the nginx configuration for your Laravel routes.
To make sure, that this is the case, first you can try to test another function. for example by changing the language from the settings panel. If the language is also not being changed, the problem is in fact the routing system and nginx configuration not being set up correctly for Laravel.
Follow these steps:

Make sure your nginx server block has the correct try_files directive for Laravel:
```
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Also verify that PHP-FPM is configured correctly and the location block for PHP files is present:
```
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
}
```

After making changes, reload nginx:

```
sudo systemctl reload nginx
```

Check nginx error logs if issues persist:
```
sudo tail -f /var/log/nginx/error.log
```

This should solve the issue. But you can also check file and directory permissions.
The nginx user (usually www-data) should have proper access to your Laravel directory.
```
ls -la /var/www/html/hawki-project/
```
Permissions should be something like:
```
drwxr-xr-x  12 www-data www-data  4096 ...
```

</details>
---
