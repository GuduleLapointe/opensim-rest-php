# OpenSim REST PHP development rules

**Never use code or concepts related to projects consuming this library**, it must be agnostic and work with any project.

## Build

`dev/build.sh` makes what the project distributes into `dist/`, from the committed tree (commit first: the version carries the hash of HEAD, and `.dirty` when files are changed):

- a Debian package, `opensim-rest-php_<version>_all.deb`: the library and the client in `/usr/share/opensim-rest-php`, the command `opensim-rest-cli`; it depends on PHP through apt, no vendor folder;
- a zip, `opensim-rest-php-<version>.zip`: the same files with a `vendor` folder, to unzip and use without composer.

`dev/build.sh deb` or `dev/build.sh zip` makes one. What is distributed is what git tracks (so what `.gitignore` ignores is not there) without what `.distignore` lists, plus the `vendor` folder composer makes without the development tools, from the repositories of `composer.json` (a path repository in development, else Packagist). The work is done on copies, the `vendor` folder of the project is not touched. The scripts are in `packaging/`: `version`, `stage` (the files and the vendor folder), `build`, `zip`, `siblings` (the projects of the family the Debian package gets from their own packages), and the nfpm definition `opensim-rest-php.yaml`. It needs nfpm and composer.

`tests/Packaging/check` tries both in a clean container (podman), `PACKAGING=1 vendor/bin/pest` runs it after the build where podman is available. What the container needs is sent to it as a tar stream: `CONTAINER_CONNECTION=name` (a `podman system connection`) runs it on the podman of another machine, `MEMORY=` sets its memory (default 400m); both can be set in `tests/.env` (see `tests/.env.example`), read after the `.env` of the project (bash-tools `read_env`: what the files set wins over the environment of the command).

## Release

One command in each project, in the order of the family (rest-php, engine, helpers, kit), each one completely before the next:

```bash
dev/release.sh          # the whole release, after one question
dev/release.sh status   # what is done and what remains
```

It does what remains, whatever was done before: run it again after an interruption or an error. It makes the release commit (`.version`, the projects of the family required by version, `CHANGELOG.md`; its message is `v<version>` followed by the lines of the changelog, verbatim, and the tag says the same), the tag, the push to the `github` remote (`RELEASE_REMOTE` for another), the zip, the publication through `apt-package --publish` (the apt repository, and the GitHub release with the Debian packages and their checksums) with the zip added to that release, then the next development version, pushed. The tag is built and published in a folder of its own (`tmp/release-<tag>`), so the current commit does not matter. `dev/release.sh VERSION` releases another version than the one `.version` gives, `RELEASE_YES=1` answers yes to the question. It needs `gh` (logged in), `apt-package`, `nfpm`; it says what is missing before it does anything.

`dev/switch.sh dev|release` does the composer part alone, and updates `composer.lock`. `dev` links the projects next to this one (path repositories, `@dev`). `release` requires `^` the latest version tag of each, and refuses a project that changed since that release (release it first); if composer does not find the tag, it is tried again for `SWITCH_WAIT` seconds (1200 by default) and says why. A step that fails leaves the files as they were.

## Shell scripts

The scripts of `dev/`, `packaging/` and `tests/Packaging` use the functions of [bash-tools](https://github.com/magicoli/bash-tools) to ask, tell and fail: `log`, `success`, `warning`, `die`, `end`, `yesno`, `require`, `usage`, `read_env`. It is a development dependency of the project, loaded by `dev/lib.sh` (the copy of `vendor`, else the package, else the PATH); loading it also reads the `.env` of the project. Write the new ones the same way, not with their own prompts and `echo`.
