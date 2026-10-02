<?php

/**
 * OpenSimulator REST PHP library and command-line client
 *
 * This class provides functionality to communicate with a Robust or OpenSimulator instance
 * with REST console enabled.
 *
 * @package opensim-rest-php
 * @category Libraries
 * @version 1.0.6
 * @license AGPLv3
 * @link https://github.com/magicoli/opensim-rest-php
 *
 * Donate to support the project:
 * @link https://magiiic.com/donate/project/?project=opensim-rest-php
 */

/**
 * The remote (REST) console of a Robust or an OpenSimulator instance, which
 * gives every command of its console through one port.
 *
 * The protocol, as OpenSimulator serves it (Framework/Console/RemoteConsole.cs):
 *  - StartSession: a form with USER and PASS, answered with a SessionID (401
 *    when they are wrong);
 *  - SessionCommand: a form with ID and COMMAND, a line typed in the console;
 *  - ReadResponses/<ID>/: the lines the console wrote since the last reading,
 *    waiting (up to 25 seconds) when there are none. A line has Input when it
 *    is the echo of what was typed, and Prompt when the console waits for input
 *    (Command as well when it is for a new command, else it is a question);
 *  - CloseSession: a form with ID.
 *
 * The console is one for everybody: the lines typed in a session go to the
 * console of the instance, and every session sees all the output.
 */
class OpenSim_Rest
{
    private $url;
    private $ch;
    private $sessionID;

    /**
     * @var Error|null Why the session could not be opened (bad address, wrong credentials,
     *                 no session in the answer). Not set when the instance is just offline.
     */
    public $error = null;

    /** @var string Why there is no session, including when the instance is offline. */
    public $reason = '';

    /** @var string The last prompt the console showed. */
    public $prompt = '';

    /**
     * OpenSim_Rest constructor.
     *
     * Initializes a new instance of the OpenSim_Rest class.
     *
     * @param array $args An array of arguments for configuring the REST connection.
     *   - uri: The base URI for the REST connection.
     *   - ConsoleUser: The username for authentication.
     *   - ConsolePass: The password for authentication.
     */
    public function __construct($args = [])
    {
        $c = array_merge(
            [
                'uri' => null,
                'ConsoleUser' => null,
                'ConsolePass' => null,
            ],
            $args,
        );
        $c = array_merge(
            [
                'scheme' => 'http',
                'host' => 'localhost',
                'port' => $c['uri'] == (int) $c['uri'] ? $c['uri'] : null,
            ],
            $c,
            (array) parse_url((string) $c['uri']),
        );

        $this->url = "{$c['scheme']}://{$c['host']}:{$c['port']}";

        if (empty($c['host']) || empty($c['port'])) {
            $this->reason = "Invalid URL $this->url from {$c['uri']}";
            $this->error = new Error($this->reason);
            return;
        }

        $this->ch = curl_init();

        $session = $this->startSession($c['ConsoleUser'], $c['ConsolePass']);
        if (is_opensim_rest_error($session)) {
            $this->error = $session;
        }
    }

    /**
     * OpenSim_Rest destructor.
     *
     * Cleans up any resources used by the OpenSim_Rest instance.
     */
    public function __destruct()
    {
        $this->close();
        // The curl handle is freed with its object
        $this->ch = null;
    }

    /**
     * Tell if a session is open.
     *
     * @return bool
     */
    public function connected()
    {
        return !empty($this->sessionID);
    }

    /**
     * Starts a session with the REST console.
     *
     * @param string $ConsoleUser The username for authentication.
     * @param string $ConsolePass The password for authentication.
     * @return string|Error|false The session ID if successful, false when the instance is offline
     *                            (not an error, no need to clutter the error log with this), or an
     *                            Error object if an error occurred.
     */
    private function startSession($ConsoleUser, $ConsolePass)
    {
        [$status, $body] = $this->post(
            '/StartSession/',
            [
                'USER' => $ConsoleUser,
                'PASS' => $ConsolePass,
            ],
            5,
        );

        if (0 === $status) {
            $this->reason = "cannot reach the console at {$this->url} (the instance is not running, or not with its remote console)";
            return false;
        }

        if (401 === $status) {
            $this->reason = 'the user or the password of the console is wrong';
            return new Error($this->reason);
        }

        $xml = $this->xml($body);
        if ($xml === false || empty($xml->SessionID)) {
            $this->reason = "no session from the console at {$this->url} (HTTP $status)";
            return new Error($this->reason);
        }

        $this->sessionID = (string) $xml->SessionID;
        $this->prompt = (string) $xml->Prompt;

        return $this->sessionID;
    }

    /**
     * Types a line in the console, and gives what it answers: the lines written
     * until the console waits for input again (a command prompt: done, or a
     * question: the console wants an answer, typed with the next call), or until
     * $wait seconds pass. An instance that stops, like after shutdown, ends the
     * connection: not an error.
     *
     * @param string $line The line to type.
     * @param float  $wait Seconds to wait for the console to be ready again.
     * @return array {
     *   @type string[] $lines    The lines the console wrote as an answer.
     *   @type string   $prompt   The last prompt of the console.
     *   @type bool     $question The console asks a question (answer it with the next call).
     *   @type bool     $closed   The connection is gone.
     * }
     */
    public function command($line, $wait = 5.0)
    {
        $result = [
            'lines' => [],
            'prompt' => $this->prompt,
            'question' => false,
            'closed' => false,
        ];

        if (!$this->connected()) {
            $result['closed'] = true;
            return $result;
        }

        [$status] = $this->post(
            '/SessionCommand/',
            [
                'ID' => $this->sessionID,
                'COMMAND' => $line,
            ],
            5,
        );
        if (200 !== $status) {
            $result['closed'] = true;
            return $result;
        }

        // What the console wrote before the echo of the line is not an answer to it
        $echoed = false;
        $end = microtime(true) + $wait;
        while (microtime(true) < $end) {
            $started = microtime(true);
            $entries = $this->read(min(2.0, max(0.2, $end - microtime(true))));
            if (null === $entries) {
                $result['closed'] = true;
                break;
            }
            if ([] === $entries && microtime(true) - $started < 0.05) {
                usleep(100000);
            }
            foreach ($entries as $entry) {
                if ($entry['input']) {
                    $echoed = true;
                    continue;
                }
                if (!$echoed) {
                    continue;
                }
                if ($entry['prompt']) {
                    $this->prompt = $entry['text'];
                    $result['prompt'] = $entry['text'];
                    $result['question'] = !$entry['command'];
                    return $result;
                }
                $result['lines'][] = $entry['text'];
            }
        }

        return $result;
    }

    /**
     * Sends a command to the REST console.
     *
     * @param string $command The command to send.
     * @return array|false|Error An array containing the lines of response if successful, false when
     *                           the console wrote nothing, or an Error object if an error occurred.
     */
    public function sendCommand($command)
    {
        if (!$this->connected()) {
            return new Error('Rest command error: ' . ($this->reason ?: 'no session'));
        }

        $result = $this->command($command, 3.0);
        if ($result['closed'] && empty($result['lines'])) {
            return new Error('Rest response error: the console closed the connection');
        }

        return empty($result['lines']) ? false : $result['lines'];
    }

    /**
     * Closes the session with the REST console.
     *
     * @return string|Error|null The response if successful, an Error object if an error occurred,
     *                           null when there was no session.
     */
    public function close()
    {
        if (empty($this->sessionID) || empty($this->ch)) {
            return null;
        }

        [$status, $body] = $this->post('/CloseSession/', ['ID' => $this->sessionID], 2);
        $this->sessionID = '';
        // Nothing more to ask: free the handle, which closes the connection
        $this->ch = null;

        if (0 === $status) {
            return new Error('Rest close session_error');
        }

        return $body;
    }

    /**
     * Reads the lines the console wrote since the last reading.
     *
     * @param float $timeout Seconds to wait for lines when there are none.
     * @return array[]|null Entries with text, input, prompt and command, or null when the console is gone.
     */
    private function read($timeout)
    {
        [$status, $body, $errno] = $this->post("/ReadResponses/{$this->sessionID}/", [], $timeout);

        if (0 === $status) {
            // A reading that waits for output and finds none ends by timeout, any other failure is the end of the console
            return (defined('CURLE_OPERATION_TIMEDOUT') ? CURLE_OPERATION_TIMEDOUT : 28) === $errno ? [] : null;
        }
        if (200 !== $status) {
            return null;
        }

        $entries = [];
        $xml = $this->xml($body);
        if ($xml !== false && isset($xml->Line)) {
            foreach ($xml->Line as $line) {
                $entries[] = [
                    'text' => (string) $line,
                    'input' => (string) $line['Input'] === 'true',
                    'prompt' => (string) $line['Prompt'] === 'true',
                    'command' => (string) $line['Command'] === 'true',
                ];
            }
        }

        return $entries;
    }

    /**
     * Posts a form to the console.
     *
     * @param string $path    The path of the request.
     * @param array  $fields  The fields of the form.
     * @param float  $timeout Seconds to wait for the answer.
     * @return array [ HTTP status (0 when nothing answered), body, curl error number ]
     */
    private function post($path, $fields, $timeout)
    {
        curl_setopt_array($this->ch, [
            CURLOPT_URL => $this->url . $path,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT_MS => (int) round($timeout * 1000),
        ]);

        $body = curl_exec($this->ch);

        return [
            (int) curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE),
            false === $body ? '' : $body,
            curl_errno($this->ch),
        ];
    }

    /**
     * Parses an XML answer of the console.
     *
     * @param string $body The body of the answer.
     * @return SimpleXMLElement|false
     */
    private function xml($body)
    {
        if ('' === trim((string) $body)) {
            return false;
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml;
    }
}

/**
 * Creates a new OpenSim_Rest session.
 *
 * @param array $args An array of arguments for configuring the REST connection.
 * @return OpenSim_Rest|Error An instance of the OpenSim_Rest class if successful, or an Error object if an error occurred.
 */
function opensim_rest_session($args)
{
    $rest = new OpenSim_Rest($args);

    if (isset($rest->error) && is_opensim_rest_error($rest->error)) {
        return $rest->error;
    }

    return $rest;
}

/**
 * Checks if a given thing is an OpenSim_Rest error.
 *
 * @param mixed $thing The thing to check.
 * @return bool Returns true if the thing is an OpenSim_Rest error, false otherwise.
 */
function is_opensim_rest_error($thing)
{
    if ($thing instanceof Error) {
        return true;
    }
}
