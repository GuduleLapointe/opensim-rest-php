# OpenSim REST PHP development rules

**Never use code or concepts related to projects consuming this library**, it must be agnostic and work with any project.

## Build

`dev/build.sh` makes what the project distributes into `dist/`, from the committed tree (commit first: the version carries the hash of HEAD, and `.dirty` when files are changed):

- a Debian package, `opensim-rest-php_<version>_all.deb`: the library and the client in `/usr/share/opensim-rest-php`, the command `opensim-rest-cli`; it depends on PHP through apt, no vendor folder;
- a zip, `opensim-rest-php-<version>.zip`: the same files with a `vendor` folder, to unzip and use without composer.

`dev/build.sh deb` or `dev/build.sh zip` makes one. It calls what `packaging/` defines: `version`, `build` (the files of the package), `zip`, the nfpm definition `opensim-rest-php.yaml`, and `files`, the list of what is distributed. It needs nfpm and composer, and works on copies: the `vendor` folder of the project is not touched.

`tests/Packaging/check` tries both in a clean container (podman), `PACKAGING=1 vendor/bin/pest` runs it after the build where podman is available.
