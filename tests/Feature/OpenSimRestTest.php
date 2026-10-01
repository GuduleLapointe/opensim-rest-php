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
function start_console() {
	$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
	$port   = (int) substr( strrchr( stream_socket_get_name( $socket, false ), ':' ), 1 );
	fclose( $socket );

	$state   = tempnam( sys_get_temp_dir(), 'console-' );
	$process = proc_open(
		array( PHP_BINARY, '-S', "127.0.0.1:$port", '-t', dirname( __DIR__ ) . '/Fixtures', dirname( __DIR__ ) . '/Fixtures/console.php' ),
		array( 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ),
		$pipes,
		null,
		array( 'CONSOLE_STATE' => $state )
	);
	for ( $try = 0; $try < 40 && ! @fsockopen( '127.0.0.1', $port ); $try++ ) {
		usleep( 50000 );
	}

	return array( $process, $port, $state );
}

beforeAll( function () {
	$GLOBALS['console'] = start_console();
} );

afterAll( function () {
	proc_terminate( $GLOBALS['console'][0] );
	proc_close( $GLOBALS['console'][0] );
	@unlink( $GLOBALS['console'][2] );
} );

/**
 * Opens a session on the fixture console.
 *
 * @param string $password Console password.
 * @return OpenSim_Rest
 */
function console_session( $password = 'secret' ) {
	return new OpenSim_Rest(
		array(
			'uri'         => 'http://127.0.0.1:' . $GLOBALS['console'][1],
			'ConsoleUser' => 'admin',
			'ConsolePass' => $password,
		)
	);
}

describe( 'OpenSim_Rest session', function () {
	test( 'opens with the right credentials', function () {
		$rest = console_session();

		expect( $rest->connected() )->toBeTrue();
		expect( $rest->error )->toBeNull();
		expect( $rest->prompt )->toBe( 'Region (Sim1) # ' );
	} );

	test( 'refuses wrong credentials with an error', function () {
		$rest = console_session( 'wrong' );

		expect( $rest->connected() )->toBeFalse();
		expect( $rest->error )->toBeInstanceOf( Error::class );
		expect( $rest->reason )->toBe( 'the user or the password of the console is wrong' );
	} );

	test( 'does not call an offline instance an error', function () {
		$rest = new OpenSim_Rest(
			array(
				'uri'         => 'http://127.0.0.1:1',
				'ConsoleUser' => 'admin',
				'ConsolePass' => 'secret',
			)
		);

		expect( $rest->connected() )->toBeFalse();
		expect( $rest->error )->toBeNull();
		expect( $rest->reason )->toContain( 'cannot reach the console' );
	} );
} );

describe( 'OpenSim_Rest commands', function () {
	test( 'give the lines the console writes', function () {
		$result = console_session()->command( 'show info' );

		expect( $result['lines'] )->toBe( array( 'Version: OpenSim 0.9.3.0' ) );
		expect( $result['question'] )->toBeFalse();
		expect( $result['closed'] )->toBeFalse();
	} );

	test( 'tell a question from a prompt, and answer it', function () {
		$rest     = console_session();
		$question = $rest->command( 'create thing' );
		$answer   = $rest->command( 'Bob' );

		expect( $question['question'] )->toBeTrue();
		expect( $question['prompt'] )->toBe( 'Name []: ' );
		expect( $answer['lines'] )->toBe( array( 'Created Bob & done' ) );
		expect( $answer['question'] )->toBeFalse();
	} );

	test( 'on a closed session say so', function () {
		$result = console_session( 'wrong' )->command( 'show info' );

		expect( $result['closed'] )->toBeTrue();
		expect( $result['lines'] )->toBe( array() );
	} );
} );

describe( 'OpenSim_Rest sendCommand', function () {
	test( 'gives the lines, as before', function () {
		expect( console_session()->sendCommand( 'show info' ) )->toBe( array( 'Version: OpenSim 0.9.3.0' ) );
	} );

	test( 'gives false when the console writes nothing', function () {
		expect( console_session()->sendCommand( 'no output' ) )->toBeFalse();
	} );

	test( 'gives an error without a session', function () {
		expect( console_session( 'wrong' )->sendCommand( 'show info' ) )->toBeInstanceOf( Error::class );
	} );
} );
