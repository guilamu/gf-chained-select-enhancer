# 🔗 Chained Select Enhancer for Gravity Forms

This plugin enhances the functionality of Gravity Forms Chained Selects by adding auto-select capabilities, column hiding options, full-width display for vertical chained selects, CSV export, and automatic updates from GitHub.

![Plugin Screenshot](https://github.com/guilamu/gf-chained-select-enhancer/blob/main/screenshot.png)

## 📋 Description

The Chained Select Enhancer for Gravity Forms adds the following features to Gravity Forms Chained Selects:

1. **⚡ Auto-select**: Automatically selects an option when it's the only choice available.
2. **👁️ Hide Columns**: Allows hiding specific columns in the chained select field.
3. **📏 Full Width**: Makes vertical chained selects full width.
4. **📊 CSV Export**: Export your chained select field choices to CSV format directly from the field settings.
5. **🔄 Automatic Updates**: Seamlessly receive plugin updates directly from GitHub through WordPress's built-in update system.

## ⚙️ Prerequisites

This plugin requires:

1. WordPress
2. Gravity Forms
3. Gravity Forms Chained Selects Add-On

## 📦 Installation

1. Install and activate Gravity Forms.
2. Install and activate the Gravity Forms Chained Selects Add-On.
3. Download the Chained Select Enhancer plugin ZIP file.
4. Go to Plugins > Add New in your WordPress admin area.
5. Click "Upload Plugin" and select the ZIP file you downloaded.
6. Click "Install Now" and then "Activate Plugin".

### 🔄 Automatic Updates

Once installed, the plugin will automatically check for updates from the GitHub repository. When a new version is released:

1. WordPress will notify you of available updates in the Plugins page.
2. You can update the plugin with one click, just like any other WordPress plugin.
3. The plugin uses GitHub releases to deliver updates securely.

## 🚀 Usage

After activation, new options will be available in the Gravity Forms editor for Chained Select fields:

- **⚡ Automatically select when only one option is available**: Enables auto-select functionality.
- **📏 Make vertical chained select full width**: Makes the field full width when in vertical layout.
- **👁️ Hide columns**: When you add a Chained Select field with multiple columns, the plugin now automatically detects all available columns and displays a toggle switch for each one.
- **📊 CSV Export**: In the form editor, select a chained select field. In the field settings panel on the right, scroll down to find the "Export Choices" button.

## 🌍 Translation

To translate the plugin:

1. Use a tool like Poedit to open the `gf-chained-select-enhancer.pot` file in the `languages` folder.
2. Create a new translation and save it as `gf-chained-select-enhancer-{locale}.po` (e.g., `gf-chained-select-enhancer-fr_FR.po` for French).
3. Save the file and Poedit will automatically generate the corresponding `.mo` file.
4. Place both the `.po` and `.mo` files in the `languages` folder of the plugin.

WordPress will automatically use the correct language file based on the site's locale setting.

## 💬 Support

For support, please open an issue on the GitHub repository.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the GNU AGPL.

## 🙏 Acknowledgements

A million thanks to the wizards from [GravityWiz](https://gravitywiz.com/) (David, Samuel, Matt, Saif, you're the bests!) for helping me through the years with anything related to Gravity Forms!

## 📝 Change Log

### Version 1.3 - 2025-11-26
- 🔄 **New feature**: Automatic updates from GitHub
- ✨ Plugin now automatically checks for and installs updates from the GitHub repository
- 🔧 Added Update URI to plugin header for seamless update integration

### Version 1.2 - 2025-11-06
- 📊 **New feature**: CSV Export

### Version 1.1 - 2025-11-05
- 👁️ Column hiding enhancement

### Version 1.01 - 2024-11-29
- 🐛 Hidden lines are now properly hidden

### Version 1.0 - 2024-07-01
- 🎉 Initial version
