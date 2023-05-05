#!/bin/bash

find ./www -name "*.php" -type f | while read filename; do
    # use sed to replace the regex match with the replacement string
    sed -i 's#\$search = strtolower(\$_GET["search"]);#\$search = strtolower(\$_GET["search"] ?? "");#g' "$filename"
done
