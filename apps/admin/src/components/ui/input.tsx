import { forwardRef, type InputHTMLAttributes } from "react";
import { cn } from "@/lib/cn";

export type InputProps = InputHTMLAttributes<HTMLInputElement>;

export const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ className, type = "text", ...props }, ref) => {
        return (
            <input
                ref={ref}
                type={type}
                className={cn(
                    "flexa-woocommerce-wishlist-control",
                    "fw:flex fw:h-9 fw:w-full fw:rounded-md fw:border fw:border-slate-300 fw:bg-white fw:px-3 fw:py-1 fw:text-sm fw:shadow-sm fw:transition-colors",
                    "fw:placeholder:text-slate-400",
                    "fw:focus-visible:outline-none fw:focus-visible:ring-2 fw:focus-visible:ring-brand-500 fw:focus-visible:ring-offset-1",
                    "fw:disabled:cursor-not-allowed fw:disabled:opacity-50",
                    className,
                )}
                {...props}
            />
        );
    },
);
Input.displayName = "Input";
