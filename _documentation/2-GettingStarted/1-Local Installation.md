---
sidebar_position: 1
---

# Local Installation


This chapter of the documentation focuses on setting up and running HAWKI 2.0 on your local system. If you prefer to create a local Docker environment follow [Local Docker Installation](2-Local Docker Installation.md)

>*Although we strongly recommend that you first test HAWKI on your local device before deploying it on the server, If you want to skip this please read the ["Deployment"](./5-Deployment/1-%20Apache%20Server.md) chapter*

---

### Initialization

1. **Download HAWKI:**

To start clone the Git Repo using:

```
git clone https://github.com/HAWK-Digital-Environments/HAWKI.git
```

or **download** the latest version files from [Releases Page](https://github.com/hawk-digital-environments/HAWKI/releases)

Ensure that you have the basic PHP modules, node and composer to run HAWKI on your machine.


><details> 
><summary>Check Pre-Requirements</summary>
>
>HAWKI 2.0 utilizes the Laravel 11 backend framework. To run HAWKI on your local machine, it is essential to ensure that all Laravel prerequisites are installed. In particular, you will need to have PHP, Composer and Node.js (including npm) installed on your system. For comprehensive setup instructions, please refer to the [laravel documentation](https://laravel.com/docs/11.x).
HAWKI also requires a database to store the messages and profile pictures. We recommend that you use a mySQL database. The use of administration tools such as phpMyAdmin can also speed up the process.
>#### Checking Requirements Using HAWKI CLI
>You can use the HAWKI CLI to check if your system meets all requirements. Navigate to project root (if already cloned) and run:
>
>```
>php hawki check
>```
>
>This command will verify:
>- PHP version (8.1+)
>- Composer
>- Node.js and npm
>- Required PHP extensions (mbstring, xml, pdo, curl, zip, json, fileinfo, openssl)
>
>If any dependencies are missing, the command will provide installation instructions.
></details>
---

2. **Install Dependencies**


Now that you have the project files on your machine, navigate to the project folder and run:

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

At this point the dependency packages should be installed. `node_modules` and `vendor` folders must be added to project.



><details> 
><summary>Tip! Continue to next steps with "-all" flag</summary>
> For a complete guided setup process use the flag "-all" to automatically proceed to next steps:
> ```
> php hawki init -all
> ```
> This will run the initialization and continue with the interactive setup process.
> </details>



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

>If you have used HAWKI CLI to initialize the project in the previous step, some attributes like Keys and Salts must have been automatically filled.
>
>Moreover, some of the attributes have already default values, which do not necessarily need be changed.

Here we go through some of the important attributes that need to be set before running HAWKI on your local machine.
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

alternatively you can host your own model using [Ollama](https://ollama.com/). Then add the following attributes in your `.env`:

```
OLLAMA_ACTIVE=true
```
then navigate to `config/model_lists/ollama_models.php` and add your model attributes in the following structure:

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
Currently, HAWKI supports LDAP, OpenID, and Shibboleth authentication services. A built-in Test User Athentication for internal testing purposes is also available.
Since normally external authentication servers are not accessible from your local machine, **set the authentication method to LDAP** and enable "test user login" in order to use test users files.

```
AUTHENTICATION_METHOD="LDAP"
TEST_USER_LOGIN="true"
```

In `/storage/app/` open the previously created `test_users.json` and add a profile in the following structure:

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


### DATABASE

To store chat data, HAWKI requires a database connection. This documentation employs MySQL, but selection depends on your usage and specific requirements.
For local development you can also use "Mamp" or "Xampp" to facilitate the process.

Create a new, empty database, for example ***HAWKI_DB***.
Update the database connection attributes in the .env file with:

```
DB_CONNECTION= mysql
DB_HOST= 127.0.0.1      // Database host IP
DB_PORT= 3306           // Database host port
DB_DATABASE= HAWKI_DB   // Database name
DB_USERNAME= root       // Database username
DB_PASSWORD= root       // Database password
```


Run database migrations by navigating to the project directory and executing:

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

---


**Create Storage Link**

To allow clients to read files from the storage folder, we need to create a symbolic link for the storage.
Use the following command to create the symbolic link:

```
php artisan storage:link
```

You should be able to see the storage shortcut inside the public folder.

>Please note that after changing the structure of your files in the storage folder you may need to recreate the virtual link:

```
sudo rm -rf public/storage
php artisan storage:link
```


## Broadcasting & Workers

HAWKI uses [Laravel Reverb](https://reverb.laravel.com/) for real-time communication between client and server for broadcasting messages in th Groupchat Rooms.

>**If you don't wish to test broadcasting on you local machine skip this step.**


In order to establish a connection to Reverb, a set of Reverb "application" credentials must be exchanged between the client and server. These credentials are configured on the server and are used to verify the request from the client. You may define these credentials using the following environment variables:

```
REVERB_APP_ID=my-app-id // replace with 
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
```

[READ THE ORIGINAL DOCUMENTAITON FOR MORE DETAIL](https://laravel.com/docs/11.x/reverb#application-credentials)

For local testing leave the rest of the Reverb variables as is:

```
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

Start Reverb using:

```
php artisan reverb:start
```

In the terminal you should see:
```
Starting server on 0.0.0.0:8080 (127.0.0.1). 
```

At this point you can stop the reverb and proceed, we will reactivate it again in the final step.

---
**Workers**

Before a message is broadcasted it must be queued and dispatched by laravel workers.
Test workers by running the following commands in separate terminals:

```
php artisan queue:work
```
```
php artisan queue:work --queue=message_broadcast
```

Laravel's built-in worker will take care of the rest.
You can either keep the workers running in separate terminals to see the printed log, or stop them here and run them with the rest of the services later.


##  File Converter 

The new Attachments feature in HAWKI allows user to upload files in the chat. But since the models mostly do not accept document files as input, we need to first convert these to text.
This task can be done either by the new converter module in HAWKI Toolkit or be the provided document endpoint from GWDG.
If you do not uncomment the FILE_CONVERTER variables in .env file, HAWKI turns off the document support for attachments automatically.

If you prefer to use HAWKI FIle Converter, and you are not using the docker deployment you can host the converter separately from the project.
For more information refer to the [File Converter Repo](https://github.com/hawk-digital-environments/hawki-toolkit-file-converter) of this documentation.


### Start Development Server

Now you can start the development server using:

```
php hawki run -dev
```

The HAWKI CLI `run -dev` command starts:
- PHP (artisan) development server
- npm development server with hot-reloading
- Reverb websocket server (if configured)
- Queue workers for various tasks
- Schedule worker for scheduled tasks

You should be able to open and use HAWKI on your localhost at this stage.

```
http://127.0.0.1:8000/
```

To stop all running processes when using HAWKI CLI:

```
php hawki stop
```

This command finds and terminates:
- PHP Artisan processes
- Node.js/npm processes
- Queue workers
- Reverb server
- Schedule worker

>**Important:** You can also use `localhost:8000` to open the web page in your browser. However, some of the communication is restricted by the address defined in the .env file.
If you wish to change this, update the `APP_URL` variable in .env.


