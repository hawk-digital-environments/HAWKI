
### Pre Requirements

HAWKI 2.0 utilizes the Laravel 11 backend framework. To run HAWKI on your local machine, it is essential to ensure that all Laravel prerequisites are installed. Specifically, you need PHP, Composer, and Node.js (including npm) on your system. For comprehensive setup instructions, please refer to the [laravel documentation](https://laravel.com/docs/11.x).
Moreover, HAWKI requires a database to store the messaegs. We suggest that you use a mySQL database. Also having administration tools such as phpMyAdmin can speed up the process.

---
### Initialization

1. **Clone the Git Repo:**

```
git clone https://github.com/HAWK-Digital-Environments/HAWKI.git
```

2. **Install Dependencies**
Navigate to the project folder and run:

```
composer install
npm install
```


---
### Configuration

Find the `.env.example` file in the root directory. Rename it to `.env` by removing the .example extension.

>*For comprehensive information on .env variables, refer to the dot env section of this documentation.*


**Authentication**

in the .env file find AUTHENTICATION_METHOD variable.
Currently HAWKI supports LDAP, OpenID, and Shibboleth authentication services. A built-in Test User Athentication for internal testing purposes is also available. 
Since normally external authentication servers are not accessible from your local machine, **set the authentication method to LDAP** to use test users.

In `/storage/app/` create a json file and name it `test_users.json` and add your desired profiles in the json file as below:

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



**API KEYS**

Navigate to config folder. There you'll find `model_providers.php.example`. Also rename it to `model_providers.php`.
Open the file and update the configuration as you need. HAWKI currently supports OpenAI, GWDG, and Google.


```
    /*
    |--------------------------------------------------------------------------
    |   Default AI Model
    |--------------------------------------------------------------------------
    |   This Model is used by default before the user choses their
    |   desired model.
    */
    
    'defaultModel' => 'gpt-4o',

    /*
    |--------------------------------------------------------------------------
    |   System Models
    |--------------------------------------------------------------------------
    |
    |   The system models are responsible for different automated processes
    |   such as title generation and prompt improvement.
    |   Add your desired models id. Make sure that the model is included and 
    |   active in the providers list below.
    |
    */
    
    'system_models' => [
        'title_generator' => 'gpt-4o-mini',
        'prompt_improver' => 'gpt-4o-mini',
        'summarizer' => 'gpt-4o-mini',
    ],

    /*
    |--------------------------------------------------------------------------
    |   Model Providers
    |--------------------------------------------------------------------------
    |
    |   List of model providers available on HAWKI. Add your API Key and 
    |   activate the providers.
    |   To include other providers in this list please refer to the
    |   documentation of HAWKI
    |
    */

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

---
### Start Server

1. To start the server use:
```
php artisan serve
```
>Of course if you are using tools like Xammp or Mamp (on windows) to run apache, an nginx server, or any other methods you don't need to run this command. However, running artisan serve creates a user friendly terminal which helps the debug process.

2. To start the node server use:

```
npm run dev
```

You should be able to open and use HAWKI on your localhost at this stage.

```
http://127.0.0.1:8000/
```

>**Important:** You can also use `localhost:8000` to open the web page in your browser. However, some of the communication is resticted by the address defined in the .env file.
If you wish to change this, update the `APP_URL` variable in .env.



---
## Broadcasting & Workers

HAWKI uses [Laravel Reverb](https://reverb.laravel.com/) for real-time communication between client and server.


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

With php artisan reverb:start, clients can connect to Reverb-created group chat channels.



---
**Workers**

Before a message is broadcasted it must be queued and dispatched by laravel workers.

To start workers run the following commands in separate terminals:

```
php artisan queue:work
```
```
php artisan queue:work --queue=message_broadcast
```

Or simply use:
```
bash run_dev.sh start
```
