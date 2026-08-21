import { cva, type VariantProps } from "class-variance-authority";
import { forwardRef, type ButtonHTMLAttributes } from "react";
import { Slot } from "@radix-ui/react-slot";
import { cn } from "@/lib/cn";

const buttonVariants = cva(
    "fw:inline-flex fw:cursor-pointer fw:items-center fw:justify-center fw:gap-2 fw:whitespace-nowrap fw:rounded-md fw:text-sm fw:font-medium fw:transition-colors fw:focus-visible:outline-none fw:focus-visible:ring-2 fw:focus-visible:ring-offset-2 fw:disabled:pointer-events-none fw:disabled:opacity-50",
    {
        variants: {
            variant: {
                default:
                    "fw:bg-brand-600 fw:text-white fw:hover:bg-brand-700 fw:focus-visible:ring-brand-500",
                ghost: "fw:bg-transparent fw:hover:bg-slate-100 fw:text-slate-900",
                outline:
                    "fw:border fw:border-slate-300 fw:bg-white fw:hover:bg-slate-50 fw:text-slate-900",
                destructive:
                    "fw:bg-red-600 fw:text-white fw:hover:bg-red-700 fw:focus-visible:ring-red-500",
            },
            size: {
                default: "fw:h-9 fw:px-4 fw:py-2",
                sm: "fw:h-8 fw:rounded-md fw:px-3 fw:text-xs",
                lg: "fw:h-10 fw:rounded-md fw:px-6",
                icon: "fw:h-9 fw:w-9",
            },
        },
        defaultVariants: { variant: "default", size: "default" },
    },
);

export interface ButtonProps
    extends ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, asChild, ...props }, ref) => {
        const Comp = asChild ? Slot : "button";
        return (
            <Comp
                ref={ref}
                className={cn(buttonVariants({ variant, size }), className)}
                {...props}
            />
        );
    },
);
Button.displayName = "Button";

export { buttonVariants };
