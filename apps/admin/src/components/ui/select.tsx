import { forwardRef, type SelectHTMLAttributes } from "react";
import { cn } from "@/lib/cn";

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    options: Array<{ value: string; label: string }>;
}

/**
 * Lightweight native `<select>`. We deliberately avoid the Radix Select for
 * the settings page - native renders correctly inside the WP admin frame
 * and is fully a11y/keyboard-conformant out of the box.
 */
export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    ({ options, className, ...rest }, ref) => (
        <select
            ref={ref}
            className={cn(
                "flexa-wishlist-for-woocommerce-control",
                "fw:h-9 fw:rounded-md fw:border fw:border-slate-300 fw:bg-white fw:px-2 fw:text-sm fw:text-slate-900 fw:shadow-sm fw:transition-colors",
                "fw:focus-visible:outline-none fw:focus-visible:ring-2 fw:focus-visible:ring-brand-500 fw:focus-visible:ring-offset-1",
                "fw:disabled:cursor-not-allowed fw:disabled:opacity-60",
                className,
            )}
            {...rest}
        >
            {options.map((opt) => (
                <option key={opt.value} value={opt.value}>
                    {opt.label}
                </option>
            ))}
        </select>
    ),
);
Select.displayName = "Select";
