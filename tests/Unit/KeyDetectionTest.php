<?php

use Crumbls\LaravelCliTable\SelectableTable;
use Symfony\Component\Console\Output\BufferedOutput;

test('readKey detects up arrow - standard format', function () {
	$output = new BufferedOutput();
	$stream = fopen('php://memory', 'r+');
	if ($stream === false) {
		throw new RuntimeException('Failed to create memory stream');
	}
	
	fwrite($stream, "\033[A");
	rewind($stream);
	
	$table = new SelectableTable($output, $stream);
	$reflection = new ReflectionClass($table);
	$method = $reflection->getMethod('readKey');
	$method->setAccessible(true);
	
	$result = $method->invoke($table);
	
	expect($result)->toBe('up');
	fclose($stream);
});

test('readKey detects down arrow - standard format', function () {
	$output = new BufferedOutput();
	$stream = fopen('php://memory', 'r+');
	if ($stream === false) {
		throw new RuntimeException('Failed to create memory stream');
	}
	
	fwrite($stream, "\033[B");
	rewind($stream);
	
	$table = new SelectableTable($output, $stream);
	$reflection = new ReflectionClass($table);
	$method = $reflection->getMethod('readKey');
	$method->setAccessible(true);
	
	$result = $method->invoke($table);
	
	expect($result)->toBe('down');
	fclose($stream);
});

test('readKey detects enter variations', function () {
	$output = new BufferedOutput();
	
	$testCases = [
		"\n" => 'enter',
		"\r" => 'enter', 
		"\r\n" => 'enter'
	];
	
	foreach ($testCases as $input => $expected) {
		$stream = fopen('php://memory', 'r+');
		if ($stream === false) {
			throw new RuntimeException('Failed to create memory stream');
		}
		
		fwrite($stream, $input);
		rewind($stream);
		
		$table = new SelectableTable($output, $stream);
		$reflection = new ReflectionClass($table);
		$method = $reflection->getMethod('readKey');
		$method->setAccessible(true);
		
		$result = $method->invoke($table);
		
		expect($result)->toBe($expected, "Failed for input: " . bin2hex($input));
		fclose($stream);
	}
});

test('readKey detects escape variations', function () {
	$output = new BufferedOutput();
	
	$testCases = [
		"\033" => 'escape', // Escape character
		"q" => 'escape',
		"Q" => 'escape', 
		"\x03" => 'escape' // Ctrl+C
	];
	
	foreach ($testCases as $input => $expected) {
		$stream = fopen('php://memory', 'r+');
		if ($stream === false) {
			throw new RuntimeException('Failed to create memory stream');
		}
		
		fwrite($stream, $input);
		rewind($stream);
		
		$table = new SelectableTable($output, $stream);
		$reflection = new ReflectionClass($table);
		$method = $reflection->getMethod('readKey');
		$method->setAccessible(true);
		
		$result = $method->invoke($table);
		
		expect($result)->toBe($expected, "Failed for input: " . bin2hex($input));
		fclose($stream);
	}
});

test('readKey handles unrecognized input', function () {
	$output = new BufferedOutput();
	$stream = fopen('php://memory', 'r+');
	if ($stream === false) {
		throw new RuntimeException('Failed to create memory stream');
	}
	
	fwrite($stream, "xyz");
	rewind($stream);
	
	$table = new SelectableTable($output, $stream);
	$reflection = new ReflectionClass($table);
	$method = $reflection->getMethod('readKey');
	$method->setAccessible(true);
	
	$result = $method->invoke($table);
	
	expect($result)->toBe('');
	fclose($stream);
});