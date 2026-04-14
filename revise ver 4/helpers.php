<?php
/**
 * Shared helper functions for receipt/order formatting
 */

// Format modifiers JSON into readable text
function formatModifiers($modifiersJson) {
    if (empty($modifiersJson)) {
        return '';
    }
    
    $modifiers = json_decode($modifiersJson, true);
    if (!$modifiers || !is_array($modifiers)) {
        return htmlspecialchars($modifiersJson); // Fallback to raw if invalid
    }
    
    $parts = [];
    if (!empty($modifiers['size'])) {
        $parts[] = 'Size: ' . htmlspecialchars($modifiers['size']);
    }
    
    if (!empty($modifiers['toppings']) && is_array($modifiers['toppings'])) {
        $toppings = array_filter($modifiers['toppings'], function($t) {
            return trim($t);
        });
        if (!empty($toppings)) {
            $parts[] = 'Toppings: ' . htmlspecialchars(implode(', ', $toppings));
        }
    }
    
    if (empty($parts)) {
        return 'No modifiers';
    }
    
    return implode(' · ', $parts);
}
?>

