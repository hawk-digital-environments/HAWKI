/** Existing runtime settings, grouped like the system administration screens. */
export const settingsTabs = [
    {
        key: 'system',
        groups: [
            { key: 'base', settings: ['APP_NAME'] },
            { key: 'system', settings: ['APP_URL', 'APP_ENV', 'APP_TIMEZONE', 'APP_LOCALE'] },
            { key: 'links', settings: ['ACCESSIBILITY_STATEMENT_URL'] }
        ]
    },
    {
        key: 'authentication',
        groups: [
            {
                key: 'passkey',
                settings: [
                    'APP_SECURITY_PASSKEY_AUTO_GENERATE',
                    'APP_SECURITY_PASSKEY_ALLOW_PASTE',
                    'APP_SECURITY_PASSKEY_CHAR_LIMITATION'
                ]
            },
            { key: 'authentication', settings: ['AUTHENTICATION_METHOD'] },
            { key: 'session', settings: ['SESSION_LIFETIME', 'SESSION_EXPIRE_ON_CLOSE', 'SESSION_ENCRYPT'] }
        ]
    },
    {
        key: 'features',
        groups: [
            { key: 'features', settings: ['AI_MENTION_HANDLE', 'CHECK_TOOL_STATUS'] },
            { key: 'api', settings: ['ALLOW_EXTERNAL_COMMUNICATION', 'ALLOW_USER_TOKEN_CREATION'] },
            {
                key: 'apps',
                settings: [
                    'ALLOW_EXTERNAL_APPS',
                    'ALLOW_EXTERNAL_APPS_GROUPS_AI',
                    'ALLOW_EXTERNAL_APPS_CONNECT_REQUEST_TIMEOUT'
                ]
            }
        ]
    },
    {
        key: 'performance',
        groups: [
            {
                key: 'uploads',
                settings: [
                    'MAX_FILE_SIZE',
                    'MAX_AVATAR_FILE_SIZE',
                    'MAX_ATTACHMENT_FILES',
                    'ALLOWED_FILE_MIME_TYPES',
                    'ALLOWED_AVATAR_MIME_TYPES'
                ]
            },
            { key: 'retention', settings: ['REMOVE_FILES_AFTER_MONTHS'] }
        ]
    }
];
