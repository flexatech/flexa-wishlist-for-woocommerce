import * as LabelPrimitive from "@radix-ui/react-label";
import { forwardRef, type ComponentPropsWithoutRef } from "react";
import { cn } from "@/lib/cn";

export const Label = forwardRef<
    HTMLLabelElement,
    ComponentPropsWithoutRef<typeof LabelPrimitive.Root>
>(({ className, ...props }, ref) => (
    <LabelPrimitive.Root
        ref={ref}
        className={cn(
            "fw:text-sm fw:font-medium fw:leading-none fw:text-slate-800 fw:peer-disabled:cursor-not-allowed fw:peer-disabled:opacity-70",
            className,
        )}
        {...props}
    />
));
Label.displayName = "Label";
