#!/usr/bin/env bash
# Builds what this project distributes into dist/, from the committed tree (commit first: the version carries the hash of
# HEAD): the Debian package (nfpm) and the zip. Calls what packaging/ defines.
#
#   dev/build.sh            every format
#   dev/build.sh deb zip    the formats named

set -e
cd "$(dirname "$0")/.."

formats=${*:-deb zip}
mkdir -p dist

for format in $formats; do
    case $format in
        deb)
            packaging/build
            set -a
            # shellcheck disable=SC1091
            source build/packaging.env
            set +a
            nfpm package --config packaging/opensim-rest-php.yaml --packager deb --target dist/
            ;;
        zip)
            packaging/zip
            ;;
        *)
            echo "dev/build.sh: no format $format (deb, zip)" >&2
            exit 2
            ;;
    esac
done
