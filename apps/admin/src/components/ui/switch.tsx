import { forwardRef, type InputHTMLAttributes } from "react";
import { cn } from "@/lib/cn";

interface SwitchProps extends Omit<InputHTMLAttributes<HTMLInputElement>, "type"> {
    checked: boolean;
    onCheckedChange: (next: boolean) => void;
}

/**
 * Minimal CSS-only toggle - no extra Radix package needed. The hidden input
 * is what the form/keyboard interacts with; the visual is two divs that
 * follow the `peer-checked:` state. The hidden checkbox stays unmarked so its
 * peer-driven track ring keeps working (see index.css `-check` note).
 */
export const Switch = forwardRef<HTMLInputElement, SwitchProps>(
    ({ checked, onCheckedChange, className, disabled, id, ...rest }, ref) => {
        return (
            <label
                className={cn(
                    "fw:relative fw:inline-flex fw:h-5 fw:w-9 fw:cursor-pointer fw:items-center",
                    disabled && "fw:cursor-not-allowed fw:opacity-60",
                    className,
                )}
            >
                <input
                    ref={ref}
                    id={id}
                    type="checkbox"
                    role="switch"
                    checked={checked}
                    disabled={disabled}
                    onChange={(e) => onCheckedChange(e.target.checked)}
                    className="fw:peer fw:sr-only"
                    {...rest}
                />
                <span
                    aria-hidden
                    className="fw:h-5 fw:w-9 fw:rounded-full fw:bg-slate-300 fw:transition-colors fw:peer-checked:bg-brand-600 fw:peer-focus-visible:ring-2 fw:peer-focus-visible:ring-brand-500 fw:peer-focus-visible:ring-offset-2"
                />
                <span
                    aria-hidden
                    className="fw:absolute fw:left-0.5 fw:h-4 fw:w-4 fw:rounded-full fw:bg-white fw:shadow fw:transition-transform fw:peer-checked:translate-x-4"
                />
            </label>
        );
    },
);
Switch.displayName = "Switch";
