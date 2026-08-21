import * as DialogPrimitive from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import {
    forwardRef,
    type ComponentPropsWithoutRef,
    type ElementRef,
    type HTMLAttributes,
} from "react";
import { cn } from "@/lib/cn";
import { useEffectiveTheme } from "@/lib/theme";

export const Dialog = DialogPrimitive.Root;
export const DialogTrigger = DialogPrimitive.Trigger;
export const DialogClose = DialogPrimitive.Close;

const DialogOverlay = forwardRef<
    ElementRef<typeof DialogPrimitive.Overlay>,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Overlay
        ref={ref}
        className={cn(
            "fw:fixed fw:inset-0 fw:z-[160001] fw:bg-black/40 fw:backdrop-blur-sm fw:data-[state=open]:animate-in fw:data-[state=closed]:animate-out fw:data-[state=closed]:fade-out-0 fw:data-[state=open]:fade-in-0",
            className,
        )}
        {...props}
    />
));
DialogOverlay.displayName = DialogPrimitive.Overlay.displayName;

interface DialogContentProps
    extends ComponentPropsWithoutRef<typeof DialogPrimitive.Content> {
    overlayClassName?: string;
}

export const DialogContent = forwardRef<
    ElementRef<typeof DialogPrimitive.Content>,
    DialogContentProps
>(({ className, overlayClassName, children, ...props }, ref) => {
    // Portaled content renders outside `.flexa-wishlist-themed`; re-stamp the
    // theme so `fw:dark:*` utilities inside the dialog keep working.
    const theme = useEffectiveTheme();
    return (
        <DialogPrimitive.Portal>
            <DialogOverlay className={overlayClassName} />
            <DialogPrimitive.Content
                ref={ref}
                data-theme={theme}
                className={cn(
                    "fw:fixed fw:left-1/2 fw:top-1/2 fw:z-[160002] fw:grid fw:w-full fw:max-w-md fw:-translate-x-1/2 fw:-translate-y-1/2 fw:gap-4 fw:rounded-lg fw:border fw:border-slate-200 fw:bg-white fw:p-6 fw:shadow-xl",
                    "fw:data-[state=open]:animate-in fw:data-[state=closed]:animate-out",
                    className,
                )}
                {...props}
            >
                {children}
                <DialogPrimitive.Close className="fw:absolute fw:right-4 fw:top-4 fw:rounded-sm fw:opacity-70 fw:transition-opacity fw:hover:opacity-100 fw:focus:outline-none fw:focus:ring-2 fw:focus:ring-brand-500">
                    <X className="fw:h-4 fw:w-4" />
                    <span className="fw:sr-only">Close</span>
                </DialogPrimitive.Close>
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
});
DialogContent.displayName = DialogPrimitive.Content.displayName;

export const DialogHeader = ({ className, ...props }: HTMLAttributes<HTMLDivElement>) => (
    <div className={cn("fw:flex fw:flex-col fw:space-y-1.5 fw:text-left", className)} {...props} />
);
DialogHeader.displayName = "DialogHeader";

export const DialogFooter = ({ className, ...props }: HTMLAttributes<HTMLDivElement>) => (
    <div className={cn("fw:flex fw:justify-end fw:gap-2", className)} {...props} />
);
DialogFooter.displayName = "DialogFooter";

export const DialogTitle = forwardRef<
    ElementRef<typeof DialogPrimitive.Title>,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        className={cn("fw:text-lg fw:font-semibold fw:leading-none fw:tracking-tight", className)}
        {...props}
    />
));
DialogTitle.displayName = DialogPrimitive.Title.displayName;

export const DialogDescription = forwardRef<
    ElementRef<typeof DialogPrimitive.Description>,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Description>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Description
        ref={ref}
        className={cn("fw:text-sm fw:text-slate-500", className)}
        {...props}
    />
));
DialogDescription.displayName = DialogPrimitive.Description.displayName;
