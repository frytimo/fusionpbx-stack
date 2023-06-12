

00-set_permissions.sh:
  Folders and files are set to the fusionpbx user and group (UID 1000)


10-copy_extra_apps.sh:

  Files are read from the extra_apps folder and COPIED in to the /var/www/fusionpbx/app folder using the  file.


99-init_db.sh:
  Normally FusionPBX is set to run the installer script from https://github.com/fusionpbx/fusionpbx-install.sh
  However, there is no installer for a Docker container that has all the applications split up on different IP
  addresses. This causes the initial installer script to fail even when using the /core/install/install.php
  file as there are dependencies that must be met first. This script will execute upon startup and ensure all
  criteria are met to login using the credentials provided in the .env file. After specific tables are created,
  the script will execute the upgrade/upgrade.php file to ensure that all app_defaults.php files are executed.

