<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum target customers per round
    |--------------------------------------------------------------------------
    |
    | A round with fewer than this many customers does not count. Customers may
    | still be added a few at a time - the minimum is judged on the round's
    | total, not on one submission - so nobody loses partial work by saving as
    | they go. Until the total is reached the round is shown as incomplete
    | rather than hidden: the figures stay visible, they are just not treated
    | as a completed set.
    |
    */

    'min_customers' => (int) env('NINETY_DAY_MIN_CUSTOMERS', 10),

    // Amber threshold for the add-customer progress light: >= this (but below
    // the minimum) is yellow, below it is red, the minimum or more is green.
    'customers_amber_from' => (int) env('NINETY_DAY_CUSTOMERS_AMBER_FROM', 7),

    // Email a "finish adding your customers" reminder when the add window has
    // this many hours left and the person is still under the minimum.
    'reminder_lead_hours' => (int) env('NINETY_DAY_REMINDER_LEAD_HOURS', 12),

    /*
    |--------------------------------------------------------------------------
    | How long the window to add customers stays open
    |--------------------------------------------------------------------------
    |
    | Counted from the moment the round is created, which is when the person is
    | enrolled and told to add their customers. After it closes nobody can add
    | to that round - except admin/HR, who keep the ability to correct a
    | mistake after the fact.
    |
    */

    'customer_window_hours' => (int) env('NINETY_DAY_CUSTOMER_WINDOW_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | When the window rule starts applying
    |--------------------------------------------------------------------------
    |
    | Rounds created before this moment would otherwise have had their window
    | expire the instant the rule shipped - the three people in the first
    | program were enrolled on 28 Sep with a task not due until 1 Oct, and
    | would have been locked out having added nothing. Their window instead
    | runs 48 hours from here. Rounds created after this are measured from
    | their own creation, as normal.
    |
    | Set it to a past date to drop the grace entirely.
    |
    */

    'window_rule_from' => env('NINETY_DAY_WINDOW_RULE_FROM', '2026-09-28 17:00:00'),

];
