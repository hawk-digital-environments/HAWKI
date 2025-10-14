import {select} from '@inquirer/prompts';
import type {AddonConfig} from '@/loadAddons.ts';
import {base64Encode, createDeterministicRandomString} from '@/util.ts';

export const defineEnv: AddonConfig['env'] = async (definition, envFile) => {
    const isInstalled = envFile.get('DOCKER_PROJECT_INSTALLED') === 'true';
    definition
        .define('APP_KEY', {
            help: 'Laravel Encryption key: 32 random characters',
            default: createDeterministicRandomString(32, 'APP_KEY')
        })
        .define('AUTHENTICATION_METHOD', {
            help: 'The authentication method to use. For a simple test setup simply keep "LDAP", as we enable the test user login.',
            default: 'LDAP',
            editor: async () => await select({
                message: 'Select the authentication method to use',
                choices: [
                    {name: 'LDAP', value: 'LDAP'},
                    {name: 'Shibboleth', value: 'Shibboleth'},
                    {name: 'OpenID Connect', value: 'OIDC'}
                ],
                default: 'LDAP'
            })
        })
        .define('TEST_USER_LOGIN', {
            help: 'If set to true, it is possible to login with the test user "tester" and password "tester".',
            default: 'true'
        })
        .define('APP_URL', {
            help: 'The URL of your application. This is used for generating links and redirects.',
            default: isInstalled ? 'https://' + envFile.get('DOCKER_PROJECT_DOMAIN') : 'http://localhost'
        })
        .define('DB_HOST', {
            help: 'The host of your database server. "mysql" is the name of the provided docker-compose service.',
            default: 'mysql'
        })
        .define('DB_PORT', {
            help: 'The port of your database server.',
            default: '3306'
        })
        .define('DB_DATABASE', {
            help: 'The name of the database to use.',
            default: 'db'
        })
        .define('DB_USERNAME', {
            help: 'The username to use for the database connection.',
            default: 'user'
        })
        .define('DB_PASSWORD', {
            help: 'The password to use for the database connection.',
            default: 'password'
        })
        .define('REVERB_APP_SECRET', {
            help: 'The secret key to use for the reverb app. This is used to authenticate with the reverb API.',
            default: createDeterministicRandomString(32, 'REVERB_APP_SECRET')
        })
        .define('REVERB_APP_ID', {
            help: 'The app id to use for the reverb app. This is used to authenticate with the reverb API.',
            default: 'hawki'
        })
        .define('REVERB_APP_KEY', {
            help: 'The app key to use for the reverb app. Should be the same as the app id.',
            default: 'hawki2'
        })
        .define('VITE_REVERB_HOST', {
            help: 'The host of the reverb app.',
            default: isInstalled ? envFile.get('DOCKER_PROJECT_DOMAIN') : 'localhost'
        })
        .define('VITE_REVERB_PORT', {
            help: 'The port of the reverb app.',
            default: isInstalled ? '443' : '80'
        })
        .define('MAIL_MAILER', {
            help: 'The mailer to use. By default, we configure the SMTP mailer to run against the mailhog service.',
            default: 'sendmail'
        })
        .define('MAIL_SENDMAIL_PATH', {
            help: 'The path to the sendmail binary. This is used to send emails.',
            default: '/usr/bin/mhsendmail --from=test@example.org --smtp-addr=mailhog:1025 -t'
        })
        .define('CACHE_STORE', {
            help: 'The cache store to use. By default, we configure the cache to run against the redis service.',
            default: 'redis'
        })
        .define('REDIS_HOST', {
            help: 'The host of the redis server. "redis" is the name of the provided docker-compose service.',
            default: 'redis'
        })
        .define('REDIS_PORT', {
            help: 'The port of the redis server.',
            default: '6379'
        })
        .define('REDIS_USERNAME', {
            help: 'The username to use for the redis connection.',
            default: 'default'
        })
        .define('REDIS_PASSWORD', {
            help: 'The password to use for the redis connection.',
            default: 'password'
        })
        .define('USERDATA_ENCRYPTION_SALT', {
            help: 'The salt to use for the userdata encryption. This is used to encrypt the userdata.',
            default: base64Encode(createDeterministicRandomString(128, 'USERDATA_ENCRYPTION_SALT'))
        })
        .define('INVITATION_SALT', {
            help: 'The salt to use for the invitation encryption. This is used to encrypt the invitations.',
            default: base64Encode(createDeterministicRandomString(128, 'INVITATION_SALT'))
        })
        .define('AI_CRYPTO_SALT', {
            help: 'The salt to use for the AI crypto encryption. This is used to encrypt the AI crypto data.',
            default: base64Encode(createDeterministicRandomString(128, 'AI_CRYPTO_SALT'))
        })
        .define('PASSKEY_SALT', {
            help: 'The salt to use for the passkey encryption. This is used to encrypt the passkeys.',
            default: base64Encode(createDeterministicRandomString(128, 'PASSKEY_SALT'))
        })
        .define('BACKUP_SALT', {
            help: 'The salt to use for the backup encryption. This is used to encrypt the backups.',
            default: base64Encode(createDeterministicRandomString(128, 'BACKUP_SALT'))
        })
        // @todo store this as json
        .define('FILE_CONVERTER', {
            help: 'The file converter to use. By default, we use the built-in HAWKI file converter service.',
            default: 'hawki_converter'
        })
        .define('HAWKI_FILE_CONVERTER_API_URL', {
            help: 'The URL to use for the HAWKI file converter service.',
            default: 'http://file-converter:8001/extract'
        })
        .define('HAWKI_FILE_CONVERTER_API_KEY', {
            help: 'The API key to use for the HAWKI file converter service.',
            default: createDeterministicRandomString(32, 'HAWKI_FILE_CONVERTER_API_KEY')
        });
};
