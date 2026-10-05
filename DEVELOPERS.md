# OpenSim REST PHP development rules

**Never use code or concepts related to projects consuming this library**, it must be agnostic and work with any project.

## Build

`dev/build.sh` makes what the project distributes into `dist/`, from the committed tree (commit first: the version carries the hash of HEAD, and `.dirty` when files are changed):

- a Debian package, `opensim-rest-php_<version>_all.deb`: the library and the client in `/usr/share/opensim-rest-php`, the command `opensim-rest-cli`; it depends on PHP through apt, no vendor folder;
- a zip, `opensim-rest-php-<version>.zip`: the same files with a `vendor` folder, to unzip and use without composer.

`dev/build.sh deb` or `dev/build.sh zip` makes one. What is distributed is what git tracks (so what `.gitignore` ignores is not there) without what `.distignore` lists, plus the `vendor` folder composer makes without the development tools, from the repositories of `composer.json` (a path repository in development, else Packagist). The work is done on copies, the `vendor` folder of the project is not touched. The scripts are in `packaging/`: `version`, `stage` (the files and the vendor folder), `build`, `zip`, `siblings` (the projects of the family the Debian package gets from their own packages), and the nfpm definition `opensim-rest-php.yaml`. It needs nfpm and composer.

`tests/Packaging/check` tries both in a clean container (podman), `PACKAGING=1 vendor/bin/pest` runs it after the build where podman is available. What the container needs is sent to it as a tar stream: `CONTAINER_CONNECTION=name` (a `podman system connection`) runs it on the podman of another machine, `MEMORY=` sets its memory (default 400m); both can be set in `tests/.env` (see `tests/.env.example`), the environment of the command wins.
