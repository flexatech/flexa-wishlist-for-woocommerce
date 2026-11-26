import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { resolve } from "node:path";
import { wpReactGlobals } from "./wp-react-globals";

const root = resolve(__dirname);
const projectRoot = resolve(__dirname, "../..");

export default defineConfig(({ mode }) => ({
    root,
    base: mode === "development" ? "/" : "",
    plugins: [wpReactGlobals(), react(), tailwindcss()],
    resolve: {
        alias: {
            "@": resolve(root, "src"),
        },
    },
    server: {
        port: 5173,
        strictPort: true,
        cors: true,
        origin: "http://localhost:5173",
        hmr: {
            host: "localhost",
            protocol: "ws",
        },
    },
    build: {
        manifest: true,
        emptyOutDir: true,
        outDir: resolve(projectRoot, "assets/dist"),
        sourcemap: mode !== "production",
        rollupOptions: {
            input: {
                main: resolve(root, "src/main.tsx"),
            },
        },
    },
}));
