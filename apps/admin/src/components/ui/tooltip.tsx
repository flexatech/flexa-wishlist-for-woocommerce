import * as TooltipPrimitive from "@radix-ui/react-tooltip";
import {
    forwardRef,
    type ComponentPropsWithoutRef,
    type ElementRef,
} from "react";
import { cn } from "@/lib/cn";
import { useEffectiveTheme } from "@/lib/theme";

export const TooltipProvider = TooltipPrimitive.Provider;
export const Tooltip = TooltipPrimitive.Root;
export const TooltipTrigger = TooltipPrimitive.Trigger;

export const TooltipContent = forwardRef<
    ElementRef<typeof TooltipPrimitive.Content>,
    ComponentPropsWithoutRef<typeof TooltipPrimitive.Content>
>(({ className, sideOffset = 4, ...props }, ref) => {
    // Portaled content renders outside `.flexa-wishlist-themed`; re-stamp the
    // theme so `fw:dark:*` utilities inside the tooltip keep working.
    const theme = useEffectiveTheme();
    return (
        <TooltipPrimitive.Portal>
            <TooltipPrimitive.Content
                ref={ref}
                data-theme={theme}
                sideOffset={sideOffset}
                className={cn(
                    "fw:z-[160003] fw:overflow-hidden fw:rounded-md fw:bg-slate-900 fw:px-2.5 fw:py-1.5 fw:text-xs fw:font-medium fw:text-slate-50 fw:shadow-md fw:animate-in fw:fade-in-0 fw:zoom-in-95 fw:data-[state=closed]:animate-out fw:data-[state=closed]:fade-out-0 fw:data-[state=closed]:zoom-out-95 fw:data-[side=bottom]:slide-in-from-top-1 fw:data-[side=left]:slide-in-from-right-1 fw:data-[side=right]:slide-in-from-left-1 fw:data-[side=top]:slide-in-from-bottom-1",
                    className,
                )}
                {...props}
            />
        </TooltipPrimitive.Portal>
    );
});
TooltipContent.displayName = TooltipPrimitive.Content.displayName;
