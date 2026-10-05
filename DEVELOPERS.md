# OpenSim REST PHP development rules

**Never use code or concepts related to projects consuming this library**, it must be agnostic and work with any project.

## Build

`dev/build.sh` makes what the project distributes into `dist/`, from the committed tree (commit first: the version carries the hash of HEAD, and `.dirty` when files are changed):

- a Debian package, `opensim-rest-php_<version>_all.deb`: the library and the client in `/usr/share/opensim-rest-php`, the command `opensim-rest-cli`; it depends on PHP through apt, no vendor folder;
- a zip, `opensim-rest-php-<version>.zip`: the same files with a `vendor` folder, to unzip and use without composer.

`dev/build.sh deb` or `dev/build.sh zip` makes one. What is distributed is what git tracks (so what `.gitignore` ignores is not there) without what `.distignore` lists, plus the `vendor` folder composer makes without the development tools, from the repositories of `composer.json` (a path repository in development, else Packagist). The work is done on copies, the `vendor` folder of the project is not touched. The scripts are in `packaging/`: `version`, `stage` (the files and the vendor folder), `build`, `zip`, `siblings` (the projects of the family the Debian package gets from their own packages), and the nfpm definition `opensim-rest-php.yaml`. It needs nfpm and composer.

`tests/Packaging/check` tries both in a clean container (podman), `PACKAGING=1 vendor/bin/pest` runs it after the build where podman is available. What the container needs is sent to it as a tar stream: `CONTAINER_CONNECTION=name` (a `podman system connection`) runs it on the podman of another machine, `MEMORY=` sets its memory (default 400m); both can be set in `tests/.env` (see `tests/.env.example`), the environment of the command wins.

## Release

The projects of the family are released in the order of their dependencies, rest-php, engine, helpers, kit, each one completely before the next: the next one requires the version just published (and its tag has to be pushed, Packagist to know it).

```bash
dev/release.sh prepare [VERSION]   # the release commit: .version, the family required by version, CHANGELOG
dev/release.sh publish             # tag, push to the github remote (RELEASE_REMOTE=name), clean build of the tag
```

When the four are published, in the same order:

```bash
dev/release.sh next [VERSION]      # the next development version, the family linked again, a new Unreleased section
```

`dev/switch.sh dev|release` does the composer part alone, and updates `composer.lock`: `dev` links the projects next to this one (path repositories, `@dev`), `release` requires `^` the `.version` of each from Packagist, and waits for Packagist to know it (`SWITCH_WAIT` seconds, 180 by default). It refuses a project that is still at a `-dev` version. A step that fails leaves the files as they were. `publish` tags and pushes: it is yours to run.
