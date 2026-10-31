<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commission
    |--------------------------------------------------------------------------
    |
    | Applied to the final sale price at escrow release.
    | Expressed as a percentage (e.g. 10 = 10%).
    |
    */
    'commission_percentage' => (int) env('AUCTION_COMMISSION_PERCENTAGE', 10),

    /*
    |--------------------------------------------------------------------------
    | Bid Increment
    |--------------------------------------------------------------------------
    |
    | Minimum raise above the current top bid, expressed as a percentage of
    | the current top bid. The absolute floor (in kobo) is applied when the
    | percentage calculation falls below it.
    |
    */
    'bid_increment_percentage' => (float) env('AUCTION_BID_INCREMENT_PERCENTAGE', 5),
    'bid_increment_floor_kobo' => (int) env('AUCTION_BID_INCREMENT_FLOOR_KOBO', 10000), // ₦100

    /*
    |--------------------------------------------------------------------------
    | Live Session Timer
    |--------------------------------------------------------------------------
    */
    'live_duration_seconds' => (int) env('AUCTION_LIVE_DURATION_SECONDS', 180),   // 3 min
    'grace_window_seconds' => (int) env('AUCTION_GRACE_WINDOW_SECONDS', 600),    // 10 min
    'decision_window_seconds' => (int) env('AUCTION_DECISION_WINDOW_SECONDS', 60),  // 60 s
    'anti_snipe_trigger_seconds' => (int) env('AUCTION_ANTI_SNIPE_TRIGGER_SECONDS', 10),
    'anti_snipe_add_seconds' => (int) env('AUCTION_ANTI_SNIPE_ADD_SECONDS', 15),
    'anti_snipe_max_extensions' => (int) env('AUCTION_ANTI_SNIPE_MAX_EXTENSIONS', 3),

    /*
    |--------------------------------------------------------------------------
    | Scheduling Constraints
    |--------------------------------------------------------------------------
    */
    'schedule_min_lead_seconds' => (int) env('AUCTION_SCHEDULE_MIN_LEAD_SECONDS', 3600),      // 1 hr
    'schedule_max_lead_seconds' => (int) env('AUCTION_SCHEDULE_MAX_LEAD_SECONDS', 1209600),   // 14 days

    /*
    |--------------------------------------------------------------------------
    | Escrow & Handover
    |--------------------------------------------------------------------------
    */
    'escrow_auto_release_hours' => (int) env('AUCTION_ESCROW_AUTO_RELEASE_HOURS', 72),
    'handover_code_max_attempts' => (int) env('AUCTION_HANDOVER_CODE_MAX_ATTEMPTS', 5),
    'handover_code_cooldown_minutes' => (int) env('AUCTION_HANDOVER_CODE_COOLDOWN_MINUTES', 30),
    'seller_no_show_strike_hours' => (int) env('AUCTION_SELLER_NO_SHOW_STRIKE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | New Account Limits (fraud protection)
    |--------------------------------------------------------------------------
    */
    'new_account_max_bid_kobo' => (int) env('AUCTION_NEW_ACCOUNT_MAX_BID_KOBO', 20000000),  // ₦200,000
    'new_account_days' => (int) env('AUCTION_NEW_ACCOUNT_DAYS', 30),
    'new_account_withdrawal_hold_hours' => (int) env('AUCTION_NEW_ACCOUNT_WITHDRAWAL_HOLD_HOURS', 168), // 7 days

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'reminder_lead_minutes' => [15, 1],
    'daily_notification_cap' => (int) env('AUCTION_DAILY_NOTIFICATION_CAP', 5),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Kill switches operable without a deployment. Set to false to disable.
    |
    */
    'features' => [
        'bidding_enabled' => env('FEATURE_BIDDING_ENABLED', true),
        'withdrawals_enabled' => env('FEATURE_WITHDRAWALS_ENABLED', true),
        'new_signups_enabled' => env('FEATURE_NEW_SIGNUPS_ENABLED', true),
        'kyc_required' => env('FEATURE_KYC_REQUIRED', true),
    ],

];
