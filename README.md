# HAWKI

## About

HAWKI is a didactic interface for universities based on the OpenAI API. It is not necessary for users to create an account, the university ID is sufficient for login - no user-related data is stored.

The service was developed by Jonas Trippler, Vincent Timm and Stefan Woelwer at the Interaction Design Lab at the HAWK University of Applied Sciences and Arts in order to give all members of the university the opportunity to integrate artificial intelligence into their work processes and to have a meeting place where new ways of working may emerge and an internal university discussion about the use of AI can take place. The interface is currently divided into three areas:

Conversation: A chat area similar to ChatGPT, for a quick start to any task.

Virtual office: Conversations with fictional experts as a mental model to familiarise yourself with non-technical areas and to make more targeted enquiries to real university experts.

Learning Space: The learning spaces are designed to help you understand the different support options and learn what makes an effective prompt.

We welcome constructive feedback to further develop this project based on your needs and insights.

<!-- ![HAWKI Login](/img/hawki-screenshot-login.png) -->
![HAWKI Login](/public/img/readmePic1.jpeg)
_HAWKI Login Screen_

<!-- ![HAWKI Dashboard](/img/hawki-screenshot-dashboard.jpg) -->
![HAWKI Dashboard](/public/img/readmePic3.jpeg)
_HAWKI Dashboard_

![HAWKI Dashboard](/public/img/readmePic2.jpeg)
_HAWKI Settings Panel_


## Changelog – HAWKI V1. 

### Functionality 

Shibboleth connection as an additional authentication option. (Thanks to Marvin Mundry from the University of Hamburg)

Multi-language with translated texts for English, Italian, French and Spanish.
Display of mathematical formulas, LaTex and improvement of syntax highlighting.

### Quality of Life 

Dark Mode for our night owls.

System prompts can now be viewed and edited.

In the new version each room's chatlog is saved by default and should be deleted before starting a new chat.


### Security updates

We have made HAWKI more secure in some areas and updated the code structure.

We would like to thank Thorger Jansen (discovery, analysis, coordination) from SEC Consult Vulnerability Lab for responsibly reporting the identified issues and working with us to fix them.

## Getting started

## Prequisites

### LDAP

HAWKI uses LDAP under the hood in order to authenticate users. Make sure you have LDAP setup first and that it is accessible from your HAWKI instance. Provide your LDAP config according to chapter [Configuration](#configuration). You can find more information on how to use LDAP on the official website https://ldap.com

_**Testing without LDAP:**_ You can try out HAWKI without an LDAP server. To do so, set `TESTUSER` to your prefered user name `tester` in the configuration file (see [Configuration](#configuration)) and sign in with username `tester` and password `superlangespasswort123`

### OpenID Connect

As an alternative to LDAP, OpenID connect can also be used to 
authenticate users. It requires the jumbojett/openid-connect-php
library (https://github.com/jumbojett/OpenID-Connect-PHP)
to be installed with composer.

### Shibboleth 

The new version also supports the Shibboleth for user authentication. Define your Shibboleth url and login page in the environment file (see [Configuration](#configuration)).

### Open AI Access

To generate answers HAWKI uses the Open AI api. Follow the instructions on https://platform.openai.com/docs/introduction to generate an API key and paste it in the configuration file like instructed in chapter [Configuration](#configuration).


## Configuration

To get started you need to add a configuration file to the project first. Copy the file ".env.example" from the root directory and rename it to ".env". Replace the example values in it with your own configuration. A detailed description of all values is listed below.

| Value            | Type    | Example                                | Description                                                                                                                                        |
| ---------------- | ------- | -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authentication   | string  | 'LDAP' / 'OIDC' / 'Shibboleth'                     | Authentication method: LDAP or OpenID Connect                                                                                                      |
| LDAP_HOST        | string  | "ldaps://...de"                        | The URL of your LDAP server.                                                                                                                       |
| LDAP_BIND_PW     | string  | secretpassword                         | Password of the user that is trying to bind to the LDAP Server.                                                                                    |
| LDAP_BASE_DN     | string  | "cn=...,ou=...,dc=..."                 | Distinguised name that is used to initially bind to your LDAP server.                                                                              |
| LDAP_SEARCH_DN   | string  | "ou=...,dc=..."                        | Distinguished name that is used for authenticating users.                                                                                          |
| LDAP_PORT  | string  | "..."                        | The LDAP port.                                                                                          |
| LDAP_FILTER  | string  | "..."  | LDAP Filter. Choose the filter based on your LDAP configuration. See .env.example for more details.|
| LDAP_DEFAULT_INITIALS  | string  | "ABC"  | User initials to use for every user. If not set, try to compute initials from LDAP displayname.|
| SHIBBOLET_LOGIN_PATH    | string  | "..."                                  | Path to shibboleth login page.                                                                                               |
| SHIBBOLET_LOGIN_PAGE    | string  | "..."                                  | Shibboleth login page.                                                                                               |
| OIDC_IDP          | string  | "https://...."                         | URL of the Identity provider supporting OpenID Connect.                                                                                            |
| OIDC_CLIENT_ID    | string  | "..."                                  | Client Id for this application in Identity provider.                                                                                               |
| OIDC_CLIENT_SECRET | string  | "..."                                 | Secret key for OpenID Connect. 
| OIDC_LOGOUT_URI | string  | "https://...."                                 | URL to logout from Identity provider                                                                                                                  |
| MODEL_SELECTOR_ACTIVATION   | string  | "true" | Set to true to activate dropdown. Deactivated Dropdown will force Gpt-4-0 as default model.                                                                                                                                    |
| OPENAI_API_URL   | string  | "https://api.openai.com/v1/chat/completions" | Open AI Endpoint URL                                                                                                                                    |
| OPENAI_API_KEY   | string  | sk-...                                 | Open AI Api key                                                                                                                                    |
| GWDG_API_URL   | string  | "https://api.openai.com/v1/chat/completions" | GWDG Endpoint URL                                                                                                                                    |
| GWDG_API_KEY   | string  |                                 | GWDG Api key                                                                                                                                     |
| IMPRINT_LOCATION | string  | https://your-university/imprint        | A link to your imprint. Alternatively you can replace the file index.php under /impressum with your own html/ php of your imprint.                 |
| PRIVACY_LOCATION | string  | https://your-university/privacy-policy | A link to your privacy policy. Alternatively you can replace the file index.php under /datenschutz with your own html/ php of your privacy policy. |
| TESTUSER         | string | "tester"                                | Set value for testing purposes. Leave TESTUSER and TESTPASSWORD empty or comment them out to disable test user.                    |
| TESTPASSWORD         | string | "superlangespasswort123"  | Set value for testing purposes. Leave TESTUSER and TESTPASSWORD empty or comment them out to disable test user.                           |
| FAVICON_URI  | string  | "https://...."                                 | Link to favicon |
| DEFAULT_LANGUAGE  | string  | "de_DE"/ "en_US"/ "es_ES"/ "fr_FR"/ "it_IT"               | Default website language. Only applicable if the user has not previously changed the language or their browser language is not one of the supported languages. Current supported languages: 'de_DE', 'en_US', 'es_ES', 'fr_FR', 'it_IT'  |
| CHATLOG_ENCRYPTION_SALT | string | ... | Set a strong salt specific to your application. This will be user to encrypt users' chatlogs in localstorage.|
## Web Server Configuration

There are a few things to keep in mind when publishing your HAWKI instance on a webserver.

First and foremost your webserver needs PHP support.

Also, make sure that you disable `output_buffering` in your webserver configuration otherwise you might run into issues when receiving the repsonse stream from Open AI.

If you are setting up a new server, make sure that you install the cURL library. https://www.php.net/manual/de/book.curl.php

**_IMPORTANT:_** _Keep the `.env` configuration file secret. Make sure your webserver does not allow directory listing and it blocks access to this configuration file. By default the `.env` file is located in the private folder with restricted access on apache. Double check that it can not be queried with a simple GET request via http://your-hawki-domain/private/.env_

## Branding

To swap out the HAWK logo for your own, replace the logo.svg file inside the `img` folder. Make sure to either keep the format as svg or replace all references to logo.svg with your respective filetype.

Of course, you can modify stylesheets and html files and adjust them to your liking.

## Third-Party Libraries

This project utilizes the following third-party libraries:

[KaTeX](https://github.com/KaTeX/KaTeX) - A fast, easy-to-use JavaScript library for TeX math rendering.
  - License: MIT. See [here](https://github.com/KaTeX/KaTeX/blob/master/LICENSE) for details.

[Highlight JS](https://github.com/highlightjs/highlight.js) - Syntax highlighting for the Web.
  - License: MIT. See [here](https://github.com/highlightjs/highlight.js/blob/main/LICENSE) for details.

[jQuery](https://github.com/jquery/jquery) - A fast, small, and feature-rich JavaScript library.
  - License: MIT. See [here](https://github.com/jquery/jquery/blob/main/LICENSE.txt) for details.

## Contact & License

This project is licensed under the terms of the MIT license. If you have any questions, feel free to get in touch via [Email](mailto:vincent.timm2@hawk.de)
