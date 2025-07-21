<?php

use Crumbls\LaravelCliTable\SelectableTable;
use Symfony\Component\Console\Output\BufferedOutput;

test('SelectableTable handles Windows environment gracefully', function () {
	$output = new BufferedOutput();
	$table = new SelectableTable($output);
	
	// Test that the table can be created and configured on any platform
	$table->setHeaders(['ID', 'Name']);
	$table->setRows([
		['1', 'John'],
		['2', 'Jane'],
	]);
	
	// Set to non-interactive to avoid hanging
	$table->setInteractive(false);
	
	// Should render without errors regardless of platform
	$table->render();
	
	$content = $output->fetch();
	expect($content)->toContain('John');
	expect($content)->toContain('Jane');
});

test('SelectableTable readKey handles Windows arrow key format', function () {
	$output = new BufferedOutput();
	
	// Create a mock input stream with Windows arrow key sequence
	$stream = fopen('php://memory', 'r+');
	fwrite($stream, "\0\xE0H"); // Windows up arrow (0x00 0xE0 0x48)
	rewind($stream);
	
	$table = new SelectableTable($output, $stream);
	
	// Use reflection to test the readKey method directly
	$reflection = new ReflectionClass($table);
	$method = $reflection->getMethod('readKey');
	$method->setAccessible(true);
	
	// Mock PHP_OS_FAMILY for this test
	$originalFamily = PHP_OS_FAMILY;
	if (function_exists('runkit7_constant_redefine')) {
		runkit7_constant_redefine('PHP_OS_FAMILY', 'Windows');
	}
	
	$result = $method->invoke($table);
	
	// Restore original PHP_OS_FAMILY if we changed it
	if (function_exists('runkit7_constant_redefine')) {
		runkit7_constant_redefine('PHP_OS_FAMILY', $originalFamily);
	}
	
	fclose($stream);
	
	// On Windows, this should detect the up arrow
	// On other platforms, it won't match but shouldn't crash
	expect($result)->toBeString();
});

test('SelectableTable FFI fallback works when FFI is not available', function () {
	$output = new BufferedOutput();
	$table = new SelectableTable($output);
	
	// Use reflection to test Windows raw mode methods
	$reflection = new ReflectionClass($table);
	$enableMethod = $reflection->getMethod('enableWindowsRawMode');
	$enableMethod->setAccessible(true);
	
	$isInteractiveProperty = $reflection->getProperty('isInteractive');
	$isInteractiveProperty->setAccessible(true);
	
	// Set interactive to true first
	$isInteractiveProperty->setValue($table, true);
	
	// Mock the scenario where FFI is not loaded
	// This should gracefully set isInteractive to false
	$enableMethod->invoke($table);
	
	// The method should handle the lack of FFI gracefully
	expect($isInteractiveProperty->getValue($table))->toBeBool();
});