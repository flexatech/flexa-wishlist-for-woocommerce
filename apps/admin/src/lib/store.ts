import { create } from "zustand";
import { persist, type PersistOptions } from "zustand/middleware";

export interface ToastState {
    id: number;
    message: string;
    tone: "success" | "error";
}

export type AppView = "dashboard" | "settings";

interface UiState {
    /** Which top-level view is open. Persisted so a reload lands on the same
     *  screen. */
    activeView: AppView;
    /** Which settings pane is open. Persisted so a reload lands on the same
     *  tab; everything else here is transient. */
    activeSection: string;
    /** Transient (never persisted): the active toast, or null. */
    toast: ToastState | null;
    setActiveView: (view: AppView) => void;
    setActiveSection: (id: string) => void;
    showToast: (message: string, tone?: ToastState["tone"]) => void;
    dismissToast: () => void;
}

const persistOptions: PersistOptions<
    UiState,
    Pick<UiState, "activeView" | "activeSection">
> = {
    name: "flexa-wishlist:ui",
    // Persist only the nav tabs - never the toast queue or any server data.
    partialize: (state) => ({
        activeView: state.activeView,
        activeSection: state.activeSection,
    }),
};

export const useUiStore = create<UiState>()(
    persist(
        (set) => ({
            activeView: "dashboard",
            activeSection: "general",
            toast: null,
            setActiveView: (activeView) => set({ activeView }),
            setActiveSection: (activeSection) => set({ activeSection }),
            showToast: (message, tone = "success") =>
                set({ toast: { id: Date.now(), message, tone } }),
            dismissToast: () => set({ toast: null }),
        }),
        persistOptions,
    ),
);
