import { AlertTriangle } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useUiStore } from "@/lib/store";
import { __ } from "@/lib/i18n";
import { useResetAllData } from "./useSettings";

const CONFIRM_PHRASE = "reset flexa wishlist";

export function DangerZone() {
    const [open, setOpen] = useState(false);
    const [confirm, setConfirm] = useState("");
    const reset = useResetAllData();
    const showToast = useUiStore((s) => s.showToast);

    const close = () => {
        if (reset.isPending) {
            return;
        }
        setOpen(false);
        setConfirm("");
        reset.reset();
    };

    const submit = () => {
        if (confirm.trim().toLowerCase() !== CONFIRM_PHRASE) {
            return;
        }
        reset.mutate(CONFIRM_PHRASE, {
            onSuccess: () => {
                setOpen(false);
                setConfirm("");
                showToast(__("All wishlist data and settings were reset."));
            },
            onError: () => {
                showToast(__("Reset failed."), "error");
            },
        });
    };

    return (
        <section className="fw:space-y-4 fw:rounded-lg fw:border fw:border-red-200 fw:bg-red-50/50 fw:p-5">
            <header className="fw:flex fw:items-start fw:gap-3">
                <AlertTriangle
                    className="fw:mt-0.5 fw:h-5 fw:w-5 fw:text-red-600"
                    aria-hidden
                />
                <div>
                    <h2 className="fw:text-base fw:font-semibold fw:text-red-900">
                        {__("Danger zone")}
                    </h2>
                    <p className="fw:text-sm fw:text-red-800">
                        {__(
                            "Delete every wishlist, list, and saved item, and restore all settings to their defaults. This cannot be undone.",
                        )}
                    </p>
                </div>
            </header>

            <Button variant="destructive" onClick={() => setOpen(true)}>
                {__("Reset everything…")}
            </Button>

            <Dialog open={open} onOpenChange={(o) => (o ? setOpen(true) : close())}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{__("Reset Flexa Wishlist?")}</DialogTitle>
                        <DialogDescription>
                            {__(
                                "This deletes all wishlists, lists, and saved items, and wipes every setting back to its default. This cannot be undone.",
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="fw:space-y-1.5">
                        <Label htmlFor="flexa-wishlist-reset-confirm">
                            {__("Type")}{" "}
                            <code className="fw:font-mono fw:text-red-700">
                                {CONFIRM_PHRASE}
                            </code>{" "}
                            {__("to confirm")}
                        </Label>
                        <Input
                            id="flexa-wishlist-reset-confirm"
                            value={confirm}
                            onChange={(e) => setConfirm(e.target.value)}
                            autoComplete="off"
                            autoFocus
                        />
                        {reset.isError && (
                            <p className="fw:text-sm fw:text-red-700">
                                {(reset.error as Error).message}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={close}
                            disabled={reset.isPending}
                        >
                            {__("Cancel")}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submit}
                            disabled={
                                reset.isPending ||
                                confirm.trim().toLowerCase() !== CONFIRM_PHRASE
                            }
                        >
                            {reset.isPending
                                ? __("Resetting…")
                                : __("Reset everything")}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
