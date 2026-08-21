import { type LucideIcon } from "lucide-react";
import { type ReactNode } from "react";
import { Label } from "@/components/ui/label";

interface SettingRowProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    htmlFor?: string;
    children: ReactNode;
}

/**
 * One settings row: left icon chip · center Label + description · right-aligned
 * control. Toggle rows pass a Switch; enum/number rows pass a Select/Input.
 */
export function SettingRow({
    icon: Icon,
    title,
    description,
    htmlFor,
    children,
}: SettingRowProps) {
    return (
        <div className="fw:flex fw:items-center fw:gap-3 fw:px-5 fw:py-4">
            <span className="fw:flex fw:h-10 fw:w-10 fw:shrink-0 fw:items-center fw:justify-center fw:rounded-lg fw:bg-slate-100 fw:text-slate-600">
                <Icon className="fw:h-4 fw:w-4" aria-hidden />
            </span>
            <div className="fw:min-w-0 fw:flex-1">
                <Label
                    htmlFor={htmlFor}
                    className="fw:block fw:text-sm fw:font-semibold fw:text-slate-900"
                >
                    {title}
                </Label>
                {description && (
                    <p className="fw:mt-0.5 fw:text-xs fw:text-slate-500">
                        {description}
                    </p>
                )}
            </div>
            <div className="fw:shrink-0">{children}</div>
        </div>
    );
}

export const ROW_DIVIDER = "fw:border-t fw:border-slate-100";
