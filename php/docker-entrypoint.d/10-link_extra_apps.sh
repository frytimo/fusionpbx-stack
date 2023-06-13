#!/usr/bin/env sh
set -e
echo "LINKING EXTRA APPS..."

#
# link each of the directories in the /usr/local/src/fusionpbx_extra_apps folder to the fusionpbx destination in the container
#
for app in /usr/local/src/fusionpbx_extra_apps/*; do
    _APP="$(basename ${app})"
    [ -d "${app}" ] && [ ! -L "/var/www/fusionpbx/app/${_APP}" ] && echo "LINKING ${_APP}" && ln -s "${app}" /var/www/fusionpbx/app
done

echo "Linked all extra_apps"
