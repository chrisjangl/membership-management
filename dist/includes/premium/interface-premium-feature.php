<?php

namespace DCMM\Premium;

/**
 * Interface for Premium Features
 * 
 * Defines the contract that all premium features must implement.
 * This ensures consistent activation/deactivation and settings management.
 * 
 * @since 1.1.0
 */
interface Premium_Feature_Interface {
    
    /**
     * Get the unique feature ID
     * 
     * @return string Unique identifier for this premium feature
     */
    public function get_feature_id();
    
    /**
     * Get the human-readable feature name
     * 
     * @return string Display name for this feature
     */
    public function get_feature_name();
    
    /**
     * Get the feature description
     * 
     * @return string Description of what this feature does
     */
    public function get_feature_description();
    
    /**
     * Check if this feature is currently enabled
     * 
     * @return bool True if feature is enabled and functional
     */
    public function is_enabled();
    
    /**
     * Check if feature dependencies are met
     * 
     * @return bool True if all required dependencies are available
     */
    public function check_dependencies();
    
    /**
     * Get dependency error messages if dependencies are not met
     * 
     * @return array Array of error messages, empty if all dependencies met
     */
    public function get_dependency_errors();
    
    /**
     * Initialize the feature
     * 
     * Called when the feature should be activated and start functioning.
     * This is where you would add hooks, filters, etc.
     * 
     * @return bool True on successful initialization
     */
    public function initialize();
    
    /**
     * Deactivate the feature
     * 
     * Called when the feature should be deactivated.
     * Remove hooks, cleanup, etc.
     * 
     * @return bool True on successful deactivation
     */
    public function deactivate();
    
    /**
     * Get feature settings fields
     * 
     * Returns array of settings fields specific to this feature.
     * 
     * @return array Settings fields array in format compatible with WordPress Settings API
     */
    public function get_settings_fields();
    
    /**
     * Validate feature settings
     * 
     * @param array $settings Settings to validate
     * @return array Validated and sanitized settings
     */
    public function validate_settings( $settings );
}