<?php

use Crumbls\LaravelCliTable\SelectableTable;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Helper\TableCell;

test('SelectableTable basic functionality', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setHeaders(['ID', 'Name']);
    $table->setRows([
        ['1', 'John'],
        ['2', 'Jane'],
    ]);
    
    expect($table->getSelectedRowIndex())->toBe(0);
    expect($table->getSelectedRow())->toBe(['1', 'John']);
});

test('SelectableTable non-interactive mode', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setHeaders(['ID', 'Name']);
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    $table->setInteractive(false);
    
    $table->render();
    
    $content = $output->fetch();
    expect($content)->toContain('John');
    expect($content)->toContain('Jane');
});

test('SelectableTable with TableCell objects', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setHeaders(['ID', 'Name', 'Email', 'Status']);
    $table->setRows([
        ['1', 'John', 'john@example.com', 'Active'],
        ['2', 'Jane', 'jane@example.com', 'Active'],
    ]);
    $table->addRow([new TableCell('+ Add New User', ['colspan' => 4])]);
    
    expect(count($table->getSelectedRow() ?? []))->toBeGreaterThan(0);
});

test('SelectableTable color defaults', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $reflection = new ReflectionClass($table);
    $bgProperty = $reflection->getProperty('selectedBackgroundColor');
    $bgProperty->setAccessible(true);
    $fgProperty = $reflection->getProperty('selectedForegroundColor');
    $fgProperty->setAccessible(true);
    
    expect($bgProperty->getValue($table))->toBe('cyan');
    expect($fgProperty->getValue($table))->toBe('black');
});

test('SelectableTable method chaining', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $result = $table->setSelectedColors('red', 'white');
    expect($result)->toBeInstanceOf(SelectableTable::class);
    
    $result = $table->setSelectedBackgroundColor('blue');
    expect($result)->toBeInstanceOf(SelectableTable::class);
    
    $result = $table->setSelectedForegroundColor('yellow');
    expect($result)->toBeInstanceOf(SelectableTable::class);
});

test('SelectableTable setRows resets selectedRow', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    
    // Manually set selectedRow to 1
    $reflection = new ReflectionClass($table);
    $property = $reflection->getProperty('selectedRow');
    $property->setAccessible(true);
    $property->setValue($table, 1);
    
    expect($table->getSelectedRowIndex())->toBe(1);
    
    // setRows should reset to 0
    $table->setRows([['3', 'Bob'], ['4', 'Alice'], ['5', 'Carol']]);
    expect($table->getSelectedRowIndex())->toBe(0);
});

test('SelectableTable addRow preserves existing rows', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    $table->addRow(['3', 'Bob']);
    
    $reflection = new ReflectionClass($table);
    $property = $reflection->getProperty('originalRows');
    $property->setAccessible(true);
    $rows = $property->getValue($table);
    
    expect(count($rows))->toBe(3);
    expect($rows[2])->toBe(['3', 'Bob']);
});

test('SelectableTable with custom input stream', function () {
    $output = new BufferedOutput();
    $stream = fopen('php://memory', 'r+');
    
    $table = new SelectableTable($output, $stream);
    expect($table)->toBeInstanceOf(SelectableTable::class);
    
    fclose($stream);
});

test('SelectableTable selectRow with callback', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    $table->setInteractive(false); // Prevent hanging
    
    $callbackCalled = false;
    $result = $table->selectRow(function($row, $index) use (&$callbackCalled) {
        $callbackCalled = true;
        return ['custom' => $row, 'idx' => $index];
    });
    
    expect($result)->toBe(['custom' => ['1', 'John'], 'idx' => 0]);
});

test('SelectableTable selectRow without callback', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    $table->setInteractive(false); // Prevent hanging
    
    $result = $table->selectRow();
    expect($result)->toBe(['1', 'John']);
});

test('SelectableTable selectRow with no selected row returns null', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    $table->setInteractive(false);
    
    // Simulate cancelled selection
    $reflection = new ReflectionClass($table);
    $property = $reflection->getProperty('selectedRow');
    $property->setAccessible(true);
    $property->setValue($table, -1);
    
    $result = $table->selectRow();
    expect($result)->toBeNull();
});

test('SelectableTable with style configuration', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    // Test that styles are set up without errors
    $table->setSelectedColors('magenta', 'yellow');
    
    $table->setRows([['1', 'John']]);
    $table->setInteractive(false);
    $table->render();
    
    expect($output->fetch())->toContain('John');
});

test('SelectableTable drawFullTable bounds safety', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    
    // Test negative selectedRow
    $reflection = new ReflectionClass($table);
    $property = $reflection->getProperty('selectedRow');
    $property->setAccessible(true);
    $property->setValue($table, -5);
    
    $method = $reflection->getMethod('drawFullTable');
    $method->setAccessible(true);
    $method->invoke($table);
    
    expect($property->getValue($table))->toBe(0); // Should be clamped to 0
});

test('SelectableTable with mixed TableCell and string rows', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setHeaders(['Col1', 'Col2']);
    $table->setRows([
        ['Regular', 'Row'],
        [new TableCell('Spanning Cell', ['colspan' => 2])]
    ]);
    
    $table->setInteractive(false);
    $table->render();
    
    $content = $output->fetch();
    expect($content)->toContain('Regular');
    expect($content)->toContain('Spanning Cell');
});