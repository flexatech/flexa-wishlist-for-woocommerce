import { Check, X } from "lucide-react";
import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { cn } from "@/lib/cn";
import { useUiStore } from "@/lib/store";

const AUTO_DISMISS_MS = 2500;

let activeClaim: symbol | null = null;

/**
 * The admin app can mount more than one React root in a page. A module-level
 * claim ensures only the first-mounted Toaster renders, so we don't get
 * duplicate portal'd toasts stacked on top of one another.
 */
export function Toaster() {
    const toast = useUiStore((s) => s.toast);
    const dismiss = useUiStore((s) => s.dismissToast);
    const [owns, setOwns] = useState(false);

    useEffect(() => {
        if (activeClaim !== null) {
            return;
        }
        const claim = Symbol("toaster");
        activeClaim = claim;
        setOwns(true);
        return () => {
            if (activeClaim === claim) {
                activeClaim = null;
            }
            setOwns(false);
        };
    }, []);

    useEffect(() => {
        if (!owns || !toast) {
            return;
        }
        const timer = window.setTimeout(dismiss, AUTO_DISMISS_MS);
        return () => window.clearTimeout(timer);
    }, [owns, toast, dismiss]);

    if (!owns || !toast) {
        return null;
    }

    return createPortal(
        <div
            key={toast.id}
            role="status"
            aria-live="polite"
            className="fw:pointer-events-none fw:fixed fw:left-1/2 fw:top-6 fw:z-[160003] fw:-translate-x-1/2"
        >
            <div
                className={cn(
                    "fw:pointer-events-auto fw:flex fw:items-center fw:gap-2 fw:rounded-full fw:px-4 fw:py-2 fw:text-sm fw:shadow-lg fw:ring-1",
                    toast.tone === "error"
                        ? "fw:bg-red-50 fw:text-red-700 fw:ring-red-200"
                        : "fw:bg-emerald-50 fw:text-emerald-800 fw:ring-emerald-200",
                )}
            >
                {toast.tone === "error" ? (
                    <X aria-hidden className="fw:h-4 fw:w-4" />
                ) : (
                    <Check aria-hidden className="fw:h-4 fw:w-4" />
                )}
                <span>{toast.message}</span>
            </div>
        </div>,
        document.body,
    );
}
