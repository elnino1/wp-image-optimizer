## How to package and install the WordPress plugin

To package and install the WordPress plugin, you generally have two main approaches: creating a zip file to upload via the WordPress interface, or manually copying the files into your WordPress installation.


Here are the instructions on how to package and install it:

**Method 1:** The Standard Way (Zip Archive)

This is the easiest way to install it on a live or remote WordPress site.

Package the Plugin (Create a Zip File): You need to compress the wp-image-optimizer folder into a .zip file. If you are in your terminal and inside the dev folder (the parent folder of the plugin), you can run:

```bash
zip -r wp-image-optimizer.zip wp-image-optimizer/ -x "*.git*" "*.DS_Store"
```

This command zips the folder while excluding Git history and macOS system files.

Install via WordPress Admin:

- Log in to your WordPress Admin dashboard (/wp-admin).
- Navigate to Plugins -> Add New Plugin.
- Click the Upload Plugin button at the top of the page.
- Choose the wp-image-optimizer.zip file you just created.
- Click Install Now.
- Once installed, click Activate Plugin.

**Method 2:** Manual Installation (Best for Local Development)

If you have WordPress running locally on your computer (e.g., using LocalWP, XAMPP, or Docker), this is the preferred method because you can continue to edit the code live.

Locate your WordPress Plugins Folder: Find where your WordPress site is installed on your computer. The plugins folder is located at: [Path_to_WordPress]/wp-content/plugins/

Copy or Symlink the Folder: Copy your entire wp-image-optimizer folder into that plugins directory.

Alternatively, you can create a symlink (shortcut) from your dev folder to the WordPress plugins folder so any changes you make in your IDE are instantly reflected in WordPress:

```bash
ln -s /Users/davidlenir/dev/wp-image-optimizer /path/to/your/wordpress/wp-content/plugins/wp-image-optimizer
```

Activate in WordPress:

- Log in to your WordPress Admin dashboard (/wp-admin).
- Navigate to Plugins -> Installed Plugins.
- Find "WordPress Image Optimizer" in the list.
- Click Activate.
