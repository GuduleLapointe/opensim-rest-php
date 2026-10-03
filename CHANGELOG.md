## Changelog

### 1.1.1

- update formatting rules, composer update, reindent composer.json

### 1.1.0

- new `opensim-rest-cli`, the command-line client of the library, with the options of the former client of the OpenSim kit (`--ini`, `--host`, `--url`, `--wait`, standard input, `--repl`); the phar is built with the current `OpenSim_Rest`
- update `OpenSim_Rest::command()` waits for the prompt and tells questions and a closed console; `sendCommand()` is unchanged for its callers
- update PHP 8.2 is the minimum (composer platform 8.2.0), the extensions needed are declared, `curl_close` is gone (deprecated in PHP 8.5)
- update the code is formatted from `.editorconfig` and `.prettierrc.json` (single quotes, PSR-12)
- update tests: a pest suite checks the PHP minimum and compatibility, and the client and `OpenSim_Rest` run against a console fixture
- update `magicoli/php-bump-library` is not a dependency anymore, `symfony/process` is 6.4.25
- fix the console port lookup of the client
