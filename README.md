# Chained Select Enhancer for Gravity Forms

This plugin enhances the functionality of Gravity Forms Chained Selects by adding auto-select capabilities, column hiding options, and full-width display for vertical chained selects.

![Plugin Screenshot](https://github.com/guilamu/gf-chained-select-enhancer/blob/main/screenshot.png)

## Description

The Chained Select Enhancer for Gravity Forms adds the following features to Gravity Forms Chained Selects:

1. **Auto-select**: Automatically selects an option when it's the only choice available.
2. **Hide Columns**: Allows hiding specific columns in the chained select field.
3. **Full Width**: Makes vertical chained selects full width.

## Prerequisites

This plugin requires:

1. WordPress
2. Gravity Forms
3. Gravity Forms Chained Selects Add-On

## Installation

1. Install and activate Gravity Forms.
2. Install and activate the Gravity Forms Chained Selects Add-On.
3. Download the Chained Select Enhancer plugin ZIP file.
4. Go to Plugins > Add New in your WordPress admin area.
5. Click "Upload Plugin" and select the ZIP file you downloaded.
6. Click "Install Now" and then "Activate Plugin".

## Usage

After activation, new options will be available in the Gravity Forms editor for Chained Select fields:

- **Automatically select when only one option is available**: Enables auto-select functionality.
- **Make vertical chained select full width**: Makes the field full width when in vertical layout.
- **Hide columns**: Enter comma-separated column numbers to hide specific columns.

## Translation

To translate the plugin:

1. Use a tool like Poedit to open the `gf-chained-select-enhancer.pot` file in the `languages` folder.
2. Create a new translation and save it as `gf-chained-select-enhancer-{locale}.po` (e.g., `gf-chained-select-enhancer-fr_FR.po` for French).
3. Save the file and Poedit will automatically generate the corresponding `.mo` file.
4. Place both the `.po` and `.mo` files in the `languages` folder of the plugin.

WordPress will automatically use the correct language file based on the site's locale setting.

## Support

For support, please open an issue on the GitHub repository.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Planned feature (help needed!)

This one is beyond my capabilities and I guess I won't be able to add it alone so any help is more than welcome:
- Download the uploaded csv file (storing it in the WordPress /uploads directory and add a href on the name of the file)

## License

This project is licensed under the GPL v2 or later.

## Acknowledgements

A million thanks to the wizards from [GravityWiz](https://gravitywiz.com/) (David, Samuel, Matt, Saif, you're the bests!) for helping me through the years with anything related to Gravity Forms!

## Change Log

* 2024-07-01 -- 1.0
  * Initial version

* 2024-11-29 -- 1.01
  * Hidden lines are now properly hidden
