<?php
/**
 * A console that follows the protocol of RemoteConsole.cs, for the tests of OpenSim_Rest.
 * User admin, password secret; the state is kept in the file named by CONSOLE_STATE.
 */
$file = getenv('CONSOLE_STATE') ?: sys_get_temp_dir() . '/console-state.json';
$state = is_file($file) ? json_decode(file_get_contents($file), true) : ['lines' => [], 'session' => null, 'seen' => 0];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
parse_str(file_get_contents('php://input'), $post);
$add = function (string $text, bool $prompt = false, bool $command = false, bool $input = false) use (&$state) {
    $state['lines'][] = ['n' => count($state['lines']) + 1, 'text' => $text, 'prompt' => $prompt, 'command' => $command, 'input' => $input];
};
$save = function () use (&$state, $file) { file_put_contents($file, json_encode($state)); };
header('Content-Type: text/xml');
if ($path === '/StartSession/') {
    if (($post['USER'] ?? '') !== 'admin' || ($post['PASS'] ?? '') !== 'secret') {
        http_response_code(401);
        exit;
    }
    $state = ['lines' => [], 'session' => 'abc-123', 'seen' => 0, 'question' => false];
    $add('Region (Sim1) # ', true, true);
    $save();
    echo '<?xml version="1.0"?><ConsoleSession><SessionID>abc-123</SessionID><Prompt>Region (Sim1) # </Prompt></ConsoleSession>';
} elseif ($path === '/SessionCommand/') {
    if (($post['ID'] ?? '') !== 'abc-123' || !isset($post['COMMAND'])) {
        http_response_code(404);
        exit;
    }
    $cmd = $post['COMMAND'];
    $add($cmd, false, false, true);
    if (!empty($state['question'])) {
        $state['question'] = false;
        $add('Created ' . ($cmd === '' ? 'nobody' : $cmd) . ' &amp; done');
        $add('Region (Sim1) # ', true, true);
    } elseif ($cmd === 'show info') {
        $add('Version: OpenSim 0.9.3.0');
        $add('Region (Sim1) # ', true, true);
    } elseif ($cmd === 'create thing') {
        $state['question'] = true;
        $add('Name []: ', true, false);
    } else {
        $add('Region (Sim1) # ', true, true);
    }
    $save();
    echo '<?xml version="1.0"?><ConsoleSession><Result>OK</Result></ConsoleSession>';
} elseif (preg_match('#^/ReadResponses/#', $path)) {
    echo '<?xml version="1.0"?><ConsoleSession>';
    foreach ($state['lines'] as $l) {
        if ($l['n'] <= $state['seen']) {
            continue;
        }
        printf('<Line Number="%d" Level="" Prompt="%s" Command="%s" Input="%s">%s</Line>', $l['n'], $l['prompt'] ? 'true' : 'false', $l['command'] ? 'true' : 'false', $l['input'] ? 'true' : 'false', $l['text']);
    }
    echo '</ConsoleSession>';
    $state['seen'] = count($state['lines']);
    $save();
} elseif ($path === '/CloseSession/') {
    echo '<?xml version="1.0"?><ConsoleSession><Result>OK</Result></ConsoleSession>';
} else {
    http_response_code(404);
}
