<?php

namespace DCMM\Premium;

/**
 * Premium Feature Manager
 * 
 * Manages the registration, activation, and coordination of premium features.
 * Provides a centralized system for handling premium functionality.
 * 
 * @since 1.1.0
 */
class Premium_Manager {
    
    /**
     * @var Premium_Manager Singleton instance
     */
    private static $instance = null;
    
    /**
     * @var array Registered premium features
     */
    private $features = array();
    
    /**
     * @var array Active premium features
     */
    private $active_features = array();
    
    /**
     * Get singleton instance
     * 
     * @return Premium_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the premium manager
     */
    private function init() {
        add_action( 'init', array( $this, 'load_features' ), 5 );
        add_action( 'init', array( $this, 'initialize_active_features' ), 10 );
        add_action( 'admin_init', array( $this, 'initialize_active_features' ) );
        
        // Load features immediately if we're in admin and looking at settings
        if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === 'dcmm_settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'premium' ) {
            add_action( 'admin_init', array( $this, 'force_load_features' ), 1 );
        }
    }
    
    /**
     * Force load features for admin settings page
     */
    public function force_load_features() {
        if ( empty( $this->features ) ) {
            $this->load_features();
        }
    }
    
    /**
     * Register a premium feature
     * 
     * @param Premium_Feature_Interface $feature The premium feature to register
     * @return bool True on successful registration
     */
    public function register_feature( Premium_Feature_Interface $feature ) {
        $feature_id = $feature->get_feature_id();
        
        if ( isset( $this->features[ $feature_id ] ) ) {
            return false;
        }
        
        $this->features[ $feature_id ] = $feature;
        
        return true;
    }
    
    /**
     * Get all registered features
     * 
     * @return array Array of Premium_Feature_Interface objects
     */
    public function get_all_features() {
        return $this->features;
    }
    
    /**
     * Get a specific feature by ID
     * 
     * @param string $feature_id The feature ID to retrieve
     * @return Premium_Feature_Interface|null Feature object or null if not found
     */
    public function get_feature( $feature_id ) {
        return isset( $this->features[ $feature_id ] ) ? $this->features[ $feature_id ] : null;
    }
    
    /**
     * Check if a feature is enabled
     * 
     * @param string $feature_id The feature ID to check
     * @return bool True if feature is enabled
     */
    public function is_feature_enabled( $feature_id ) {
        $feature = $this->get_feature( $feature_id );
        if ( ! $feature ) {
            return false;
        }
        
        return $feature->is_enabled();
    }
    
    /**
     * Enable a premium feature
     * 
     * @param string $feature_id The feature ID to enable
     * @return bool True on success, false on failure
     */
    public function enable_feature( $feature_id ) {
        $feature = $this->get_feature( $feature_id );
        if ( ! $feature ) {
            return false;
        }
        
        if ( ! $feature->check_dependencies() ) {
            return false;
        }
        
        // Update option to enable feature
        $enabled_features = get_option( 'dcmm_premium_features_enabled', array() );
        $enabled_features[ $feature_id ] = true;
        update_option( 'dcmm_premium_features_enabled', $enabled_features );
        
        // Initialize the feature
        if ( $feature->initialize() ) {
            $this->active_features[ $feature_id ] = $feature;
            return true;
        }
        
        return false;
    }
    
    /**
     * Disable a premium feature
     * 
     * @param string $feature_id The feature ID to disable
     * @return bool True on success
     */
    public function disable_feature( $feature_id ) {
        $feature = $this->get_feature( $feature_id );
        if ( ! $feature ) {
            return false;
        }
        
        // Update option to disable feature
        $enabled_features = get_option( 'dcmm_premium_features_enabled', array() );
        unset( $enabled_features[ $feature_id ] );
        update_option( 'dcmm_premium_features_enabled', $enabled_features );
        
        // Deactivate the feature
        $feature->deactivate();
        unset( $this->active_features[ $feature_id ] );
        
        return true;
    }
    
    /**
     * Load and register all available premium features
     */
    public function load_features() {
        // Prevent multiple loads
        static $loaded = false;
        if ( $loaded ) {
            return;
        }
        $loaded = true;
        
        // Include available premium features
        $features_dir = DCMM_PATH . '/includes/premium/integrations/';
        
        if ( is_dir( $features_dir ) ) {
            
            // Recursively find all class-*.php files in $features_dir and subdirectories
            $feature_files = array();
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $features_dir )
            );
            foreach ( $iterator as $file ) {
                if ( $file->isFile() && preg_match( '/class-.*\.php$/', $file->getFilename() ) ) {
                    $feature_files[] = $file->getPathname();
                }
            }
            foreach ( $feature_files as $file ) {
                require_once $file;
            }
        }
        
        // Allow other plugins/themes to register premium features
        do_action( 'dcmm_register_premium_features', $this );
    }
    
    /**
     * Initialize active premium features
     */
    public function initialize_active_features() {
        // Prevent multiple initializations
        static $initialized = false;
        if ( $initialized ) {
            return;
        }
        $initialized = true;
        
        $enabled_features = get_option( 'dcmm_premium_features_enabled', array() );
        
        foreach ( $enabled_features as $feature_id => $enabled ) {
            if ( $enabled && isset( $this->features[ $feature_id ] ) ) {
                $feature = $this->features[ $feature_id ];
                
                if ( $feature->check_dependencies() && $feature->initialize() ) {
                    $this->active_features[ $feature_id ] = $feature;
                } else {
                    $this->disable_feature( $feature_id );
                }
            }
        }
    }
    
    /**
     * Get all active features
     * 
     * @return array Array of active Premium_Feature_Interface objects
     */
    public function get_active_features() {
        return $this->active_features;
    }
    
    /**
     * Get feature settings for admin interface
     * 
     * @return array Settings data for all features
     */
    public function get_features_settings_data() {
        $features_data = array();
        
        foreach ( $this->features as $feature_id => $feature ) {
            $features_data[ $feature_id ] = array(
                'name' => $feature->get_feature_name(),
                'description' => $feature->get_feature_description(),
                'enabled' => $feature->is_enabled(),
                'dependencies_met' => $feature->check_dependencies(),
                'dependency_errors' => $feature->get_dependency_errors(),
                'settings_fields' => $feature->get_settings_fields(),
            );
        }
        
        return $features_data;
    }
}