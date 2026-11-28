<?php
// 140 — PostHog analytics (US cloud), owner's decision 2026-11-28: page views, clicks and session recordings with
// inputs masked; signed-in people identified by user id only. The snippet is printed by theme_hikmah
// (hook_callbacks::posthog) while POSTHOG_KEY is set; empty key = no analytics.
require(__DIR__ . '/lib.php');

hk_set('posthogkey', (string) hk_env('POSTHOG_KEY', ''), 'theme_hikmah');

hk_done(__FILE__);
