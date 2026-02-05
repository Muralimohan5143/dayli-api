<?php

namespace App\Support;

trait PhoneNormalizer
{
    /**
     * Normalize Indian numbers to +91XXXXXXXXXX (E.164-ish).
     * - Strips spaces, dashes, brackets, dots.
     * - Handles 0-leading locals, 91/0091 country prefixes, WhatsApp JIDs (…@s.whatsapp.net).
     * - If 10+ digits, prefers the **last 10** (common CRM export quirks).
     */
    public function normPhone(?string $raw, string $defaultCc = '+91'): ?string
    {
        if (!$raw) return null;

        // Remove WhatsApp JID suffix if present
        $raw = preg_replace('/@s\.whatsapp\.net$/i', '', trim($raw));

        // Keep + and digits only
        $clean = preg_replace('/[^0-9+]+/', '', $raw) ?? '';

        // If starts with 0091 → +91…
        if (str_starts_with($clean, '0091')) {
            $clean = '+' . substr($clean, 2); // 00 → +
        }

        // If starts with + → strip non-digits then re-add +
        if (str_starts_with($clean, '+')) {
            $digits = preg_replace('/\D+/', '', $clean);
            // +91 + 10 digits (perfect)
            if (preg_match('/^91\d{10}$/', $digits)) {
                return '+' . $digits;
            }
            // If more than 10 trailing digits, grab last 10 as local
            if (strlen($digits) > 10) {
                $last10 = substr($digits, -10);
                return $defaultCc . $last10;
            }
            // If exactly 10 after + (weird “+” on local), assume India
            if (strlen($digits) === 10) {
                return $defaultCc . $digits;
            }
            // Fallback: return +digits
            return '+' . $digits;
        }

        // No plus: just digits
        $digits = preg_replace('/\D+/', '', $clean) ?? '';

        // If starts with 91 and total 12 → good after adding +
        if (preg_match('/^91\d{10}$/', $digits)) {
            return '+' . $digits;
        }

        // If leading 0 + 10 digits → drop 0, add +91
        if (preg_match('/^0(\d{10})$/', $digits, $m)) {
            return $defaultCc . $m[1];
        }

        // If exactly 10 digits → assume India
        if (preg_match('/^\d{10}$/', $digits)) {
            return $defaultCc . $digits;
        }

        // If longer: take last 10 as local (common dumps)
        if (strlen($digits) > 10) {
            $last10 = substr($digits, -10);
            return $defaultCc . $last10;
        }

        // If too short or empty after cleaning, bail
        return $digits ? ('+' . $digits) : null;
    }
}
