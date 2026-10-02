<?php
/**
 * OpenSim_Rest against a console that follows the protocol of RemoteConsole.cs
 * (tests/Fixtures/console.php, user admin, password secret).
 */

/**
 * Starts the fixture console on a free local port.
 *
 * @return array Process, port and state file.
 */
function start_console()
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $state = tempnam(sys_get_temp_dir(), 'console-');
    $process = proc_open(
        [
            PHP_BINARY,
            '-S',
            "127.0.0.1:$port",
            '-t',
            dirname(__DIR__) . '/Fixtures',
            dirname(__DIR__) . '/Fixtures/console.php',
        ],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        null,
        ['CONSOLE_STATE' => $state, 'PHP_CLI_SERVER_WORKERS' => '4'],
    );
    // Up to ten seconds for the server to listen, a loaded machine can be slow
    for ($try = 0; $try < 200 && !($probe = @fsockopen('127.0.0.1', $port, $code, $message, 0.2)); $try++) {
        usleep(50000);
    }
    if (!empty($probe)) {
        fclose($probe);
    }

    return [$process, $port, $state];
}

beforeAll(function () {
    $GLOBALS['console'] = start_console();
});

afterAll(function () {
    proc_terminate($GLOBALS['console'][0]);
    proc_close($GLOBALS['console'][0]);
    @unlink($GLOBALS['console'][2]);
});

/**
 * Opens a session on the fixture console.
 *
 * @param string $password Console password.
 * @return OpenSim_Rest
 */
function console_session($password = 'secret')
{
    return new OpenSim_Rest([
        'uri' => 'http://127.0.0.1:' . $GLOBALS['console'][1],
        'ConsoleUser' => 'admin',
        'ConsolePass' => $password,
    ]);
}

describe('OpenSim_Rest session', function () {
    test('opens with the right credentials', function () {
        $rest = console_session();

        expect($rest->connected())->toBeTrue();
        expect($rest->error)->toBeNull();
        expect($rest->prompt)->toBe('Region (Sim1) # ');
    });

    test('refuses wrong credentials with an error', function () {
        $rest = console_session('wrong');

        expect($rest->connected())->toBeFalse();
        expect($rest->error)->toBeInstanceOf(Error::class);
        expect($rest->reason)->toBe('the user or the password of the console is wrong');
    });

    test('does not call an offline instance an error', function () {
        $rest = new OpenSim_Rest([
            'uri' => 'http://127.0.0.1:1',
            'ConsoleUser' => 'admin',
            'ConsolePass' => 'secret',
        ]);

        expect($rest->connected())->toBeFalse();
        expect($rest->error)->toBeNull();
        expect($rest->reason)->toContain('cannot reach the console');
    });
});

describe('OpenSim_Rest commands', function () {
    test('give the lines the console writes', function () {
        $result = console_session()->command('show info');

        expect($result['lines'])->toBe(['Version: OpenSim 0.9.3.0']);
        expect($result['question'])->toBeFalse();
        expect($result['closed'])->toBeFalse();
    });

    test('tell a question from a prompt, and answer it', function () {
        $rest = console_session();
        $question = $rest->command('create thing');
        $answer = $rest->command('Bob');

        expect($question['question'])->toBeTrue();
        expect($question['prompt'])->toBe('Name []: ');
        expect($answer['lines'])->toBe(['Created Bob & done']);
        expect($answer['question'])->toBeFalse();
    });

    test('on a closed session say so', function () {
        $result = console_session('wrong')->command('show info');

        expect($result['closed'])->toBeTrue();
        expect($result['lines'])->toBe([]);
    });
});

describe('OpenSim_Rest sendCommand', function () {
    test('gives the lines, as before', function () {
        expect(console_session()->sendCommand('show info'))->toBe(['Version: OpenSim 0.9.3.0']);
    });

    test('gives false when the console writes nothing', function () {
        expect(console_session()->sendCommand('no output'))->toBeFalse();
    });

    test('gives an error without a session', function () {
        expect(console_session('wrong')->sendCommand('show info'))->toBeInstanceOf(Error::class);
    });
});

/**
 * Runs the command-line client.
 *
 * @param array  $arguments Arguments.
 * @param string $stdin     Standard input.
 * @return array Exit status, standard output, standard error.
 */
function run_cli(array $arguments, $stdin = '')
{
    $process = proc_open(
        array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/opensim-rest-cli.php'], $arguments),
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), ['OPENSIM_REST_PASSWORD' => 'secret', 'HOME' => sys_get_temp_dir()]),
    );
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    return [proc_close($process), trim($output), trim($errors)];
}

/**
 * Writes the config of the fixture console as an instance would have it.
 *
 * @param string $base_url Value of BaseURL.
 * @return string Path of the ini file.
 */
function console_ini($base_url = '127.0.0.1')
{
    $file = tempnam(sys_get_temp_dir(), 'console-ini-');
    file_put_contents(
        $file,
        "[Const]\n  BaseURL = \"$base_url\"\n[Network]\n  ConsolePort = {$GLOBALS['console'][1]}\n  ConsoleUser = \"admin\"\n  ConsolePass = \"secret\"\n",
    );

    return $file;
}

describe('opensim-rest-cli', function () {
    test('runs one command from an ini file', function () {
        expect(run_cli([console_ini(), 'show', 'info']))->toBe([0, 'Version: OpenSim 0.9.3.0', '']);
    });

    test('reads the commands and answers of the standard input', function () {
        [$status, $output] = run_cli(['--ini', console_ini(), '-'], "create thing\nBob\n");

        expect($status)->toBe(0);
        expect($output)->toBe('Created Bob & done');
    });

    test('reaches a console from an url, a user and the password in the environment', function () {
        $url = 'http://127.0.0.1:' . $GLOBALS['console'][1];

        expect(run_cli(['--url', $url, '--user', 'admin', '--', 'show', 'info']))->toBe([
            0,
            'Version: OpenSim 0.9.3.0',
            '',
        ]);
    });

    test('reaches the console at --host instead of BaseURL', function () {
        [$status, $output] = run_cli([
            '--ini',
            console_ini('unreachable.invalid'),
            '--host',
            '127.0.0.1',
            '--',
            'show',
            'info',
        ]);

        expect($status)->toBe(0);
        expect($output)->toBe('Version: OpenSim 0.9.3.0');
    });

    test('exits 1 when the password is wrong', function () {
        [$status, , $errors] = run_cli([
            '--url',
            'http://127.0.0.1:' . $GLOBALS['console'][1],
            '--user',
            'nobody',
            '--',
            'show',
            'info',
        ]);

        // The fixture accepts only admin
        expect($status)->toBe(1);
        expect($errors)->toContain('the user or the password of the console is wrong');
    });

    test('exits 1 when the console is offline', function () {
        [$status, , $errors] = run_cli(['--url', 'http://127.0.0.1:1', '--user', 'admin', '--', 'show', 'info']);

        expect($status)->toBe(1);
        expect($errors)->toContain('cannot reach the console');
    });

    test('exits 2 on an unknown option', function () {
        [$status, , $errors] = run_cli(['--bogus']);

        expect($status)->toBe(2);
        expect($errors)->toContain('unknown argument --bogus');
    });
});
