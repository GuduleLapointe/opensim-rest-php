<?php

/**
 * OpenSimulator REST PHP command-line client
 *
 * This script provides a command-line interface to communicate with a Robust or OpenSimulator instance
 * with REST console enabled.
 *
 * The console is reached from the config of an instance (an ini file, [Const] BaseURL and
 * [Network] ConsolePort, ConsoleUser, ConsolePass) or from an URL, a user and a password.
 * Run it with --help for the options.
 *
 * @package opensim-rest-php
 * @category Command-line tools
 * @version 1.0.5
 * @license AGPLv3
 * @link https://github.com/magicoli/opensim-rest-php
 *
 * Donate to support the project:
 * @link https://magiiic.com/donate/project/?project=opensim-rest-php
 */

if ( php_sapi_name() !== 'cli' ) {
	// Code to handle when the file is accessed from a web page
	die( 'This script can only be run from the command line.' );
}

$base_dir = dirname( realpath( __FILE__ ) );
$base_dir = empty( $base_dir ) ? __DIR__ : $base_dir;
require_once $base_dir . '/class-rest.php';

/**
 * Retrieves the configuration from the INI file.
 *
 * @param string $defaultIniFile The path to the default INI file.
 * @param string $additionalIni The path to an additional INI file.
 * @return array|Error The configuration array if successful, or an Error object if an error occurred.
 */
function get_config( $defaultIniFile, $additionalIni = '' ) {
	static $config = null;

	if ( $config !== null ) {
		return $config;
	}

	// Read the ini file contents
	$iniContents = '';
	if ( file_exists( $defaultIniFile ) && is_readable( $defaultIniFile ) ) {
		$iniContents .= file_get_contents( $defaultIniFile );
	}
	if ( $additionalIni && file_exists( $additionalIni ) && is_readable( $additionalIni ) ) {
		$iniContents .= "\n" . file_get_contents( $additionalIni );
	}

	// Parse the ini contents manually
	$config = parse_ini_string( $iniContents, true, INI_SCANNER_RAW );

	if ( $config === false ) {
		return new Error( 'Error parsing ini file ' . $defaultIniFile );
	}

	// Process constants recursively
	$config = process_constants( $config );
	// error_log("config " . print_r($config, true));

	return $config;
}

/**
 * Processes the constants in the configuration array.
 *
 * @param array      $config The configuration array.
 * @param bool|array $section The current section being processed.
 * @return array The processed configuration array.
 */
function process_constants( $config, $section = false ) {
	if ( $section === false ) {
		$section = $config;
	}
	foreach ( $section as $key => $value ) {
		if ( is_array( $value ) ) {
			$section[ $key ] = process_constants( $config, $value );
		} else {
			$section[ $key ] = replace_constants( $config, $value );
		}
	}
	return $section;
}

/**
 * Replaces constants in a string with their corresponding values.
 *
 * @param array  $config The configuration array.
 * @param string $value The string containing the constants.
 * @return string The string with replaced constants.
 */
function replace_constants( $config, $value ) {
	$found = preg_match_all( '/\${([^\|]+)\|([^\}]+)}/', $value, $matches );
	if ( ! $found ) {
		return $value;
	}
	foreach ( $matches[0] as $index => $match ) {
		$sectionName = $matches[1][ $index ];
		$option      = $matches[2][ $index ];

		if ( isset( $config[ $sectionName ][ $option ] ) ) {
			$replacement = $config[ $sectionName ][ $option ];
			$value       = str_replace( $match, $replacement, $value );
		}
	}
	return $value;
}

/**
 * Retrieves a value from the configuration array, case-insensitive.
 *
 * @param array  $array The configuration array.
 * @param string $key The key to retrieve.
 * @return mixed The value if found, null otherwise.
 */
function array_get_case_insensitive( $array, $key ) {
	$key           = strtolower( $key );
	$lowercaseKeys = array_map( 'strtolower', array_keys( $array ) );
	$lowercaseKey  = strtolower( $key );

	$index = array_search( $lowercaseKey, $lowercaseKeys, true );

	if ( $index !== false ) {
		$keys = array_keys( $array );
		return $array[ $keys[ $index ] ];
	}

	return null;
}

/**
 * Retrieves an option from the configuration.
 *
 * @param string $option The option to retrieve.
 * @param mixed  $default The default value if the option is not found.
 * @return mixed The option value if found, or the default value.
 */
function get_option( $option, $default = null ) {
	$scriptFilename = __FILE__;
	$scriptBasename = pathinfo( $scriptFilename, PATHINFO_FILENAME );
	$defaultIniFile = getenv( 'HOME' ) . '/.' . $scriptBasename . '.ini';

	$additionalIni = isset( $GLOBALS['additional_ini'] ) ? $GLOBALS['additional_ini'] : '';

	$config = get_config( $defaultIniFile, $additionalIni );

	switch ( $option ) {
		case 'opensim_rest_config':
			$constSection   = array_get_case_insensitive( $config, 'Const' ) ?? [];
			$networkSection = array_get_case_insensitive( $config, 'Network' ) ?? [];
			$baseURL     = $GLOBALS['console_host'] ?? array_get_case_insensitive( $constSection, 'BaseURL' ) ?? 'localhost';
			$consolePort = array_get_case_insensitive( $networkSection, 'ConsolePort' )
			            ?? array_get_case_insensitive( $networkSection, 'console_port' )
			            ?? 8002;
			$consoleUser = array_get_case_insensitive( $networkSection, 'ConsoleUser' ) ?? '';
			$consolePass = array_get_case_insensitive( $networkSection, 'ConsolePass' ) ?? '';
			return array(
				'uri'         => "$baseURL:$consolePort",
				'ConsoleUser' => $consoleUser,
				'ConsolePass' => $consolePass,
			);

		default:
			$parts   = explode( ':', $option );
			$section = $parts[0];
			$key     = isset( $parts[1] ) ? $parts[1] : null;

			if ( isset( $config[ $section ] ) ) {
				  $sectionData = $config[ $section ];
				  $sectionData = array_get_case_insensitive( $config, $section );

				if ( $key !== null ) {
					return array_get_case_insensitive( $sectionData, $key ) ?? $default;
					// return $sectionData[$key] ?? $default;
				} else {
					return $sectionData;
				}
			}
	}

	return $default;
}

/**
 * Prints the usage.
 *
 * @param resource $stream Where to print, STDOUT or STDERR.
 */
function usage( $stream = STDOUT ) {
	fwrite(
		$stream,
		<<<'USAGE'
Usage:
  opensim-rest-cli [<ini_file>] <command>             one command, the config is read from the ini file
  opensim-rest-cli [options] -- <command>             one command
  opensim-rest-cli [options] -                        the lines of the standard input, one command or answer each
                                                      (an empty line answers a question with its default)
  opensim-rest-cli [options] --repl                   a prompt, as the console of the instance

Options:
  --ini FILE        config of the instance: [Const] BaseURL, [Network] ConsolePort (or console_port),
                    ConsoleUser and ConsolePass, added to ~/.opensim-rest-cli.ini
  --host HOST       reach the console at HOST instead of BaseURL (e.g. 127.0.0.1 from the machine itself)
  --url URL         reach the console at URL (http://HOST:PORT) with --user, the password is in the
                    environment variable OPENSIM_REST_PASSWORD
  --user USER       console user, with --url
  --wait SECONDS    how long to wait for the console to be ready again after a line (default 5)
  -h, --help        this help

Exit code: 0 done, 1 cannot reach or open the console, 2 usage.

USAGE
	);
}

/**
 * Reads the arguments.
 *
 * @param array $args Arguments, without the script name.
 * @return array|string The options, or an error message.
 */
function read_arguments( $args ) {
	$options = array(
		'ini'     => null,
		'host'    => null,
		'url'     => null,
		'user'    => null,
		'wait'    => 5.0,
		'mode'    => null,
		'command' => array(),
	);

	// Legacy form: an ini file as first argument, the rest is the command
	if ( isset( $args[0] ) && '-' !== $args[0] && is_file( $args[0] ) && is_readable( $args[0] ) ) {
		$options['ini']     = array_shift( $args );
		$options['mode']    = 'command';
		$options['command'] = $args;
		return $options;
	}

	// Legacy form too: no option, the whole line is the command, the config is in ~/.opensim-rest-cli.ini
	if ( isset( $args[0] ) && '-' !== substr( $args[0], 0, 1 ) ) {
		$options['mode']    = 'command';
		$options['command'] = $args;
		return $options;
	}

	while ( $args ) {
		$arg = array_shift( $args );
		switch ( $arg ) {
			case '--ini':
			case '--host':
			case '--url':
			case '--user':
				if ( ! $args ) {
					return "$arg needs a value";
				}
				$options[ ltrim( $arg, '-' ) ] = array_shift( $args );
				break;

			case '--wait':
				if ( ! $args ) {
					return '--wait needs a value';
				}
				$options['wait'] = (float) array_shift( $args );
				break;

			case '--repl':
				$options['mode'] = 'repl';
				break;

			case '-':
				$options['mode'] = 'stdin';
				break;

			case '--':
				$options['mode']    = 'command';
				$options['command'] = $args;
				$args               = array();
				break;

			case '-h':
			case '--help':
				$options['mode'] = 'help';
				break;

			default:
				return "unknown argument $arg";
		}
	}

	return $options;
}

/**
 * Types a line in the console and prints what it answers.
 *
 * @param OpenSim_Rest $rest Open session.
 * @param string       $line Line to type.
 * @param float        $wait Seconds to wait for the console to be ready again.
 * @return bool False when the console is gone.
 */
function send_line( $rest, $line, $wait ) {
	$result = $rest->command( $line, $wait );
	foreach ( $result['lines'] as $text ) {
		echo $text, "\n";
	}

	return ! $result['closed'];
}

$options = read_arguments( array_slice( $argv, 1 ) );
if ( is_string( $options ) ) {
	fwrite( STDERR, "opensim-rest-cli: $options\n" );
	usage( STDERR );
	exit( 2 );
}
if ( 'help' === $options['mode'] ) {
	usage();
	exit( 0 );
}
if ( null === $options['mode'] || ( null === $options['ini'] && null === $options['url'] && ! is_file( getenv( 'HOME' ) . '/.opensim-rest-cli.ini' ) ) ) {
	usage( STDERR );
	exit( 2 );
}

if ( null !== $options['url'] ) {
	$password = (string) getenv( 'OPENSIM_REST_PASSWORD' );
	if ( null === $options['user'] || '' === $password ) {
		fwrite( STDERR, "opensim-rest-cli: --url needs --user and OPENSIM_REST_PASSWORD\n" );
		exit( 2 );
	}
	$rest_args = array(
		'uri'         => $options['url'],
		'ConsoleUser' => $options['user'],
		'ConsolePass' => $password,
	);
} else {
	$GLOBALS['additional_ini'] = $options['ini'];
	if ( null !== $options['host'] ) {
		$GLOBALS['console_host'] = $options['host'];
	}
	$rest_args = get_option( 'opensim_rest_config' );
	if ( is_opensim_rest_error( $rest_args ) ) {
		fwrite( STDERR, 'opensim-rest-cli: error reading config: ' . $rest_args->getMessage() . "\n" );
		exit( 1 );
	}
}

$rest = opensim_rest_session( $rest_args );
if ( is_opensim_rest_error( $rest ) ) {
	fwrite( STDERR, 'opensim-rest-cli: ' . $rest->getMessage() . "\n" );
	exit( 1 );
}
if ( ! $rest->connected() ) {
	fwrite( STDERR, 'opensim-rest-cli: ' . $rest->reason . "\n" );
	exit( 1 );
}

switch ( $options['mode'] ) {
	case 'command':
		send_line( $rest, implode( ' ', $options['command'] ), $options['wait'] );
		break;

	case 'stdin':
		while ( false !== ( $line = fgets( STDIN ) ) ) {
			if ( ! send_line( $rest, rtrim( $line, "\r\n" ), $options['wait'] ) ) {
				break;
			}
		}
		break;

	case 'repl':
		while ( true ) {
			echo '' !== $rest->prompt ? $rest->prompt : '> ';
			$line = fgets( STDIN );
			if ( false === $line ) {
				echo "\n";
				break;
			}
			if ( ! send_line( $rest, rtrim( $line, "\r\n" ), $options['wait'] ) ) {
				break;
			}
		}
		break;
}

$rest->close();
