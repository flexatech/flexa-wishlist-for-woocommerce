/**
 * Build-time UI feature flags.
 *
 * SHOW_PRO_UPSELL — whether the admin UI shows "upgrade to Pro" prompts
 * (the Dashboard upsell strip and the Notifications/Analytics Pro-teaser
 * settings sections). Temporarily OFF for the WordPress.org submission, whose
 * guidelines restrict in-dashboard upsell/advertising. Flip back to `true`
 * once the plugin is approved to restore the Pro teasers — no other change is
 * needed; every upsell surface is gated on this flag.
 */
export const SHOW_PRO_UPSELL = false;
