---
sidebar_position: 1
---


This chapter provides detailed instructions for deploying HAWKI on an apache web server.

---

### Server Preparation

To prepare your server, ensure communication ports are properly configured. HAWKI deployment requires the HTTPS protocol, though testing locally or alternatives using HTTP are possible but not recommended.

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

Additionally, verify that **Node** and **Composer** are installed on your machine.


---

### Project Deployment Steps


1. Copy the HAWKI project content to the desired webserver location, typically `/var/www/html/hawki-project`. This can be done via cloning the git repository or manually uploading files.


2. Configure your server to use `/var/www/html/hawki-project/public` as the Document Root for port 443.

```
DocumentRoot /var/www/html/hawki-project/public
```

- Optionally, redirect port 80 traffic to HTTPS:
```
Redirect permanent / https://yourDomain.com/
```



3. Install dependency packages by navigating to the project root and executing:

```
composer install
npm install
npm run build
```

4. Generate an application key:

```
php artisan key:generate
```

At this point, the project is transferred to the server, but you may encounter a Laravel error if the database connection is not configured.



---

### DATABASE

1- If not already installed, set up a preferred database. This documentation employs MySQL, but selection depends on your usage and specific requirements.

***!!! Please make sure that your database has proper security !!!***

2- Create a new, empty database, such as ***HAWKI_DB***.

3- Update the database connection settings in the .env file with:

```
DB_CONNECTION= mysql
DB_HOST= 127.0.0.1 #Database host IP
DB_PORT= 3306 #Database host port
DB_DATABASE= HAWKI_DB #Database name
DB_USERNAME= root #Database username
DB_PASSWORD= root #Database password
```

4-Run database migrations and seed data by navigating to the project directory and executing:

```
php artisan migrate
php artisan db:seed
```

At this stage, the database tables should be set up and operational.


___
***IMPORTANT:***
If instructions are not strictly followed, please do not forget to seed the databse before allowing any other users to join HAWKI. This will ensure that the AI Agent (HAWKI) is registered as a user on the database.
If you want start a fresh database run:

```
php artisan migrate:fresh
```
___



### PROJECT CONFIGURATION

Edit the .env file within the root folder. Most variables can remain at their default values unless specific adjustments are required.

>*For comprehensive information on .env variables, refer to the dot env section of this documentation.*


**Setup Authentication Methods**

in the .env file find AUTHENTICATION_METHOD variable.
Currently HAWKI supports LDAP, OpenID, and Shibboleth authentication services. A built-in Test User Athentication for internal testing purposes is also available.

Set the variable to one of the following:

```
- AUTHENTICATION_METHOD="LDAP"
- AUTHENTICATION_METHOD="OIDC"
- AUTHENTICATION_METHOD="Shibboleth"
```

according to your authentication method set the necessary variables. For more information refer to the documentation in .env file.

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


**Create Storage Link**

To allow clients too read files from the storage folder, we need to create a symbolic link for the storage.
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



**Setup Server Salts**

For encryption purposes, HAWKI utilises individual salts for each component. Though not mandatory, unique hash keys are recommended:

|Variable| Value |
|---------|-------|
|USERDATA_ENCRYPTION_SALT  | base64:RandomHash== |
|INVITATION_SALT  | base64:RandomHash== |
|AI_CRYPTO_SALT  | base64:RandomHash== |
|PASSKEY_SALT  | base64:RandomHash== |
|BACKUP_SALT  | base64:RandomHash== |



**Adding API Keys**

Navigate to config folder. There you'll find `model_providers.php.example`. Also rename it to `model_providers.php`.
Open the file and update the configuration as you need. HAWKI currently supports OpenAI, GWDG, and Google.

```
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




**Broadcasting & Workers**

HAWKI uses [Laravel Reverb](https://reverb.laravel.com/) for real-time communication between client and server.
In the .env file you simply need to set reverb variables:

```
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
```

REVERB configuration defaults to port 8080. Use HTTPS for secure communication:

```
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

However, to secure the communication between the client and the server you should use https protocol and port 443 for websocket as well.
Set the variables to:

```
REVERB_HOST=yourDomain.com // set your domain without any prefixes
REVERB_PORT=443
REVERB_SCHEME=https //reverb scheme must be set to https instead of http.
```

also add the path the SSL certificate chain and key path:

```
SSL_CERTIFICATE=""
SSL_CERTIFICATE_KEY=""
```

Ensure Port 8080 is blocked by the server firewall. Establish a reverse proxy for communication redirection.
first make sure the proxy modules are activated:

```
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod proxy_wstunnel
```

open the configuration file, normally located at: `/etc/apache2/sites-available/hawki-ssl.com.conf`.
Add the following to the configuration:
```
# Specific WebSocket Proxy
ProxyPass /app ws://localhost:8080/app
ProxyPassReverse /app ws://localhost:8080/app

ProxyPass /apps ws://localhost:8080/apps
ProxyPassReverse /apps ws://localhost:8080/apps
```

restart apache server:

```
sudo service apache2 restart
```

At this step if you run
```
php artisan reverb:start
```

With php artisan reverb:start, clients can connect to Reverb-created group chat channels.
Preparing workers to broadcast messages follows.

---

### SERVICES

Before broadcasting messages to users, each message is queued on the server and laravel workers.
In order to automate the reverb broadcasting and laravel workers we need to create extra services in your linux server.
If Reverb is already running from the previous step, stop it before proceeding.

1. Navigate to `/etc/systemd/system`. You will find the list of linux services.

2. Create a new file for reverb and call it reverb.service. Insert this content:

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

3. Create a new file for workers and call it "laravel-worker.service". Insert this content:

```
[Unit]
Description=Laravel Worker Service
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/hawki-project
ExecStart=/usr/bin/php /var/www/html/hawki-project/artisan queue:work --queue=default,mails,message_broadcast --sle>
Restart=always
TimeoutSec=300
LimitNOFILE=4096

[Install]
WantedBy=multi-user.target

```


4- Reload Systemd Manager Configuration

```
sudo systemctl daemon-reload
```

5- Enable services:

```
sudo systemctl enable reverb.service
sudo systemctl enable laravel-worker.service
```

6- Start the Services:

```
sudo systemctl start reverb.service
sudo systemctl start laravel-worker.service
```

7-Check Status (to ensure they started correctly):

```
sudo systemctl status reverb.service
sudo systemctl status laravel-worker.service
```

Now that workers are running the queued messages should be successfully broadcasted to the users.

---

### FAQs

>**1. Config updates are not applied to the project.**

By default laravel caches every configuration in the project. Don't forget to clear the cached data after each adjustment.
*for example by using:*

```
php artisan config:clear
php aritsan cache:clear
php artisan route:clear
php artisan view:clear
```


>**2. Styles or Javascript updates are not applied**

If the styles in browser seem incorrect or the changes are not visible on a live server, the issue may be due to the cached style or scripts in your browser.
Try to hard reload or empty browser cache and reload.
Some changes in javascript may be also cached in view caches. Use `php artisan view:clear` to clear the cache.


>**3. "Failed to fetch Server Salt"**

Clear Config and cache:
```
php artisan config:clear
php artisan cache:clear
```


>**4. Vite Packages are not loaded. (md is not defined)**

Make sure node packages are built `npm run build`.
If the problem perists, locate and remove "hot" file in the public folder.