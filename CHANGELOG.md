## Changelog

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
