# Chained Select Enhancer for Gravity Forms

Enhances Gravity Forms Chained Selects with auto-select functionality, column hiding options, XLSX import support, and CSV export.

## Auto-Select Features

- Automatically select options when only one choice is available
- Works seamlessly with multi-level chained selects
- Reduces user clicks for single-path selections

## Display Customization

- Hide specific columns from the chained select interface
- Toggle switches for each column in the form editor
- Make vertical chained selects display full width

## XLSX Import Support

- Import chained select choices from XLSX files (in addition to CSV)
- Uses native PHP ZipArchive - no external dependencies
- Supports Excel workbooks created by Microsoft Excel, LibreOffice, Google Sheets

## Export Capabilities

- Export all chained select choices to CSV format
- Direct download from field settings panel
- Generates all possible combinations automatically

## Key Features

- **Multilingual:** Works with content in any language
- **Translation-Ready:** All strings are internationalized
- **Secure:** Proper nonce verification and data sanitization
- **GitHub Updates:** Automatic updates from GitHub releases

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Gravity Forms 2.5 or higher
- Gravity Forms Chained Selects Add-On

## Installation

1. Upload the `gf-chained-select-enhancer` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Edit any form with a Chained Select field
4. Configure options in the field settings panel

## FAQ

### How do I enable auto-select?

In the form editor, select your Chained Select field. In the field settings panel, check "Automatically select when only one option is available".

### How do I hide columns?

In the field settings, use the toggle switches under "Hide Columns" to show/hide specific columns. Hidden columns are still processed but not displayed to users.

### How do I export choices to CSV?

Select your Chained Select field in the form editor. Click the "Export Choices" button in the field settings panel. The CSV will download automatically.

### Can I customize the auto-update behavior?

Yes, the plugin caches GitHub API responses for 12 hours. Updates are checked automatically through WordPress's standard update system.

## Project Structure

```
.
├── gf-chained-select-enhancer.php    # Main plugin file (bootstrap)
├── uninstall.php                     # Cleanup on uninstall
├── README.md
├── assets
│   ├── css
│   │   └── admin.css                 # Admin toggle switch styles
│   └── js
│       └── admin.js                  # Admin field settings scripts
├── includes
│   ├── class-gf-chained-select-enhancer.php  # Main functionality
│   ├── class-github-updater.php      # GitHub auto-updates
│   ├── class-import-handler.php      # XLSX import handler
│   └── class-xlsx-parser.php         # XLSX file parser
└── languages
    ├── gf-chained-select-enhancer-fr_FR.mo   # French translation (binary)
    ├── gf-chained-select-enhancer-fr_FR.po   # French translation (source)
    └── gf-chained-select-enhancer.pot        # Translation template
```

## Changelog

### 1.8.0
- **Security:** Fixed XXE (XML External Entity) vulnerability in XLSX parser — added `LIBXML_NONET` flag and entity loader protection
- **Security:** Added authorization capability check (`current_user_can`) to XLSX upload handler — nonce alone is not authorization
- **Security:** Fixed XSS via unescaped CSS output — now sanitized with `wp_strip_all_tags()`
- **Security:** Fixed DOM-based XSS in admin JS — replaced `innerHTML` with safe DOM methods
- **Security:** Replaced internal `wp_kses_hook()`/`wp_kses_split()` calls with public `wp_kses()` API
- **Security:** Added `Content-Type: application/json` header to XLSX upload JSON response
- **Security:** Added `sanitize_file_name()` and `X-Content-Type-Options: nosniff` to CSV export
- **Security:** Added MIME validation for XLSX uploads — verifies ZIP structure before processing
- **Security:** Nonce and AJAX URL are now only exposed on Gravity Forms editor pages

### 1.7.2
- **Fixed:** PHP 7.4 compatibility — replaced `str_ends_with()` (PHP 8+) with `substr()` in GitHub updater
- **Fixed:** Off-by-one loop in XLSX import `array_values_recursive()`
- **Fixed:** License mismatch between plugin header and README (now AGPL-3.0 everywhere)

### 1.7.1
- **New:** Plugin description translation support in WordPress plugins list
- **Improved:** Updated translation files with plugin metadata

### 1.7.0
- **New:** Guilamu Bug Reporter integration for easy bug reporting
- **Improved:** Code cleanup and optimization

### 1.6.0
- **New:** XLSX file import support for chained select choices
- **New:** Native PHP ZipArchive-based XLSX parser (no external dependencies)

### 1.5.3
- **Fixed:** Fix spacing issue in backend editor when labels are hidden (removes excessive margin) because of main plugin update to 1.8.1.

### 1.5.2
- **Fixed:** Full width chained selects now display correctly on initial form editor load

### 1.5.1
- **Fixed:** Full width vertical chained select preview now works correctly in form editor

### 1.5.0
- **Improved:** Toggle switches now match native Gravity Forms toggle styling

### 1.4.0
- **New:** Modular architecture with separate class files
- **New:** Externalized CSS and JavaScript assets
- **Improved:** Security with proper nonce/data sanitization
- **Improved:** Performance by loading CSS only when forms render
- **Improved:** GitHub API caching (12-hour transient)
- **Fixed:** CSV export now streams directly (no public file storage)

### 1.3
- **New:** Automatic updates from GitHub releases
- **New:** Update URI integration for seamless WordPress updates

### 1.2
- **New:** CSV export functionality for chained select choices

### 1.1
- **Improved:** Column hiding with toggle switches

### 1.0.1
- **Fixed:** Hidden columns now properly hidden on frontend

### 1.0.0
- Initial release
- Auto-select when single option available
- Column hiding options
- Full-width vertical display option

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>
