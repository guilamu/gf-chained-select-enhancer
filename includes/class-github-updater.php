<?php
/**
 * GitHub Auto-Updater for Chained Select Enhancer
 *
 * Enables automatic updates from GitHub releases for WordPress plugins.
 *
 * @package GF_Chained_Select_Enhancer
 */

if (!defined('ABSPATH')) {
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
     * Format: 'folder-name/main-file.php'
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
    private const PLUGIN_DESCRIPTION = 'Enhances Gravity Forms Chained Selects with auto-select functionality, column hiding options, and XLSX file support.';

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
     * Minimum Gravity Forms version required.
     *
     * @var string
     */
    private const REQUIRES_GF = '2.8';

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

    /**
     * Optional GitHub token for private repos or to avoid rate limits (leave empty for public repos).
     *
     * @var string
     */
    private const GITHUB_TOKEN = '';

    // =========================================================================
    // IMPLEMENTATION
    // =========================================================================

    /**
     * Initialize the updater.
     *
     * @return void
     */
    public static function init(): void {
        add_filter('update_plugins_github.com', array(self::class, 'check_for_update'), 10, 4);
        add_filter('plugins_api', array(self::class, 'plugin_info'), 20, 3);
        add_filter('plugins_api_result', array(self::class, 'finalize_plugin_info'), PHP_INT_MAX, 3);
        add_filter('upgrader_source_selection', array(self::class, 'fix_folder_name'), 10, 4);
        add_action('admin_head', array(self::class, 'plugin_info_css'));
    }

    /**
     * Rebuild the final plugin information object after all earlier filters.
     *
     * Some sites run additional `plugins_api_result` filters that mutate or
     * strip fields such as `sections`. Returning a fresh object at the highest
     * practical priority ensures WordPress core receives the expected shape.
     *
     * @param false|object|array $result Plugin API result.
     * @param string             $action Requested action.
     * @param object             $args   API arguments.
     * @return false|object|array
     */
    public static function finalize_plugin_info($result, $action, $args) {
        if (!self::is_plugin_information_api_request($action, $args)) {
            return $result;
        }

        return self::get_safe_plugin_info_result();
    }

    /**
     * Check whether the current API request is asking for this plugin.
     *
     * @param string $action Requested action.
     * @param mixed  $args   API arguments.
     * @return bool
     */
    private static function is_plugin_information_api_request($action, $args): bool {
        return 'plugin_information' === $action
            && is_object($args)
            && isset($args->slug)
            && self::PLUGIN_SLUG === $args->slug;
    }

    /**
     * Get the active plugin file path relative to the plugins directory.
     *
     * @return string
     */
    private static function get_plugin_file(): string {
        if (defined('GFCS_PLUGIN_FILE') && is_string(GFCS_PLUGIN_FILE) && '' !== GFCS_PLUGIN_FILE) {
            return GFCS_PLUGIN_FILE;
        }

        return self::PLUGIN_FILE;
    }

    /**
     * Get the active plugin directory relative to the plugins directory.
     *
     * @return string
     */
    private static function get_plugin_directory(): string {
        return dirname(self::get_plugin_file());
    }

    /**
     * Build the plugin information object once and return a fresh clone.
     *
     * @return stdClass
     */
    private static function get_safe_plugin_info_result(): stdClass {
        static $plugin_info = null;

        if ($plugin_info instanceof stdClass) {
            return clone $plugin_info;
        }

        try {
            $plugin_info = self::build_plugin_info_result();
        } catch (Throwable $throwable) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '%s plugin details fallback: %s in %s:%d',
                    self::PLUGIN_NAME,
                    $throwable->getMessage(),
                    $throwable->getFile(),
                    $throwable->getLine()
                ));
            }

            $plugin_info = self::build_fallback_plugin_info_result();
        }

        return clone $plugin_info;
    }

    /**
     * Build plugin information for the WordPress details modal.
     *
     * @return stdClass
     */
    private static function build_plugin_info_result(): stdClass {
        $release_data = self::get_release_data();
        $installed_version = defined('GFCS_VERSION') ? GFCS_VERSION : '1.0.0';
        $release_version = $release_data
            ? ltrim($release_data['tag_name'], 'v')
            : '';
        $version = $installed_version;
        $has_update = '' !== $release_version
            && version_compare($release_version, $installed_version, '>');

        if ($has_update) {
            $version = $release_version;
        }

        $result               = new stdClass();
        $result->name         = self::PLUGIN_NAME;
        $result->slug         = self::PLUGIN_SLUG;
        $result->plugin       = self::get_plugin_file();
        $result->version      = $version;
        $result->author       = sprintf('<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER);
        $result->homepage     = sprintf('https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO);
        $result->requires     = self::REQUIRES_WP;
        $result->tested       = get_bloginfo('version');
        $result->requires_php = self::REQUIRES_PHP;
        $result->external     = true;
        $result->banners      = array();
        $result->icons        = array();

        if ($release_data && $has_update) {
            $package_url = self::get_package_url($release_data);

            if ('' !== $package_url) {
                $result->download_link = $package_url;
            }

            if (!empty($release_data['published_at'])) {
                $result->last_updated = $release_data['published_at'];
            }
        }

        $result->sections = self::build_plugin_info_sections(
            self::parse_readme(),
            $release_data,
            $installed_version,
            $version
        );

        return $result;
    }

    /**
     * Build plugin information sections from parsed README content.
     *
     * @param array      $readme            Parsed README sections.
     * @param array|null $release_data      Release data from GitHub.
     * @param string     $installed_version Installed plugin version.
     * @param string     $display_version   Version shown in the modal.
     * @return array
     */
    private static function build_plugin_info_sections(
        array $readme,
        ?array $release_data,
        string $installed_version,
        string $display_version
    ): array {
        $sections = array(
            'description' => !empty($readme['description'])
                ? $readme['description']
                : '<p>' . esc_html(self::PLUGIN_DESCRIPTION) . '</p>',
        );

        if (!empty($readme['installation'])) {
            $sections['installation'] = $readme['installation'];
        }

        if (!empty($readme['faq'])) {
            $sections['faq'] = $readme['faq'];
        }

        $changelog_html = '';

        if (
            is_array($release_data)
            && !empty($release_data['body'])
            && version_compare($installed_version, $display_version, '<')
        ) {
            $changelog_html .= '<h4>' . esc_html($display_version) . '</h4>'
                . self::markdown_to_html((string) $release_data['body']);
        }

        if (!empty($readme['changelog'])) {
            $changelog_html .= $readme['changelog'];
        }

        $sections['changelog'] = !empty($changelog_html)
            ? $changelog_html
            : sprintf(
                '<p>See <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for changelog.</p>',
                esc_attr(self::GITHUB_USER),
                esc_attr(self::GITHUB_REPO)
            );

        return $sections;
    }

    /**
     * Build a small fallback payload if plugin details generation fails.
     *
     * @return stdClass
     */
    private static function build_fallback_plugin_info_result(): stdClass {
        $result               = new stdClass();
        $result->name         = self::PLUGIN_NAME;
        $result->slug         = self::PLUGIN_SLUG;
        $result->plugin       = self::get_plugin_file();
        $result->version      = defined('GFCS_VERSION') ? GFCS_VERSION : '1.0.0';
        $result->author       = sprintf('<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER);
        $result->homepage     = sprintf('https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO);
        $result->requires     = self::REQUIRES_WP;
        $result->tested       = get_bloginfo('version');
        $result->requires_php = self::REQUIRES_PHP;
        $result->external     = true;
        $result->banners      = array();
        $result->icons        = array();
        $result->sections     = array(
            'description' => '<p>' . esc_html(self::PLUGIN_DESCRIPTION) . '</p>',
            'changelog'   => sprintf(
                '<p>See <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for changelog.</p>',
                esc_attr(self::GITHUB_USER),
                esc_attr(self::GITHUB_REPO)
            ),
        );

        return $result;
    }

    /**
     * Determine whether the current request is the plugin details iframe for this plugin.
     *
     * @return bool
     */
    private static function is_plugin_info_request(): bool {
        if (!isset($_GET['tab'], $_GET['plugin'])) {
            return false;
        }

        $tab = sanitize_text_field(wp_unslash($_GET['tab']));
        $plugin = sanitize_text_field(wp_unslash($_GET['plugin']));

        return 'plugin-information' === $tab && self::PLUGIN_SLUG === $plugin;
    }

    /**
     * Get release data from GitHub with caching.
     *
     * @return array|null Release data or null on failure.
     */
    private static function get_release_data(): ?array {
        $release_data = get_transient(self::CACHE_KEY);

        if (false !== $release_data && is_array($release_data)) {
            return $release_data;
        }

        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USER, self::GITHUB_REPO),
            array(
                'user-agent' => 'WordPress/' . self::PLUGIN_SLUG,
                'timeout'    => 15,
                'headers'    => !empty(self::GITHUB_TOKEN)
                    ? array('Authorization' => 'token ' . self::GITHUB_TOKEN)
                    : array(),
            )
        );

        // Handle request errors.
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(self::PLUGIN_NAME . ' Update Error: ' . $response->get_error_message());
            }
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(self::PLUGIN_NAME . " Update Error: HTTP {$response_code}");
            }
            return null;
        }

        // Parse JSON response.
        $release_data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($release_data['tag_name'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(self::PLUGIN_NAME . ' Update Error: No tag_name in release');
            }
            return null;
        }

        // Cache the release data.
        set_transient(self::CACHE_KEY, $release_data, self::CACHE_EXPIRATION);

        return $release_data;
    }

    /**
     * Get the download URL for the plugin package.
     *
     * Prefers custom release assets (e.g., gf-chained-select-enhancer.zip) over
     * GitHub's auto-generated zipball for cleaner folder naming.
     *
     * @param array $release_data Release data from GitHub API.
     * @return string Download URL for the plugin package.
     */
    private static function get_package_url(array $release_data): string {
        // Look for a custom .zip asset (preferred).
        if (!empty($release_data['assets']) && is_array($release_data['assets'])) {
            foreach ($release_data['assets'] as $asset) {
                if (
                    isset($asset['browser_download_url']) &&
                    isset($asset['name']) &&
                    substr($asset['name'], -4) === '.zip'
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
    public static function check_for_update($update, array $plugin_data, string $plugin_file, $locales) {
        // Verify this is our plugin.
        if (self::get_plugin_file() !== $plugin_file) {
            return $update;
        }

        $release_data = self::get_release_data();
        if (null === $release_data) {
            return $update;
        }

        // Clean version (remove 'v' prefix: v1.0.0 -> 1.0.0).
        $new_version = ltrim($release_data['tag_name'], 'v');

        // Compare versions - only return update if newer version exists.
        if (version_compare($plugin_data['Version'], $new_version, '>=')) {
            return $update;
        }

        // Build update object.
        //
        // The keys id, slug, plugin, and new_version are required for reliable
        // compatibility with WordPress core update screens. Omitting them can
        // trigger notices such as "Undefined property: stdClass::$slug" on
        // wp-admin/update-core.php in newer WordPress versions.
        return array(
            'id'            => 'github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'slug'          => self::PLUGIN_SLUG,
            'plugin'        => self::get_plugin_file(),
            'new_version'   => $new_version,
            'version'       => $new_version,
            'package'       => self::get_package_url($release_data),
            'url'           => $release_data['html_url'],
            'tested'        => get_bloginfo('version'),
            'requires_php'  => self::REQUIRES_PHP,
            'compatibility' => new stdClass(),
            'icons'         => array(),
            'banners'       => array(),
        );
    }

    /**
     * Provide plugin information for the WordPress plugin details popup.
     *
     * Reads sections (description, installation, FAQ, changelog) from the
     * local README.md instead of fetching from the GitHub release body.
     * This avoids API rate limits and gives full control over the content.
     *
     * @param false|object|array $res    The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin information or false.
     */
    public static function plugin_info($res, $action, $args) {
        if (!self::is_plugin_information_api_request($action, $args)) {
            return $res;
        }

        return self::get_safe_plugin_info_result();
    }

    /**
     * Inject CSS overrides and optional sidebar info in the plugin-information iframe.
     *
     * wp_kses_post() strips <style> tags from section content, so CSS must be
     * injected via the admin_head hook which fires inside the iframe's <head>.
     *
     * The WordPress sidebar in install_plugin_information() is hardcoded — there
     * is no PHP hook to add custom items. For Gravity Forms add-ons, a small JS
     * snippet inserts a "Requires Gravity Forms" line into the sidebar after the
     * "Requires PHP Version" line.
     */
    public static function plugin_info_css(): void {
        if (!isset($_GET['plugin'], $_GET['tab'])) {
            return;
        }
        if ('plugin-information' !== sanitize_text_field(wp_unslash($_GET['tab']))
            || self::PLUGIN_SLUG !== sanitize_text_field(wp_unslash($_GET['plugin']))) {
            return;
        }

        // CSS pattern variables for the banner background.
        $pattern_css = '--s: 27px;'
            . '--c1: #b2b2b2;'
            . '--c2: #ffffff;'
            . '--c3: #d9d9d9;'
            . '--_g: var(--c3) 0 120deg, #0000 0;';

        $pattern_bg = 'conic-gradient(from -60deg at 50% calc(100%/3), var(--_g)),'
            . 'conic-gradient(from 120deg at 50% calc(200%/3), var(--_g)),'
            . 'conic-gradient(from 60deg at calc(200%/3), var(--c3) 60deg, var(--c2) 0 120deg, #0000 0),'
            . 'conic-gradient(from 180deg at calc(100%/3), var(--c1) 60deg, var(--_g)),'
            . 'linear-gradient(90deg, var(--c1) calc(100%/6), var(--c2) 0 50%,'
            . 'var(--c1) 0 calc(500%/6), var(--c2) 0)';

        echo '<style>'
            . '#plugin-information-title.with-banner {'
            .   $pattern_css
            .   'background: ' . $pattern_bg . ' !important;'
            .   'background-size: calc(1.732 * var(--s)) var(--s) !important;'
            . '}'
            . '#plugin-information-title.with-banner h2 {'
            .   'position: relative;'
            .   'font-family: "Helvetica Neue", sans-serif;'
            .   'display: inline-block;'
            .   'font-size: 30px;'
            .   'line-height: 1.68;'
            .   'box-sizing: border-box;'
            .   'max-width: 100%;'
            .   'padding: 0 15px;'
            .   'margin-top: 174px;'
            .   'color: #fff;'
            .   'background: rgba(29, 35, 39, 0.9);'
            .   'text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);'
            .   'box-shadow: 0 0 30px rgba(255, 255, 255, 0.1);'
            .   'border-radius: 8px;'
            . '}'
            . '#section-holder .section h2 { margin: 1.5em 0 0.5em; clear: none; }'
            . '#section-holder .section h3 { margin: 1.5em 0 0.5em; }'
            . '#section-holder .section > :first-child { margin-top: 0; }'
            . '.md-table { display: table; width: 100%; border-collapse: collapse; margin: 1em 0; font-size: 13px; }'
            . '.md-tr { display: table-row; }'
            . '.md-tr > span { display: table-cell; padding: 6px 10px; border: 1px solid #ddd; vertical-align: top; }'
            . '.md-th > span { font-weight: 600; background: #f5f5f5; }'
            . '</style>';

        // Gravity Forms add-on: add sidebar line for required GF version
        // and add the with-banner class to enable the CSS pattern banner.
        $gf_version = esc_html(self::REQUIRES_GF);
        echo '<script>'
            . 'document.addEventListener("DOMContentLoaded",function(){'
            // Add with-banner class to trigger the CSS pattern background.
            . 'var title=document.getElementById("plugin-information-title");'
            . 'if(title){title.classList.add("with-banner");}'
            // Add Gravity Forms requirement to the sidebar.
            . 'var items=document.querySelectorAll(".fyi ul li");'
            . 'var php=null;'
            . 'for(var i=0;i<items.length;i++){if(items[i].textContent.indexOf("Requires PHP")!==-1){php=items[i];break;}}'
            . 'if(!php)return;'
            . 'var li=document.createElement("li");'
            . 'li.innerHTML="<strong>Requires Gravity Forms:<\/strong> ' . $gf_version . ' or higher";'
            . 'php.parentNode.insertBefore(li,php.nextSibling);'
            . '});'
            . '</script>';
    }

    // ------------------------------------------------------------------
    // README.md parsing
    // ------------------------------------------------------------------

    /**
     * Parse the local README.md into description, installation, FAQ and changelog HTML.
     *
     * Splits the Markdown by ## headers and categorizes each section:
     * - "Installation", "FAQ", "Changelog" → their own tabs.
     * - Utility sections (Requirements, License, Project Structure,
     *   Acknowledgements) → excluded.
     * - Everything else → appended to the description tab.
     *
     * @return array{description: string, installation: string, faq: string, changelog: string}
     */
    private static function parse_readme(): array {
        $readme_path = WP_PLUGIN_DIR . '/' . self::get_plugin_directory() . '/README.md';

        if (!file_exists($readme_path)) {
            return array();
        }

        $content = file_get_contents($readme_path);
        if (false === $content) {
            return array();
        }

        // Remove the main title line (# Title).
        $content = preg_replace('/^#\s+[^\n]+\n*/m', '', $content, 1);

        // Sections that are NOT part of the description tab.
        $utility_sections = array(
            'changelog', 'requirements', 'installation', 'faq',
            'project structure', 'acknowledgements', 'license',
        );

        // Split content by ## headers.
        $parts = preg_split('/^##\s+/m', $content);

        $description  = trim($parts[0] ?? '');
        $installation = '';
        $faq          = '';
        $changelog    = '';

        for ($i = 1, $count = count($parts); $i < $count; $i++) {
            $lines = explode("\n", $parts[$i], 2);
            $title = strtolower(trim($lines[0]));
            $body  = trim($lines[1] ?? '');

            if ('installation' === $title) {
                $installation .= $body . "\n\n";
            } elseif ('faq' === $title) {
                $faq .= $body . "\n\n";
            } elseif ('changelog' === $title) {
                $changelog .= $body . "\n\n";
            } elseif (!in_array($title, $utility_sections, true)) {
                // Include in description (e.g. "Auto-Select Features", "Key Features").
                $description .= "\n\n## " . trim($lines[0]) . "\n" . $body;
            }
        }

        return array(
            'description'  => self::markdown_to_html(trim($description)),
            'installation' => self::markdown_to_html(trim($installation)),
            'faq'          => self::markdown_to_html(trim($faq)),
            'changelog'    => self::markdown_to_html(trim($changelog)),
        );
    }

    /**
     * Convert Markdown to HTML using Parsedown.
     *
     * Images are stripped before conversion since they are not useful
    * inside the WordPress plugin-information modal. This includes both
    * Markdown image syntax and raw HTML <img> blocks often used for README logos.
     *
     * IMPORTANT: WordPress install_plugin_information() sanitizes section
     * content with wp_kses() using $plugins_allowedtags — which does NOT
     * include <table>, <tr>, <th>, <td>. Tables generated by Parsedown
     * are therefore converted to <div>/<span> structures via tables_to_divs()
     * and styled with CSS injected through admin_head (see plugin_info_css()).
     */
    private static function markdown_to_html(string $markdown): string {
        if ('' === $markdown) {
            return '';
        }

        // Remove images (not useful in the modal).
        $markdown = preg_replace('/!\[[^\]]*\]\([^\)]+\)/', '', $markdown);
        $markdown = preg_replace('/<p\b[^>]*>\s*(?:(?:<a\b[^>]*>\s*)?<img\b[^>]*>\s*(?:<\/a>\s*)?)+<\/p>\s*/is', '', $markdown);
        $markdown = preg_replace('/(?:<a\b[^>]*>\s*)?<img\b[^>]*>\s*(?:<\/a>)?/i', '', $markdown);

        if (!class_exists('Parsedown')) {
            $parsedown_path = __DIR__ . '/Parsedown.php';

            if (!file_exists($parsedown_path)) {
                error_log(sprintf(
                    '%s plugin details fallback: missing Parsedown.php at %s',
                    self::PLUGIN_NAME,
                    $parsedown_path
                ));

                return wpautop(esc_html($markdown));
            }

            require_once $parsedown_path;
        }

        if (!class_exists('Parsedown')) {
            error_log(sprintf(
                '%s plugin details fallback: Parsedown class unavailable after load',
                self::PLUGIN_NAME
            ));

            return wpautop(esc_html($markdown));
        }

        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true);

        $html = $parsedown->text($markdown);

        // Convert <table> to wp_kses-safe <div>/<span> structures.
        $html = self::tables_to_divs($html);

        return $html;
    }

    /**
     * Convert HTML tables to div/span structures compatible with wp_kses.
     *
     * WordPress plugin info modal only allows: div (with class), span (with class),
     * p, strong, em, code, a, ul, ol, li, h1-h6, pre, br, img.
     * Table elements (table, thead, tbody, tr, th, td) are stripped entirely.
     *
     * This method replaces <table> with CSS-table divs:
     * - <div class="md-table">  → display: table
     * - <div class="md-tr">     → display: table-row
     * - <div class="md-tr md-th"> → header row (bold + background)
     * - <span>                  → display: table-cell
     *
     * The corresponding CSS is injected by plugin_info_css() via admin_head.
     *
     * @param string $html HTML containing <table> elements.
     * @return string HTML with tables replaced by styled div/span.
     */
    private static function tables_to_divs(string $html): string {
        return preg_replace_callback('/<table>(.*?)<\/table>/s', function ($m) {
            $table_html = $m[1];
            $output = '<div class="md-table">';

            // Extract all rows (from thead and tbody).
            preg_match_all('/<tr>(.*?)<\/tr>/s', $table_html, $rows);

            foreach ($rows[1] as $idx => $row_content) {
                $is_header = (0 === $idx && strpos($table_html, '<thead>') !== false);
                $row_class = $is_header ? 'md-tr md-th' : 'md-tr';

                // Extract cell contents (th or td).
                preg_match_all('/<t[hd]>(.*?)<\/t[hd]>/s', $row_content, $cells);

                $output .= '<div class="' . $row_class . '">';
                foreach ($cells[1] as $cell) {
                    $output .= '<span>' . $cell . '</span>';
                }
                $output .= '</div>';
            }

            $output .= '</div>';
            return $output;
        }, $html);
    }

    /**
     * Rename the extracted folder to match the expected plugin folder name.
     *
     * GitHub zipball contains a folder named "username-repo-hash" which breaks
     * WordPress plugin updates. This filter renames it to the correct folder name.
     *
     * @param string      $source        File source location.
     * @param string      $remote_source Remote file source location.
     * @param WP_Upgrader $upgrader      WP_Upgrader instance.
     * @param array       $hook_extra    Extra arguments passed to hooked filters.
     * @return string|WP_Error The corrected source path or WP_Error on failure.
     */
    public static function fix_folder_name($source, $remote_source, $upgrader, $hook_extra) {
        global $wp_filesystem;

        // Only process plugin updates.
        if (!isset($hook_extra['plugin'])) {
            return $source;
        }

        // Check if this is our plugin.
        if (self::get_plugin_file() !== $hook_extra['plugin']) {
            return $source;
        }

        // Expected folder name (extract from PLUGIN_FILE).
        $correct_folder = self::get_plugin_directory();

        // Get the current folder name from source path.
        $source_folder = basename(untrailingslashit($source));

        // If already correct, no action needed.
        if ($source_folder === $correct_folder) {
            return $source;
        }

        // Build new source path with correct folder name.
        $new_source = trailingslashit($remote_source) . $correct_folder . '/';

        // Rename the folder.
        if ($wp_filesystem && $wp_filesystem->move($source, $new_source)) {
            return $new_source;
        }

        // Attempt copy+delete fallback if move failed.
        if ($wp_filesystem && $wp_filesystem->copy($source, $new_source, true) && $wp_filesystem->delete($source, true)) {
            return $new_source;
        }

        // Log for debugging without fatals in production.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '%s updater: failed to rename update folder from %s to %s',
                self::PLUGIN_NAME,
                $source,
                $new_source
            ));
        }

        return new WP_Error(
            'rename_failed',
            __('Unable to rename the update folder. Please retry or update manually.', self::TEXT_DOMAIN)
        );
    }
}
