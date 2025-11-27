import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')

    return {
        server: {
            watch: {
                ignored: [
                    "**/dev_*_data/**",
                    "**/storage/**",
                    "**/vendor/**",
                    "**/node_modules/**",
                    "**/.git/**",
                ],
            },
            host: "0.0.0.0",
            port: 5173,
            hmr: {
                host: 'localhost',
                port: 5173,
            },
            cors: {
                origin: ['http://localhost:8000', 'http://localhost:5173'],
                credentials: true,
            },
        },
        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/app.js"],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        resolve: {
            alias: {
                vue: "vue/dist/vue.esm-bundler.js",
            },
        },
    }
});
