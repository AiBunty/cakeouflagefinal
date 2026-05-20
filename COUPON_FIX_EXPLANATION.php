<?php
/**
 * FIX for coupon visibility in top-offer-banner.php
 * 
 * Issue: Current logic requires BOTH start AND end timestamps to be set
 * This breaks open-ended coupons with NULL start or NULL end
 * 
 * Solution: Handle NULL values properly
 * - NULL start = "valid from beginning"
 * - NULL end = "valid until disabled"  
 * - Empty both = "always valid"
 */

$nowTs = time();

// Original (BROKEN) logic:
// $couponWindowValid = $couponStartsTs !== false && $couponEndsTs !== false && $nowTs >= $couponStartsTs && $nowTs <= $couponEndsTs;

// FIXED logic:
$couponWindowValid = true;  // Start with assumption it's valid

// Check start time (null start = always started)
if ($couponStarts !== '') {
    // Start time is specified
    $couponWindowValid = $couponWindowValid && ($nowTs >= $couponStartsTs);
}
// If start is null, no check needed (always valid from start)

// Check end time (null end = never ends)
if ($couponEnds !== '') {
    // End time is specified
    $couponWindowValid = $couponWindowValid && ($nowTs <= $couponEndsTs);
}
// If end is null, no check needed (always valid to end)

// Final visibility check
$showSiteTopOffer = $couponActive && !$couponDeleted && $couponWindowValid && $couponCode !== '';
?>

<!-- 
EXPLANATION OF FIX:

Original broken logic (line 71):
$couponWindowValid = $couponStartsTs !== false && $couponEndsTs !== false && $nowTs >= $couponStartsTs && $nowTs <= $couponEndsTs;

This requires:
1. $couponStartsTs is not false (requires start to be set)
2. $couponEndsTs is not false (requires end to be set)  
3. $nowTs >= $couponStartsTs (current time >= start)
4. $nowTs <= $couponEndsTs (current time <= end)

Problem: If start OR end is NULL, $couponStartsTs or $couponEndsTs becomes false (from strtotime()),
causing the entire expression to be false, regardless of other conditions.

New fixed logic:
1. Start with $couponWindowValid = true
2. Only check start time IF it's specified (not null)
3. Only check end time IF it's specified (not null)
4. This allows:
   - NULL start: always valid from beginning
   - NULL end: always valid to infinity
   - Both NULL: always valid
   - Both set: must be within range
   - Only start set: must be after start (no end limit)
   - Only end set: must be before end (no start limit)
-->
