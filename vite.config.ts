import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                google('Inter', {
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
        // No server-side rendering: the app is all behind the login (config/inertia.php)
        inertia({ ssr: false }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'resources/js/generated/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
        rules: {
            // Regola del progetto: niente any, i tipi si scrivono sempre
            'typescript/no-explicit-any': 'error',
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.ts'],
        setupFiles: ['resources/js/test/setup.ts'],
        // Docker gets 8 GB but sees every core of the Mac: one worker per core runs it out of memory
        maxWorkers: 4,
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'lang/**',
            '**/*.md',
            'resources/js/generated/**',
            // Written by `npm run catalog` with JSON.stringify: the catalogue test compares it as it is
            'resources/js/catalog/catalog.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            // Copied from Claude Design and read by `npm run tokens`, which writes the CSS as it is: no formatting
            'resources/design/**',
            'resources/css/tokens.css',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
