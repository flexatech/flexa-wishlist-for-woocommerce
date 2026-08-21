/**
 * Bridge to the `flexaWishlist` global published by Enqueue.php via
 * wp_localize_script.
 */
import type { PluginGlobal } from "@/types/global";

export function getPluginGlobal(): PluginGlobal {
    if (!window.flexaWishlist) {
        throw new Error(
            "flexaWishlist global missing - make sure Enqueue::enqueue_admin ran before this script.",
        );
    }
    return window.flexaWishlist;
}
