import { LayoutDashboard, Settings2 } from "lucide-react";
import { cn } from "@/lib/cn";
import { __ } from "@/lib/i18n";
import { useUiStore, type AppView } from "@/lib/store";
import { DashboardPage } from "@/features/dashboard/DashboardPage";
import { SettingsPage } from "@/features/settings/SettingsPage";

const VIEWS: Array<{ id: AppView; label: string; icon: typeof LayoutDashboard }> = [
    { id: "dashboard", label: __("Dashboard"), icon: LayoutDashboard },
    { id: "settings", label: __("Settings"), icon: Settings2 },
];

/**
 * Top-level shell. Two views (Dashboard | Settings) toggled by a lightweight
 * tab bar backed by the persisted UI store - no router needed for two screens.
 */
export function App() {
    const view = useUiStore((s) => s.activeView);
    const setView = useUiStore((s) => s.setActiveView);

    return (
        <div className="fw:min-h-full fw:bg-slate-50">
            <nav className="fw:border-b fw:border-slate-200 fw:bg-white">
                <div className="fw:mx-auto fw:flex fw:max-w-6xl fw:items-center fw:gap-1 fw:px-6">
                    {VIEWS.map((v) => {
                        const Icon = v.icon;
                        const selected = view === v.id;
                        return (
                            <button
                                key={v.id}
                                type="button"
                                onClick={() => setView(v.id)}
                                aria-current={selected ? "page" : undefined}
                                className={cn(
                                    "fw:-mb-px fw:flex fw:items-center fw:gap-2 fw:border-b-2 fw:px-3 fw:py-3 fw:text-sm fw:font-medium fw:transition-colors fw:cursor-pointer",
                                    "fw:focus-visible:outline-none fw:focus-visible:ring-2 fw:focus-visible:ring-brand-500",
                                    selected
                                        ? "fw:border-brand-600 fw:text-brand-700"
                                        : "fw:border-transparent fw:text-slate-500 fw:hover:text-slate-800",
                                )}
                            >
                                <Icon className="fw:h-4 fw:w-4" aria-hidden />
                                {v.label}
                            </button>
                        );
                    })}
                </div>
            </nav>

            {view === "dashboard" ? <DashboardPage /> : <SettingsPage />}
        </div>
    );
}
