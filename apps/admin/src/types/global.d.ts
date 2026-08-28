/**
 * The `window.flexaWishlist` config published by `src/Admin/Enqueue.php` via
 * wp_localize_script, and the nested settings schema mirrored from
 * `Flexa\Wishlist\Support\Settings`.
 */

export type AppTheme = "light" | "dark";

export interface GeneralSettings {
    enabled: boolean;
    guest_wishlists: boolean;
    retention_days: number;
    default_list_name: string;
    page_id: number;
}

export interface AppearanceSettings {
    preset: "flexa" | "minimal" | "classic" | "custom";
    /** Hex color, or empty string = inherit the theme. */
    accent_color: string;
    icon: "heart" | "star" | "bookmark";
    radius: "none" | "sm" | "md" | "lg" | "full";
}

export interface ButtonSettings {
    position_loop: "on_image" | "after_add_to_cart" | "none";
    position_single:
        | "after_add_to_cart"
        | "before_add_to_cart"
        | "after_summary"
        | "none";
    label_add: string;
    label_added: string;
}

export interface PageSettings {
    layout: "grid" | "list";
    per_page: number;
    show_price: boolean;
    show_stock: boolean;
    add_to_cart: boolean;
}

export interface SharingSettings {
    enabled: boolean;
    channels: string[];
    show_owner_name: boolean;
    allow_indexing: boolean;
}

export interface CounterSettings {
    auto_inject: boolean;
}

export interface AdvancedSettings {
    remove_after_add_to_cart: boolean;
    show_quantity: boolean;
    load_scripts_all_pages: boolean;
    delete_data_on_uninstall: boolean;
}

/** Full nested settings object. Reads/writes go through the REST `settings`
 *  endpoint; writes send only the changed groups (PHP merges partials). */
export interface Settings {
    general: GeneralSettings;
    appearance: AppearanceSettings;
    button: ButtonSettings;
    page: PageSettings;
    sharing: SharingSettings;
    counter: CounterSettings;
    advanced: AdvancedSettings;
}

export interface PluginGlobal {
    restUrl: string;
    restBase: string;
    restNonce: string;
    version: string;
    pluginUrl: string;
    locale: string;
    theme: AppTheme;
    settings: Settings;
    wishlistPage: { id: number; url: string };
}

declare global {
    interface Window {
        flexaWishlist?: PluginGlobal;
    }
}

export {};
