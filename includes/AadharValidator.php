<?php
/**
 * AadharValidator Class
 * Specialized validation for Aadhar numbers
 */

class AadharValidator {
    
    /**
     * Validate Aadhar number format and rules
     * @param string $aadhar
     * @return array
     */
    public static function validate($aadhar) {
        // Remove spaces and sanitize
        $cleanAadhar = preg_replace('/\s+/', '', trim($aadhar));
        
        // Check if empty
        if (empty($cleanAadhar)) {
            return [
                'valid' => false,
                'error' => 'Aadhar number is required.',
                'formatted' => ''
            ];
        }
        
        // Check if contains only digits
        if (!ctype_digit($cleanAadhar)) {
            return [
                'valid' => false,
                'error' => 'Aadhar number must contain only digits.',
                'formatted' => ''
            ];
        }
        
        // Check length
        if (strlen($cleanAadhar) !== 12) {
            return [
                'valid' => false,
                'error' => 'Aadhar number must be exactly 12 digits.',
                'formatted' => ''
            ];
        }
        
        // Check if all digits are same
        if (preg_match('/^(\d)\1{11}$/', $cleanAadhar)) {
            return [
                'valid' => false,
                'error' => 'Aadhar number cannot have all same digits.',
                'formatted' => ''
            ];
        }
        
        // Check if starts with 0 or 1
        if ($cleanAadhar[0] === '0' || $cleanAadhar[0] === '1') {
            return [
                'valid' => false,
                'error' => 'Aadhar number cannot start with 0 or 1.',
                'formatted' => ''
            ];
        }
        
        // Additional business rules can be added here
        // For example, checking against known invalid patterns
        
        // Format for display/storage
        $formatted = self::format($cleanAadhar);
        
        return [
            'valid' => true,
            'error' => '',
            'formatted' => $formatted,
            'clean' => $cleanAadhar
        ];
    }
    
    /**
     * Format Aadhar number with spaces
     * @param string $aadhar
     * @return string
     */
    public static function format($aadhar) {
        $clean = preg_replace('/\s+/', '', $aadhar);
        if (strlen($clean) === 12) {
            return substr($clean, 0, 4) . ' ' . substr($clean, 4, 4) . ' ' . substr($clean, 8, 4);
        }
        return $aadhar;
    }
    
    /**
     * Clean Aadhar number (remove spaces)
     * @param string $aadhar
     * @return string
     */
    public static function clean($aadhar) {
        return preg_replace('/\s+/', '', $aadhar);
    }
    
    /**
     * Mask Aadhar number for display (show only last 4 digits)
     * @param string $aadhar
     * @return string
     */
    public static function mask($aadhar) {
        $clean = self::clean($aadhar);
        if (strlen($clean) === 12) {
            return 'XXXX XXXX ' . substr($clean, 8, 4);
        }
        return $aadhar;
    }
    
    /**
     * Check if Aadhar number is valid format (basic check)
     * @param string $aadhar
     * @return bool
     */
    public static function isValidFormat($aadhar) {
        $result = self::validate($aadhar);
        return $result['valid'];
    }
    
    /**
     * Get validation error message
     * @param string $aadhar
     * @return string
     */
    public static function getErrorMessage($aadhar) {
        $result = self::validate($aadhar);
        return $result['error'];
    }
}
?>