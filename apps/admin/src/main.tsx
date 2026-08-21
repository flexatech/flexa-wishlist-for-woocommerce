import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { AppProviders } from "./app/providers";
import { Toaster } from "./components/Toaster";
import { App } from "./App";
import "./styles/index.css";

const root = document.getElementById("flexa-wishlist-admin-root");
if (root) {
    createRoot(root).render(
        <StrictMode>
            <AppProviders>
                <App />
                <Toaster />
            </AppProviders>
        </StrictMode>,
    );
}
