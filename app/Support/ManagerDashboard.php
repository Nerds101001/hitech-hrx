<?php

namespace App\Support;

use App\Models\User;

/**
 * Picks which manager dashboard a user sees.
 *
 * Managers do not all want the same numbers. A Sales manager is measured on
 * targets and achievement; a Customer Care / New Biz manager is measured on
 * forecast, pending orders and the book. Rather than one dashboard that tries
 * to serve both, the manager view branches on the variant returned here.
 *
 * Adding a department is a one-line change to self::MAP.
 */
class ManagerDashboard
{
    /**
     * Normalised department name => dashboard variant.
     *
     * Keys are lower-cased with a trailing "department" stripped, so both
     * "Sales" and "Sales Department" land on the same entry.
     */
    private const MAP = [
        'sales'                  => 'sales',
        'customer care'          => 'ccare',
        'new biz'                => 'ccare',
        'customer care + new biz' => 'ccare',
        'ccare & new biz'        => 'ccare',
    ];

    /** Fallback for departments with no dedicated dashboard yet. */
    public const DEFAULT = 'ccare';

    /**
     * @return string One of: 'sales', 'ccare'.
     */
    public static function variant(?User $user): string
    {
        if (!$user) {
            return self::DEFAULT;
        }

        return self::MAP[self::normalise(self::departmentName($user))] ?? self::DEFAULT;
    }

    /** The department name on the user, whichever shape the record takes. */
    public static function departmentName(?User $user): string
    {
        if (!$user) {
            return '';
        }

        try {
            return (string) ($user->department->name ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Lower-case, collapse whitespace, drop a trailing "department". */
    private static function normalise(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/\s+/', ' ', $n);
        $n = preg_replace('/\s*department$/', '', $n);

        return trim($n);
    }
}
