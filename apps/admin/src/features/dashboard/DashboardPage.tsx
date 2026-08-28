import {
    Heart,
    Layers,
    ListChecks,
    Package,
    Users,
    type LucideIcon,
} from "lucide-react";
import { __, sprintf } from "@/lib/i18n";
import { useDashboard } from "./useDashboard";

export function DashboardPage() {
    const dashboard = useDashboard();

    return (
        <div className="fw:min-h-full fw:bg-slate-50">
            {/* Brand strip */}
            <div className="fw:border-b fw:border-slate-200 fw:bg-white">
                <div className="fw:mx-auto fw:flex fw:max-w-6xl fw:items-center fw:gap-4 fw:px-6 fw:py-3">
                    <span className="fw:flex fw:h-10 fw:w-10 fw:shrink-0 fw:items-center fw:justify-center fw:rounded-lg fw:bg-brand-500 fw:text-white fw:shadow-sm">
                        <Heart className="fw:h-5 fw:w-5" aria-hidden />
                    </span>
                    <div className="fw:leading-tight">
                        <div className="fw:text-sm fw:font-semibold fw:text-slate-900">
                            {__("Flexa Wishlist")}
                        </div>
                        <div className="fw:text-xs fw:text-slate-500">
                            {__("for WooCommerce")}
                        </div>
                    </div>
                </div>
            </div>

            {/* Page header */}
            <div className="fw:mx-auto fw:max-w-6xl fw:px-6 fw:pt-8 fw:pb-6">
                <div className="fw:space-y-1">
                    <h1 className="fw:text-3xl fw:font-bold fw:text-slate-900">
                        {__("Dashboard")}
                    </h1>
                    <p className="fw:text-sm fw:text-slate-600">
                        {__(
                            "A quick pulse on how shoppers are using their wishlists.",
                        )}
                    </p>
                </div>
            </div>

            <div className="fw:mx-auto fw:max-w-6xl fw:space-y-6 fw:px-6 fw:pb-12">
                {dashboard.isLoading && (
                    <div className="fw:rounded-xl fw:border fw:border-slate-200 fw:bg-white fw:p-6 fw:text-sm fw:text-slate-500">
                        {__("Loading dashboard…")}
                    </div>
                )}

                {dashboard.isError && (
                    <div className="fw:rounded-md fw:bg-red-50 fw:p-4 fw:text-sm fw:text-red-700">
                        {__("Failed to load the dashboard:")}{" "}
                        {(dashboard.error as Error).message}
                    </div>
                )}

                {dashboard.data && (
                    <>
                        <div className="fw:grid fw:grid-cols-1 fw:gap-4 fw:sm:grid-cols-2 fw:lg:grid-cols-4">
                            <StatCard
                                icon={Heart}
                                label={__("Wishlist items")}
                                value={dashboard.data.totals.totalItems}
                            />
                            <StatCard
                                icon={ListChecks}
                                label={__("Lists")}
                                value={dashboard.data.totals.totalLists}
                            />
                            <StatCard
                                icon={Layers}
                                label={__("Active wishlists")}
                                value={dashboard.data.totals.activeWishlists}
                            />
                            <StatCard
                                icon={Users}
                                label={__("Guest wishlists")}
                                value={dashboard.data.totals.guestWishlists}
                            />
                        </div>

                        <TopProducts products={dashboard.data.topProducts} />
                    </>
                )}
            </div>
        </div>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
}) {
    return (
        <div className="fw:rounded-xl fw:border fw:border-slate-200 fw:bg-white fw:p-5 fw:shadow-sm">
            <div className="fw:flex fw:items-center fw:gap-3">
                <span className="fw:flex fw:h-10 fw:w-10 fw:shrink-0 fw:items-center fw:justify-center fw:rounded-lg fw:bg-brand-50 fw:text-brand-600">
                    <Icon className="fw:h-5 fw:w-5" aria-hidden />
                </span>
                <div className="fw:min-w-0">
                    <div className="fw:text-xs fw:text-slate-500">{label}</div>
                    <div className="fw:text-2xl fw:font-bold fw:text-slate-900">
                        {value.toLocaleString()}
                    </div>
                </div>
            </div>
        </div>
    );
}

function TopProducts({ products }: { products: import("./useDashboard").TopProduct[] }) {
    return (
        <div className="fw:overflow-hidden fw:rounded-xl fw:border fw:border-slate-200 fw:bg-white fw:shadow-sm">
            <div className="fw:flex fw:items-center fw:gap-3 fw:border-b fw:border-slate-100 fw:bg-slate-50/60 fw:px-5 fw:py-4">
                <span className="fw:flex fw:h-10 fw:w-10 fw:shrink-0 fw:items-center fw:justify-center fw:rounded-lg fw:bg-brand-50 fw:text-brand-600">
                    <Package className="fw:h-5 fw:w-5" aria-hidden />
                </span>
                <div>
                    <h2 className="fw:text-base fw:font-semibold fw:text-slate-900">
                        {__("Most wishlisted products")}
                    </h2>
                    <p className="fw:text-sm fw:text-slate-500">
                        {__("The products shoppers save the most.")}
                    </p>
                </div>
            </div>

            {products.length === 0 ? (
                <div className="fw:px-5 fw:py-8 fw:text-center fw:text-sm fw:text-slate-500">
                    {__("No products have been wishlisted yet.")}
                </div>
            ) : (
                <ul>
                    {products.map((product, i) => (
                        <li
                            key={product.productId}
                            className="fw:flex fw:items-center fw:gap-3 fw:border-t fw:border-slate-100 fw:px-5 fw:py-3 fw:first:border-t-0"
                        >
                            <span className="fw:flex fw:h-8 fw:w-8 fw:shrink-0 fw:items-center fw:justify-center fw:rounded-full fw:bg-slate-100 fw:text-xs fw:font-semibold fw:text-slate-600">
                                {i + 1}
                            </span>
                            <span className="fw:min-w-0 fw:flex-1 fw:truncate fw:text-sm fw:font-medium fw:text-slate-900">
                                {product.name}
                            </span>
                            <span className="fw:shrink-0 fw:rounded-full fw:bg-brand-50 fw:px-2.5 fw:py-1 fw:text-xs fw:font-semibold fw:text-brand-700">
                                {sprintf(__("%d saves"), product.count)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

