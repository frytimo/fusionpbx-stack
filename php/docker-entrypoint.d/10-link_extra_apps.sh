#!/usr/bin/env sh
set -e
echo "LOADING EXTRA APPS..."

#
# set each of the directories in the extra_apps folder to be included in fusionpbx
#
for app in /usr/local/src/extra_apps/*; do
    _APP="$(basename ${app})"
    echo "${_APP}"
    [ -d "${app}" ] && su -c "cp -R \"${app}\" \"/var/www/fusionpbx/app\"" fusionpbx
done

echo "DONE"
