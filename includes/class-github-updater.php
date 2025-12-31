<?php
/**
 * GitHub Auto-Updater for Chained Select Enhancer
 *
 * Enables automatic updates from GitHub releases for WordPress plugins.
 *
 * @package GF_Chained_Select_Enhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GFCS_GitHub_Updater
 *
 * Handles automatic updates from GitHub releases.
 */
class GFCS_GitHub_Updater {

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * GitHub username or organization.
     *
     * @var string
     */
    private const GITHUB_USER = 'guilamu';

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private const GITHUB_REPO = 'gf-chained-select-enhancer';

    /**
     * Plugin file path relative to plugins directory.
     *
     * @var string
     */
    private const PLUGIN_FILE = 'gf-chained-select-enhancer/gf-chained-select-enhancer.php';

    /**
     * Plugin slug.
     *
     * @var string
     */
    private const PLUGIN_SLUG = 'gf-chained-select-enhancer';

    /**
     * Plugin display name.
     *
     * @var string
     */
    private const PLUGIN_NAME = 'Chained Select Enhancer for Gravity Forms';

    /**
     * Plugin description.
     *
     * @var string
     */
    private const PLUGIN_DESCRIPTION = 'Enhances Gravity Forms Chained Selects with auto-select functionality, column hiding options, and CSV export.';

    /**
     * Minimum WordPress version required.
     *
     * @var string
     */
    private const REQUIRES_WP = '5.8';

    /**
     * WordPress version tested up to.
     *
     * @var string
     */
    private const TESTED_WP = '6.7';

    /**
     * Minimum PHP version required.
     *
     * @var string
     */
    private const REQUIRES_PHP = '7.4';

    /**
     * Text domain for translations.
     *
     * @var string
     */
    private const TEXT_DOMAIN = 'gf-chained-select-enhancer';

    // =========================================================================
    // CACHE SETTINGS
    // =========================================================================

    /**
     * Cache key for GitHub release data.
     *
     * @var string
     */
    private const CACHE_KEY = 'gfcs_github_release';

    /**
     * Cache expiration in seconds (12 hours).
     *
     * @var int
     */
    private const CACHE_EXPIRATION = 43200;

    // =========================================================================
    // IMPLEMENTATION
    // =========================================================================

    /**
     * Initialize the updater.
     *
     * @return void
     */
    public static function init(): void {
        add_filter( 'update_plugins_github.com', array( self::class, 'check_for_update' ), 10, 4 );
        add_filter( 'plugins_api', array( self::class, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_install_package_result', array( self::class, 'fix_folder_name' ), 10, 2 );
    }

    /**
     * Get release data from GitHub with caching.
     *
     * @return array|null Release data or null on failure.
     */
    private static function get_release_data(): ?array {
        $release_data = get_transient( self::CACHE_KEY );

        if ( false !== $release_data && is_array( $release_data ) ) {
            return $release_data;
        }

        $response = wp_remote_get(
            sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USER, self::GITHUB_REPO ),
            array(
                'user-agent' => 'WordPress/' . self::PLUGIN_SLUG,
                'timeout'    => 10,
            )
        );

        // Handle request errors.
        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( self::PLUGIN_NAME . ' Update Error: ' . $response->get_error_message() );
            }
            return null;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( self::PLUGIN_NAME . " Update Error: HTTP {$response_code}" );
            }
            return null;
        }

        // Parse JSON response.
        $release_data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $release_data['tag_name'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( self::PLUGIN_NAME . ' Update Error: No tag_name in release' );
            }
            return null;
        }

        // Cache the release data.
        set_transient( self::CACHE_KEY, $release_data, self::CACHE_EXPIRATION );

        return $release_data;
    }

    /**
     * Get the download URL for the plugin package.
     *
     * Prefers custom release assets over GitHub's auto-generated zipball.
     *
     * @param array $release_data Release data from GitHub API.
     * @return string Download URL for the plugin package.
     */
    private static function get_package_url( array $release_data ): string {
        // Look for a custom .zip asset (preferred).
        if ( ! empty( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
            foreach ( $release_data['assets'] as $asset ) {
                if (
                    isset( $asset['browser_download_url'] ) &&
                    isset( $asset['name'] ) &&
                    str_ends_with( $asset['name'], '.zip' )
                ) {
                    return $asset['browser_download_url'];
                }
            }
        }

        // Fallback to GitHub's auto-generated zipball.
        return $release_data['zipball_url'] ?? '';
    }

    /**
     * Check for plugin updates from GitHub.
     *
     * @param array|false $update      The plugin update data.
     * @param array       $plugin_data Plugin headers.
     * @param string      $plugin_file Plugin file path.
     * @param array       $locales     Installed locales.
     * @return array|false Updated plugin data or false.
     */
    public static function check_for_update( $update, array $plugin_data, string $plugin_file, $locales ) {
        // Verify this is our plugin.
        if ( self::PLUGIN_FILE !== $plugin_file ) {
            return $update;
        }

        // Skip if update already found.
        if ( ! empty( $update ) ) {
            return $update;
        }

        $release_data = self::get_release_data();
        if ( null === $release_data ) {
            return $update;
        }

        // Clean version (remove 'v' prefix: v1.0.0 -> 1.0.0).
        $new_version = ltrim( $release_data['tag_name'], 'v' );

        // Compare versions - only return update if newer version exists.
        if ( version_compare( $plugin_data['Version'], $new_version, '>=' ) ) {
            return false;
        }

        // Build update object.
        return array(
            'slug'         => self::PLUGIN_SLUG,
            'plugin'       => $plugin_file,
            'version'      => $new_version,
            'package'      => self::get_package_url( $release_data ),
            'url'          => $release_data['html_url'],
            'tested'       => self::TESTED_WP,
            'requires_php' => self::REQUIRES_PHP,
            'icons'        => array(),
            'banners'      => array(),
        );
    }

    /**
     * Provide plugin information for the WordPress plugin details popup.
     *
     * @param false|object|array $res    The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin information or false.
     */
    public static function plugin_info( $res, $action, $args ) {
        // Only handle plugin_information requests.
        if ( 'plugin_information' !== $action ) {
            return $res;
        }

        // Check this is our plugin.
        if ( ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
            return $res;
        }

        $release_data = self::get_release_data();
        if ( null === $release_data ) {
            return $res;
        }

        $new_version = ltrim( $release_data['tag_name'], 'v' );

        // Build response object.
        $res                 = new stdClass();
        $res->name           = self::PLUGIN_NAME;
        $res->slug           = self::PLUGIN_SLUG;
        $res->version        = $new_version;
        $res->author         = sprintf( '<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER );
        $res->homepage       = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
        $res->download_link  = self::get_package_url( $release_data );
        $res->requires       = self::REQUIRES_WP;
        $res->tested         = self::TESTED_WP;
        $res->requires_php   = self::REQUIRES_PHP;
        $res->last_updated   = $release_data['published_at'] ?? '';
        $res->sections       = array(
            'description' => self::PLUGIN_DESCRIPTION,
            'changelog'   => ! empty( $release_data['body'] )
                ? '<h4>' . esc_html( $new_version ) . '</h4>' . wp_kses_post( nl2br( $release_data['body'] ) )
                : sprintf(
                    'See <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for changelog.',
                    self::GITHUB_USER,
                    self::GITHUB_REPO
                ),
        );

        return $res;
    }

    /**
     * Fix the plugin folder name after installation from GitHub.
     *
     * @param array $result     Installation result data.
     * @param array $hook_extra Extra arguments passed to the upgrader.
     * @return array Modified result data.
     */
    public static function fix_folder_name( $result, $hook_extra ) {
        global $wp_filesystem;

        // Only process plugin updates.
        if ( ! isset( $hook_extra['plugin'] ) ) {
            return $result;
        }

        // Check if this is our plugin.
        if ( self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
            return $result;
        }

        // Get the destination where WordPress installed the plugin.
        if ( ! isset( $result['destination'] ) ) {
            return $result;
        }

        $destination      = $result['destination'];
        $destination_name = basename( untrailingslashit( $destination ) );

        // If already correct, no action needed.
        if ( self::PLUGIN_SLUG === $destination_name ) {
            return $result;
        }

        // Build new destination path.
        $correct_destination = trailingslashit( dirname( $destination ) ) . self::PLUGIN_SLUG;

        // If a folder with the correct name already exists, remove it first.
        if ( $wp_filesystem && $wp_filesystem->exists( $correct_destination ) ) {
            $wp_filesystem->delete( $correct_destination, true );
        }

        // Move the downloaded plugin to the correct folder name.
        if ( $wp_filesystem && $wp_filesystem->move( $destination, $correct_destination ) ) {
            $result['destination']      = $correct_destination;
            $result['destination_name'] = self::PLUGIN_SLUG;
        }

        return $result;
    }
}
