// @ts-check
import {themes as prismThemes} from 'prism-react-renderer';

/** @type {import('@docusaurus/types').Config} */
const config = {
    title: 'HAWKI Docs - Learn how to HAWKI',
    tagline: 'Latest documentation',
    favicon: 'img/favicon-32x32.png',

    url: 'https://docs.hawki.info',
    baseUrl: '/',

    organizationName: 'hawk-digital-environments', // Update accordingly
    projectName: 'HAWKI', // Update with your actual project name

    onBrokenLinks: 'throw',
    onBrokenMarkdownLinks: 'warn',

    i18n: {
        defaultLocale: 'en',
        locales: ['en']
    },

    presets: [
        [
            'classic',
            /** @type {import('@docusaurus/preset-classic').Options} */
            ({
                docs: {
                    path: '../_documentation',
                    routeBasePath: '/',
                    sidebarPath: require.resolve('./sidebars.js'),
                    // The "x" is there, because the docs are stored in the parent directory, which confuses docusaurus
                    editUrl: 'https://github.com/hawk-digital-environments/HAWKI/edit/main/x/'
                },
                theme: {
                    customCss: require.resolve('./custom.css')
                }
            })
        ]
    ],

    themeConfig: /** @type {import('@docusaurus/preset-classic').ThemeConfig} */ ({
        image: 'img/HAWKI_Icon.jpg',
        navbar: {
            title: '',
            logo: {
                alt: 'HAWK Logo',
                src: '/img/hawk-logo.svg',
                srcDark: '/img/hawk-logo-dark.svg'
            },
            items: [
                {
                    href: 'https://www.hawki.info/',
                    position: 'right',
                    className: 'header-info-link',
                    title: 'Official HAWKI website',
                    'aria-label': 'Official HAWKI website'
                },
                {
                    href: 'https://github.com/hawk-digital-environments/HAWKI',
                    position: 'right',
                    className: 'header-github-link',
                    title: 'GitHub repository',
                    'aria-label': 'GitHub repository'
                },
                {
                    href: 'https://discord.gg/zzR54sRWDE',
                    position: 'right',
                    className: 'header-discord-link',
                    title: 'Join our Discord server',
                    'aria-label': 'Discord server'
                }
            ]
        },
        footer: {
            copyright: `Made with Docusaurus © ${new Date().getFullYear()}`
        },
        prism: {
            theme: prismThemes.github,
            darkTheme: prismThemes.dracula
        }
    })
};

export default config;
