## Changelog

### 1.1.3

- update: `dev/` runs [build-tools](https://github.com/magicoli/build-tools) instead of its own copy of the scripts
- update: [bash-tools](https://github.com/magicoli/bash-tools) 1.0.7 in require-dev, the scripts use its functions
- new: the GitHub release has the zip, next to the Debian package and its signed checksums
- fix: the release commit and the tag are `v<version>` followed by the changelog

### 1.1.2

- new: `dev/release.sh` makes the whole release, `dev/switch.sh` the composer part
- new: `dev/build.sh` makes the Debian package and the zip

### 1.1.1

- update formatting rules, composer update, reindent composer.json

### 1.1.0

- new: `opensim-rest-cli`, command-line client (`--ini`, `--host`, `--url`, `--wait`, stdin, `--repl`)
- update: `OpenSim_Rest::command()` waits for the prompt, tells questions and a closed console
- update: PHP 8.2 minimum, extensions declared, no `curl_close` (deprecated in 8.5)
- update: code formatted from `.editorconfig` and `.prettierrc.json`
- update: pest suite, the client runs against a console fixture
- update: `magicoli/php-bump-library` dropped, `symfony/process` 6.4.25
- fix the console port lookup of the client
