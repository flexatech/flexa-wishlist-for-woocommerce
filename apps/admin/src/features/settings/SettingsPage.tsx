import {
    Bell,
    Bookmark,
    ChevronDown,
    Clock,
    Eye,
    Hash,
    Heart,
    LayoutGrid,
    Link2,
    ListPlus,
    Lock,
    MousePointerClick,
    Palette,
    Save,
    Settings2,
    Share2,
    ShieldAlert,
    ShoppingCart,
    Sparkles,
    SquareStack,
    Star,
    Tag,
    ToggleRight,
    UserCheck,
    type LucideIcon,
} from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/cn";
import { SHOW_PRO_UPSELL } from "@/lib/flags";
import { __ } from "@/lib/i18n";
import { useUiStore } from "@/lib/store";
import { getPluginGlobal } from "@/lib/wp";
import type { Settings } from "@/types/global";
import { DangerZone } from "./DangerZone";
import { ROW_DIVIDER, SettingRow } from "./SettingRow";
import { useSaveSettings, useSettings, type SettingsPatch } from "./useSettings";

type SectionId =
    | "general"
    | "appearance"
    | "button"
    | "page"
    | "sharing"
    | "counter"
    | "advanced"
    | "notifications"
    | "analytics"
    | "danger";

interface SectionMeta {
    id: SectionId;
    title: string;
    subtitle: string;
    icon: LucideIcon;
    paneTitle: string;
    paneSubtitle: string;
    pro?: boolean;
}

const ALL_SECTIONS: SectionMeta[] = [
    {
        id: "general",
        title: __("General"),
        subtitle: __("Core wishlist behavior"),
        icon: Settings2,
        paneTitle: __("General"),
        paneSubtitle: __("Turn the wishlist on and tune how it stores data."),
    },
    {
        id: "appearance",
        title: __("Appearance"),
        subtitle: __("Look & feel"),
        icon: Palette,
        paneTitle: __("Appearance"),
        paneSubtitle: __("Match the wishlist to your store's design."),
    },
    {
        id: "button",
        title: __("Button"),
        subtitle: __("Add-to-wishlist button"),
        icon: MousePointerClick,
        paneTitle: __("Button"),
        paneSubtitle: __("Where the button appears and what it says."),
    },
    {
        id: "page",
        title: __("Wishlist Page"),
        subtitle: __("The saved-items page"),
        icon: LayoutGrid,
        paneTitle: __("Wishlist Page"),
        paneSubtitle: __("How saved products are laid out and what they show."),
    },
    {
        id: "sharing",
        title: __("Sharing"),
        subtitle: __("Public & shared lists"),
        icon: Share2,
        paneTitle: __("Sharing"),
        paneSubtitle: __("Let shoppers share their wishlists with others."),
    },
    {
        id: "counter",
        title: __("Counter"),
        subtitle: __("Header count badge"),
        icon: Hash,
        paneTitle: __("Counter"),
        paneSubtitle: __("Show a live wishlist count in your site header."),
    },
    {
        id: "advanced",
        title: __("Advanced"),
        subtitle: __("Behavior & cleanup"),
        icon: SquareStack,
        paneTitle: __("Advanced"),
        paneSubtitle: __("Fine-grained behavior and data-cleanup options."),
    },
    {
        id: "notifications",
        title: __("Notifications"),
        subtitle: __("Price-drop & back-in-stock"),
        icon: Bell,
        paneTitle: __("Notifications"),
        paneSubtitle: __("Email shoppers when saved products change."),
        pro: true,
    },
    {
        id: "analytics",
        title: __("Analytics"),
        subtitle: __("Conversion insights"),
        icon: Sparkles,
        paneTitle: __("Analytics"),
        paneSubtitle: __("Attribution and conversion reporting for wishlists."),
        pro: true,
    },
    {
        id: "danger",
        title: __("Danger Zone"),
        subtitle: __("Destructive actions"),
        icon: ShieldAlert,
        paneTitle: __("Danger Zone"),
        paneSubtitle: __("Reset everything back to a clean slate."),
    },
];

// Pro-teaser sections (Notifications, Analytics) are upsell-only surfaces.
// Hidden for the WordPress.org build; re-enabled by flipping SHOW_PRO_UPSELL.
const SECTIONS: SectionMeta[] = ALL_SECTIONS.filter(
    (s) => SHOW_PRO_UPSELL || !s.pro,
);

const PRESET_OPTIONS = [
    { value: "flexa", label: __("Flexa") },
    { value: "minimal", label: __("Minimal") },
    { value: "classic", label: __("Classic") },
    { value: "custom", label: __("Custom") },
];
const ICON_OPTIONS = [
    { value: "heart", label: __("Heart") },
    { value: "star", label: __("Star") },
    { value: "bookmark", label: __("Bookmark") },
];
const RADIUS_OPTIONS = [
    { value: "none", label: __("None") },
    { value: "sm", label: __("Small") },
    { value: "md", label: __("Medium") },
    { value: "lg", label: __("Large") },
    { value: "full", label: __("Full") },
];
const LOOP_POSITION_OPTIONS = [
    { value: "on_image", label: __("On the image") },
    { value: "after_add_to_cart", label: __("After add-to-cart") },
    { value: "none", label: __("Don't show") },
];
const SINGLE_POSITION_OPTIONS = [
    { value: "after_add_to_cart", label: __("After add-to-cart") },
    { value: "before_add_to_cart", label: __("Before add-to-cart") },
    { value: "after_summary", label: __("After the summary") },
    { value: "none", label: __("Don't show") },
];
const LAYOUT_OPTIONS = [
    { value: "grid", label: __("Grid") },
    { value: "list", label: __("List") },
];

const ICON_FOR_APPEARANCE: Record<string, LucideIcon> = {
    heart: Heart,
    star: Star,
    bookmark: Bookmark,
};

/** Deep-equal for the two-level settings groups (values are primitives or
 *  string arrays). Enough to diff form state against the loaded query. */
function groupChanged<K extends keyof Settings>(a: Settings[K], b: Settings[K]): boolean {
    return JSON.stringify(a) !== JSON.stringify(b);
}

function diffSettings(form: Settings, base: Settings): SettingsPatch {
    const out: SettingsPatch = {};
    (Object.keys(form) as Array<keyof Settings>).forEach((group) => {
        if (groupChanged(form[group], base[group])) {
            out[group] = form[group] as never;
        }
    });
    return out;
}

export function SettingsPage() {
    const settings = useSettings();
    const save = useSaveSettings();
    const showToast = useUiStore((s) => s.showToast);
    const storedActive = useUiStore((s) => s.activeSection) as SectionId;
    const setActive = useUiStore((s) => s.setActiveSection);
    const { proEnabled } = getPluginGlobal();

    const [form, setForm] = useState<Settings | null>(null);
    useEffect(() => {
        if (settings.data && !form) {
            setForm(settings.data);
        }
    }, [settings.data, form]);

    if (settings.isLoading || !form) {
        return (
            <div className="fw:p-6 fw:text-sm fw:text-slate-500">
                {__("Loading settings…")}
            </div>
        );
    }
    if (settings.isError) {
        return (
            <div className="fw:m-6 fw:rounded-md fw:bg-red-50 fw:p-4 fw:text-sm fw:text-red-700">
                {__("Failed to load settings:")}{" "}
                {(settings.error as Error).message}
            </div>
        );
    }

    const base = settings.data as Settings;
    const changed = diffSettings(form, base);
    const dirty = Object.keys(changed).length > 0;

    const onSave = () => {
        if (!dirty) {
            return;
        }
        save.mutate(changed, {
            onSuccess: () => showToast(__("Settings saved.")),
            onError: () => showToast(__("Save failed."), "error"),
        });
    };

    // Group-scoped setters keep the update immutable and typed.
    function setGroup<K extends keyof Settings>(group: K, patch: Partial<Settings[K]>) {
        setForm({ ...form!, [group]: { ...form![group], ...patch } });
    }

    // Fall back to the first visible section if the persisted one is hidden
    // (e.g. a Pro-teaser section that is not shown in this build).
    const activeSection = SECTIONS.find((s) => s.id === storedActive) ?? SECTIONS[0];
    const active = activeSection.id;

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
            <div className="fw:mx-auto fw:flex fw:max-w-6xl fw:flex-wrap fw:items-start fw:justify-between fw:gap-4 fw:px-6 fw:pt-8 fw:pb-6">
                <div className="fw:space-y-1">
                    <h1 className="fw:text-3xl fw:font-bold fw:text-slate-900">
                        {__("Settings")}
                    </h1>
                    <p className="fw:text-sm fw:text-slate-600">
                        {__(
                            "Configure how the wishlist looks and behaves across your store.",
                        )}
                    </p>
                </div>
                <div className="fw:flex fw:items-center fw:gap-3">
                    {save.isSuccess && !dirty && (
                        <span className="fw:text-sm fw:text-emerald-700">
                            {__("Settings saved.")}
                        </span>
                    )}
                    {save.isError && (
                        <span className="fw:text-sm fw:text-red-700">
                            {__("Save failed:")}{" "}
                            {(save.error as Error).message}
                        </span>
                    )}
                    <Button
                        onClick={onSave}
                        disabled={!dirty || save.isPending}
                        className="fw:gap-2"
                    >
                        <Save className="fw:h-4 fw:w-4" aria-hidden />
                        {save.isPending ? __("Saving…") : __("Save Settings")}
                    </Button>
                </div>
            </div>

            {/* Body: nav + pane */}
            <div className="fw:mx-auto fw:flex fw:max-w-6xl fw:flex-col fw:gap-6 fw:px-6 fw:pb-12 fw:md:flex-row">
                <aside className="fw:w-full fw:shrink-0 fw:space-y-2 fw:md:w-72">
                    {SECTIONS.map((s) => (
                        <NavItem
                            key={s.id}
                            section={s}
                            selected={active === s.id}
                            locked={Boolean(s.pro) && !proEnabled}
                            onSelect={() => setActive(s.id)}
                        />
                    ))}
                </aside>

                <main className="fw:flex-1">
                    <div className="fw:overflow-hidden fw:rounded-xl fw:border fw:border-slate-200 fw:bg-white fw:shadow-sm">
                        <PaneHeader section={activeSection} />

                        {active === "general" && (
                            <GeneralPane form={form} onChange={(p) => setGroup("general", p)} />
                        )}
                        {active === "appearance" && (
                            <AppearancePane
                                form={form}
                                onChange={(p) => setGroup("appearance", p)}
                            />
                        )}
                        {active === "button" && (
                            <ButtonPane form={form} onChange={(p) => setGroup("button", p)} />
                        )}
                        {active === "page" && (
                            <PagePane form={form} onChange={(p) => setGroup("page", p)} />
                        )}
                        {active === "sharing" && (
                            <SharingPane form={form} onChange={(p) => setGroup("sharing", p)} />
                        )}
                        {active === "counter" && (
                            <CounterPane form={form} onChange={(p) => setGroup("counter", p)} />
                        )}
                        {active === "advanced" && (
                            <AdvancedPane form={form} onChange={(p) => setGroup("advanced", p)} />
                        )}
                        {active === "notifications" && (
                            <ProTeaser
                                title={__("Notifications are a Pro feature")}
                                description={__(
                                    "Automatically email shoppers when a saved product drops in price or comes back in stock.",
                                )}
                                enabled={proEnabled}
                            />
                        )}
                        {active === "analytics" && (
                            <ProTeaser
                                title={__("Analytics is a Pro feature")}
                                description={__(
                                    "See which wishlisted products convert, with configurable attribution windows.",
                                )}
                                enabled={proEnabled}
                            />
                        )}
                        {active === "danger" && (
                            <div className="fw:p-5">
                                <DangerZone />
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </div>
    );
}

/* ---------------------------------------------------------------- panes -- */

interface PaneProps<K extends keyof Settings> {
    form: Settings;
    onChange: (patch: Partial<Settings[K]>) => void;
}

function GeneralPane({ form, onChange }: PaneProps<"general">) {
    const g = form.general;
    return (
        <div>
            <SettingRow
                icon={ToggleRight}
                htmlFor="fw-general-enabled"
                title={__("Enable wishlist")}
                description={__("Turn the wishlist feature on across your store.")}
            >
                <Switch
                    id="fw-general-enabled"
                    checked={g.enabled}
                    onCheckedChange={(v) => onChange({ enabled: v })}
                />
            </SettingRow>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={UserCheck}
                    htmlFor="fw-general-guest"
                    title={__("Guest wishlists")}
                    description={__("Let logged-out visitors save products too.")}
                >
                    <Switch
                        id="fw-general-guest"
                        checked={g.guest_wishlists}
                        onCheckedChange={(v) => onChange({ guest_wishlists: v })}
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Clock}
                    htmlFor="fw-general-retention"
                    title={__("Retention (days)")}
                    description={__("How long a guest wishlist is kept before cleanup.")}
                >
                    <Input
                        id="fw-general-retention"
                        type="number"
                        min={1}
                        max={3650}
                        value={g.retention_days}
                        onChange={(e) =>
                            onChange({ retention_days: Number(e.target.value) })
                        }
                        className="fw:w-24"
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={ListPlus}
                    htmlFor="fw-general-listname"
                    title={__("Default list name")}
                    description={__("The name given to a shopper's first wishlist.")}
                >
                    <Input
                        id="fw-general-listname"
                        value={g.default_list_name}
                        onChange={(e) =>
                            onChange({ default_list_name: e.target.value })
                        }
                        className="fw:w-56"
                    />
                </SettingRow>
            </div>
        </div>
    );
}

function AppearancePane({ form, onChange }: PaneProps<"appearance">) {
    const a = form.appearance;
    const IconPreview = ICON_FOR_APPEARANCE[a.icon] ?? Heart;
    return (
        <div>
            <SettingRow
                icon={Palette}
                htmlFor="fw-appearance-preset"
                title={__("Preset")}
                description={__("A starting style for the wishlist UI.")}
            >
                <Select
                    id="fw-appearance-preset"
                    value={a.preset}
                    options={PRESET_OPTIONS}
                    onChange={(e) =>
                        onChange({ preset: e.target.value as typeof a.preset })
                    }
                />
            </SettingRow>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Tag}
                    title={__("Accent color")}
                    description={__(
                        "Leave empty to inherit your theme's color.",
                    )}
                >
                    <div className="fw:flex fw:items-center fw:gap-2">
                        <input
                            aria-label={__("Accent color")}
                            type="color"
                            value={a.accent_color || "#2563eb"}
                            onChange={(e) => onChange({ accent_color: e.target.value })}
                            className="fw:h-9 fw:w-10 fw:cursor-pointer fw:rounded-md fw:border fw:border-slate-300 fw:bg-white fw:p-1"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onChange({ accent_color: "" })}
                            disabled={a.accent_color === ""}
                        >
                            {__("Inherit from theme")}
                        </Button>
                    </div>
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={IconPreview}
                    htmlFor="fw-appearance-icon"
                    title={__("Icon")}
                    description={__("The icon used for the wishlist button.")}
                >
                    <Select
                        id="fw-appearance-icon"
                        value={a.icon}
                        options={ICON_OPTIONS}
                        onChange={(e) =>
                            onChange({ icon: e.target.value as typeof a.icon })
                        }
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={SquareStack}
                    htmlFor="fw-appearance-radius"
                    title={__("Corner radius")}
                    description={__("Roundness of buttons and cards.")}
                >
                    <Select
                        id="fw-appearance-radius"
                        value={a.radius}
                        options={RADIUS_OPTIONS}
                        onChange={(e) =>
                            onChange({ radius: e.target.value as typeof a.radius })
                        }
                    />
                </SettingRow>
            </div>
            <div className={cn(ROW_DIVIDER, "fw:space-y-1.5 fw:p-5")}>
                <Label
                    htmlFor="fw-appearance-css"
                    className="fw:text-sm fw:font-semibold fw:text-slate-900"
                >
                    {__("Custom CSS")}
                </Label>
                <p className="fw:text-xs fw:text-slate-500">
                    {__("Extra CSS injected on the storefront. Use with care.")}
                </p>
                <textarea
                    id="fw-appearance-css"
                    rows={5}
                    value={a.custom_css}
                    placeholder=".flexa-wishlist { }"
                    onChange={(e) => onChange({ custom_css: e.target.value })}
                    className="flexa-woocommerce-wishlist-control fw:w-full fw:rounded-md fw:border fw:border-slate-300 fw:bg-white fw:px-3 fw:py-2 fw:font-mono fw:text-sm fw:shadow-sm fw:transition-colors fw:placeholder:text-slate-400 fw:focus-visible:outline-none fw:focus-visible:ring-2 fw:focus-visible:ring-brand-500 fw:focus-visible:ring-offset-1"
                />
            </div>
        </div>
    );
}

function ButtonPane({ form, onChange }: PaneProps<"button">) {
    const b = form.button;
    return (
        <div>
            <SettingRow
                icon={LayoutGrid}
                htmlFor="fw-button-loop"
                title={__("Position in product loop")}
                description={__("Where the button shows on shop/category listings.")}
            >
                <Select
                    id="fw-button-loop"
                    value={b.position_loop}
                    options={LOOP_POSITION_OPTIONS}
                    onChange={(e) =>
                        onChange({
                            position_loop: e.target.value as typeof b.position_loop,
                        })
                    }
                />
            </SettingRow>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={MousePointerClick}
                    htmlFor="fw-button-single"
                    title={__("Position on product page")}
                    description={__("Where the button shows on a single product.")}
                >
                    <Select
                        id="fw-button-single"
                        value={b.position_single}
                        options={SINGLE_POSITION_OPTIONS}
                        onChange={(e) =>
                            onChange({
                                position_single:
                                    e.target.value as typeof b.position_single,
                            })
                        }
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Tag}
                    htmlFor="fw-button-add"
                    title={__("Add label")}
                    description={__("Text shown before a product is saved.")}
                >
                    <Input
                        id="fw-button-add"
                        value={b.label_add}
                        onChange={(e) => onChange({ label_add: e.target.value })}
                        className="fw:w-56"
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Heart}
                    htmlFor="fw-button-added"
                    title={__("Added label")}
                    description={__("Text shown once a product is saved.")}
                >
                    <Input
                        id="fw-button-added"
                        value={b.label_added}
                        onChange={(e) => onChange({ label_added: e.target.value })}
                        className="fw:w-56"
                    />
                </SettingRow>
            </div>
        </div>
    );
}

function PagePane({ form, onChange }: PaneProps<"page">) {
    const p = form.page;
    return (
        <div>
            <SettingRow
                icon={LayoutGrid}
                htmlFor="fw-page-layout"
                title={__("Layout")}
                description={__("Show saved products as a grid or a list.")}
            >
                <Select
                    id="fw-page-layout"
                    value={p.layout}
                    options={LAYOUT_OPTIONS}
                    onChange={(e) =>
                        onChange({ layout: e.target.value as typeof p.layout })
                    }
                />
            </SettingRow>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Hash}
                    htmlFor="fw-page-perpage"
                    title={__("Items per page")}
                    description={__("How many saved products to show per page.")}
                >
                    <Input
                        id="fw-page-perpage"
                        type="number"
                        min={1}
                        max={200}
                        value={p.per_page}
                        onChange={(e) => onChange({ per_page: Number(e.target.value) })}
                        className="fw:w-24"
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Tag}
                    htmlFor="fw-page-price"
                    title={__("Show price")}
                    description={__("Display each product's price on the page.")}
                >
                    <Switch
                        id="fw-page-price"
                        checked={p.show_price}
                        onCheckedChange={(v) => onChange({ show_price: v })}
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Eye}
                    htmlFor="fw-page-stock"
                    title={__("Show stock")}
                    description={__("Display each product's stock status.")}
                >
                    <Switch
                        id="fw-page-stock"
                        checked={p.show_stock}
                        onCheckedChange={(v) => onChange({ show_stock: v })}
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={ShoppingCart}
                    htmlFor="fw-page-atc"
                    title={__("Add-to-cart button")}
                    description={__("Let shoppers add a saved product straight to cart.")}
                >
                    <Switch
                        id="fw-page-atc"
                        checked={p.add_to_cart}
                        onCheckedChange={(v) => onChange({ add_to_cart: v })}
                    />
                </SettingRow>
            </div>
        </div>
    );
}

function SharingPane({ form, onChange }: PaneProps<"sharing">) {
    const s = form.sharing;
    const CHANNELS = [
        { key: "email", label: __("Email") },
        { key: "whatsapp", label: __("WhatsApp") },
        { key: "x", label: __("X") },
        { key: "facebook", label: __("Facebook") },
        { key: "pinterest", label: __("Pinterest") },
    ];
    const toggleChannel = (key: string, on: boolean) => {
        const set = new Set(s.channels);
        if (on) {
            set.add(key);
        } else {
            set.delete(key);
        }
        onChange({ channels: Array.from(set) });
    };
    return (
        <div>
            <SettingRow
                icon={Share2}
                htmlFor="fw-sharing-enabled"
                title={__("Enable sharing")}
                description={__("Let shoppers share their wishlists via a link.")}
            >
                <Switch
                    id="fw-sharing-enabled"
                    checked={s.enabled}
                    onCheckedChange={(v) => onChange({ enabled: v })}
                />
            </SettingRow>
            <div className={cn(ROW_DIVIDER, "fw:space-y-2 fw:p-5")}>
                <Label className="fw:text-sm fw:font-semibold fw:text-slate-900">
                    {__("Channels")}
                </Label>
                <p className="fw:text-xs fw:text-slate-500">
                    {__("Which share destinations to offer.")}
                </p>
                <div className="fw:flex fw:flex-wrap fw:gap-2 fw:pt-1">
                    {CHANNELS.map((c) => {
                        const on = s.channels.includes(c.key);
                        return (
                            <button
                                key={c.key}
                                type="button"
                                aria-pressed={on}
                                onClick={() => toggleChannel(c.key, !on)}
                                className={cn(
                                    "fw:rounded-full fw:border fw:px-3 fw:py-1.5 fw:text-xs fw:font-medium fw:transition-colors fw:cursor-pointer",
                                    "fw:focus-visible:outline-none fw:focus-visible:ring-2 fw:focus-visible:ring-brand-500",
                                    on
                                        ? "fw:border-brand-500 fw:bg-brand-50 fw:text-brand-700"
                                        : "fw:border-slate-300 fw:bg-white fw:text-slate-600 fw:hover:bg-slate-50",
                                )}
                            >
                                {c.label}
                            </button>
                        );
                    })}
                </div>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={UserCheck}
                    htmlFor="fw-sharing-owner"
                    title={__("Show owner name")}
                    description={__("Display the shopper's name on a shared list.")}
                >
                    <Switch
                        id="fw-sharing-owner"
                        checked={s.show_owner_name}
                        onCheckedChange={(v) => onChange({ show_owner_name: v })}
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Link2}
                    htmlFor="fw-sharing-index"
                    title={__("Allow search indexing")}
                    description={__("Let search engines index public shared lists.")}
                >
                    <Switch
                        id="fw-sharing-index"
                        checked={s.allow_indexing}
                        onCheckedChange={(v) => onChange({ allow_indexing: v })}
                    />
                </SettingRow>
            </div>
        </div>
    );
}

function CounterPane({ form, onChange }: PaneProps<"counter">) {
    const c = form.counter;
    return (
        <SettingRow
            icon={Hash}
            htmlFor="fw-counter-auto"
            title={__("Auto-inject counter")}
            description={__(
                "Automatically add a wishlist count badge to your site header.",
            )}
        >
            <Switch
                id="fw-counter-auto"
                checked={c.auto_inject}
                onCheckedChange={(v) => onChange({ auto_inject: v })}
            />
        </SettingRow>
    );
}

function AdvancedPane({ form, onChange }: PaneProps<"advanced">) {
    const a = form.advanced;
    return (
        <div>
            <SettingRow
                icon={ShoppingCart}
                htmlFor="fw-adv-remove"
                title={__("Remove after add-to-cart")}
                description={__("Take a product off the wishlist once it's added to cart.")}
            >
                <Switch
                    id="fw-adv-remove"
                    checked={a.remove_after_add_to_cart}
                    onCheckedChange={(v) => onChange({ remove_after_add_to_cart: v })}
                />
            </SettingRow>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={Hash}
                    htmlFor="fw-adv-qty"
                    title={__("Show quantity")}
                    description={__("Let shoppers set a quantity on the wishlist page.")}
                >
                    <Switch
                        id="fw-adv-qty"
                        checked={a.show_quantity}
                        onCheckedChange={(v) => onChange({ show_quantity: v })}
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={SquareStack}
                    htmlFor="fw-adv-scripts"
                    title={__("Load scripts on all pages")}
                    description={__(
                        "Enqueue the wishlist assets everywhere, not just product pages.",
                    )}
                >
                    <Switch
                        id="fw-adv-scripts"
                        checked={a.load_scripts_all_pages}
                        onCheckedChange={(v) => onChange({ load_scripts_all_pages: v })}
                    />
                </SettingRow>
            </div>
            <div className={ROW_DIVIDER}>
                <SettingRow
                    icon={ShieldAlert}
                    htmlFor="fw-adv-uninstall"
                    title={__("Delete data on uninstall")}
                    description={__("Wipe all wishlist data when the plugin is deleted.")}
                >
                    <Switch
                        id="fw-adv-uninstall"
                        checked={a.delete_data_on_uninstall}
                        onCheckedChange={(v) => onChange({ delete_data_on_uninstall: v })}
                    />
                </SettingRow>
            </div>
        </div>
    );
}

/* --------------------------------------------------------- nav & header -- */

interface NavItemProps {
    section: SectionMeta;
    selected: boolean;
    locked: boolean;
    onSelect: () => void;
}

function NavItem({ section, selected, locked, onSelect }: NavItemProps) {
    const Icon = section.icon;
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                "fw:group fw:flex fw:w-full fw:items-center fw:gap-3 fw:rounded-xl fw:border fw:px-3 fw:py-2.5 fw:text-left fw:transition-colors fw:cursor-pointer",
                "fw:focus-visible:outline-none fw:focus-visible:ring-2 fw:focus-visible:ring-brand-500",
                selected
                    ? "fw:border-brand-500 fw:bg-brand-500 fw:text-white fw:shadow-sm"
                    : "fw:border-slate-200 fw:bg-white fw:text-slate-800 fw:hover:border-slate-300 fw:hover:bg-slate-50",
            )}
        >
            <span
                className={cn(
                    "fw:flex fw:h-9 fw:w-9 fw:shrink-0 fw:items-center fw:justify-center fw:rounded-lg",
                    selected
                        ? "fw:bg-white/15 fw:text-white"
                        : "fw:bg-slate-100 fw:text-slate-600",
                )}
            >
                <Icon className="fw:h-4 fw:w-4" aria-hidden />
            </span>
            <span className="fw:min-w-0 fw:flex-1">
                <span
                    className={cn(
                        "fw:flex fw:items-center fw:gap-1.5 fw:text-sm fw:font-semibold",
                        selected ? "fw:text-white" : "fw:text-slate-900",
                    )}
                >
                    {section.title}
                    {locked && (
                        <span
                            className={cn(
                                "fw:inline-flex fw:items-center fw:gap-0.5 fw:rounded-full fw:px-1.5 fw:py-0.5 fw:text-[10px] fw:font-bold fw:uppercase fw:tracking-wide",
                                selected
                                    ? "fw:bg-white/20 fw:text-white"
                                    : "fw:bg-brand-50 fw:text-brand-700",
                            )}
                        >
                            <Lock className="fw:h-2.5 fw:w-2.5" aria-hidden />
                            {__("Pro")}
                        </span>
                    )}
                </span>
                <span
                    className={cn(
                        "fw:block fw:text-xs",
                        selected ? "fw:text-white/80" : "fw:text-slate-500",
                    )}
                >
                    {section.subtitle}
                </span>
            </span>
            <ChevronDown
                className={cn(
                    "fw:h-4 fw:w-4 fw:shrink-0 fw:transition-transform",
                    selected ? "fw:text-white" : "fw:-rotate-90 fw:text-slate-400",
                )}
                aria-hidden
            />
        </button>
    );
}

function PaneHeader({ section }: { section: SectionMeta }) {
    const Icon = section.icon;
    return (
        <div className="fw:flex fw:items-start fw:gap-3 fw:border-b fw:border-slate-100 fw:bg-slate-50/60 fw:px-5 fw:py-4">
            <span className="fw:flex fw:h-10 fw:w-10 fw:shrink-0 fw:items-center fw:justify-center fw:rounded-lg fw:bg-brand-50 fw:text-brand-600">
                <Icon className="fw:h-5 fw:w-5" aria-hidden />
            </span>
            <div className="fw:min-w-0 fw:space-y-0.5">
                <h2 className="fw:text-base fw:font-semibold fw:text-slate-900">
                    {section.paneTitle}
                </h2>
                <p className="fw:text-sm fw:text-slate-500">{section.paneSubtitle}</p>
            </div>
        </div>
    );
}

/** Locked teaser card for Pro-only sections. Rendered visible but disabled
 *  (no interactive controls) when Pro is not enabled. */
function ProTeaser({
    title,
    description,
    enabled,
}: {
    title: string;
    description: string;
    enabled: boolean;
}) {
    const { proUpgradeUrl } = getPluginGlobal();
    return (
        <div className="fw:p-5">
            <div
                aria-disabled={!enabled}
                className={cn(
                    "fw:flex fw:flex-col fw:items-start fw:gap-4 fw:rounded-xl fw:border fw:border-dashed fw:border-brand-300 fw:bg-brand-50/50 fw:p-6",
                    !enabled && "fw:opacity-90",
                )}
            >
                <span className="fw:flex fw:h-12 fw:w-12 fw:items-center fw:justify-center fw:rounded-xl fw:bg-brand-600 fw:text-white">
                    <Lock className="fw:h-6 fw:w-6" aria-hidden />
                </span>
                <div className="fw:space-y-1">
                    <h3 className="fw:text-base fw:font-semibold fw:text-brand-900">
                        {title}
                    </h3>
                    <p className="fw:text-sm fw:text-brand-800">{description}</p>
                </div>
                {!enabled && (
                    <Button asChild>
                        <a href={proUpgradeUrl} target="_blank" rel="noreferrer">
                            {__("Upgrade to Pro")}
                        </a>
                    </Button>
                )}
            </div>
        </div>
    );
}
