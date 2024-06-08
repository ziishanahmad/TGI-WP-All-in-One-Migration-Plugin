# TGI WP All-in-One Migration Plugin

**Plugin Name**: TGI WP All-in-One Migration Plugin
**Description**: A WordPress plugin to export and import your entire WordPress site, including the database and all files, with an easy-to-use interface.
**Version**: 1.5
**Author**: Zeeshan Ahmad
**License**: MIT

## Description

The TGI WP All-in-One Migration Plugin allows you to easily export and import your entire WordPress site. The plugin handles both the files and the database, ensuring that your site is fully backed up and can be restored or moved to another location seamlessly.

### Features

- Export your entire WordPress site including files and database (except the options table).
- Import the site from a backup file, replacing the existing site's files and database (except the options table).
- View logs of export and import processes for transparency.
- Clear logs directly from the plugin's interface.
- Exclude the plugin's own directory from the export.
- Handles large files and long execution times gracefully.

## Installation

### Method 1: From the WordPress Dashboard

1. Go to your WordPress admin dashboard.
2. Navigate to **Plugins > Add New**.
3. Search for "TGI WP All-in-One Migration Plugin".
4. Click **Install Now** and then **Activate** the plugin.

### Method 2: Uploading in WordPress Dashboard

1. Download the plugin zip file from the [GitHub releases page](https://github.com/yourusername/tgi-wp-all-in-one-migration-plugin/releases).
2. Go to your WordPress admin dashboard.
3. Navigate to **Plugins > Add New**.
4. Click **Upload Plugin** and choose the downloaded zip file.
5. Click **Install Now** and then **Activate** the plugin.

### Method 3: Manual Installation

1. Download the plugin zip file from the [GitHub releases page](https://github.com/yourusername/tgi-wp-all-in-one-migration-plugin/releases) and extract it.
2. Upload the extracted folder to the `/wp-content/plugins/` directory using FTP or your hosting file manager.
3. Go to your WordPress admin dashboard.
4. Navigate to **Plugins** and activate the "TGI WP All-in-One Migration Plugin".

## Usage

### Exporting Your Site

1. Navigate to **TGI WP Migration > Export Site**.
2. Click the **Export Site** button.
3. The plugin will create a backup of your site including all files and the database (excluding the options table).
4. Once the export is complete, you can download the generated backup file from the list of existing backups.

### Importing Your Site

1. Navigate to **TGI WP Migration > Import Site**.
2. Upload a backup file or select an existing backup file from the list.
3. Click the **Import Site** button and confirm the action.
4. The plugin will import the backup, replacing the existing site's files and database (excluding the options table).
5. Wait for the import process to complete. You can monitor the progress in the log section.

### Viewing and Clearing Logs

1. Navigate to either the **Export Site** or **Import Site** page.
2. The log of actions will be displayed at the bottom of the page.
3. To clear the logs, click the **Clear Log** button at the top right corner of the page.

### PHP Configuration

Ensure that your PHP settings are configured to handle large files and long execution times. Adjust the following settings if necessary:

- `max_execution_time`: Maximum execution time of each script, in seconds.
- `max_input_time`: Maximum amount of time each script may spend parsing request data.
- `memory_limit`: Maximum amount of memory a script may consume.
- `upload_max_filesize`: Maximum allowed size for uploaded files.
- `post_max_size`: Maximum size of POST data that PHP will accept.

You can view the current values of these settings on the plugin pages.

## License

This plugin is licensed under the MIT License. See the [LICENSE](LICENSE) file for more information.

## Contributing

Contributions are welcome! Please open an issue or submit a pull request on GitHub.

## Support

For support, please open an issue on the GitHub repository.

## Author

**Zeeshan Ahmad**
- [Website](https://tabsgi.com)
- [Email](mailto:ziishanahmad@gmail.com)
- [LinkedIn](https://www.linkedin.com/in/zeeshan-ahmad-10a84b18/)

---

*This plugin is developed and maintained by Zeeshan Ahmad.*
