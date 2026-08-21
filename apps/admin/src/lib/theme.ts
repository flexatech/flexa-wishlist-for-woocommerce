import type { AppTheme } from "@/types/global";

/**
 * The admin color scheme published by Enqueue.php. Portaled Radix content
 * (Dialog/Tooltip) renders outside `.flexa-wishlist-themed`, so the
 * `[data-theme="dark"] *` selector chain breaks at the portal boundary. Each
 * portaled `*Content` re-stamps `data-theme` with this value so `fw:dark:*`
 * utilities keep working inside the portal.
 */
export function useEffectiveTheme(): AppTheme {
    return window.flexaWishlist?.theme ?? "light";
}
