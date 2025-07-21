<?php

use Crumbls\LaravelCliTable\SelectableTable;
use Symfony\Component\Console\Output\BufferedOutput;

class TestableSelectableTable extends SelectableTable
{
    public function testReadKey(string $input): string
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Failed to create memory stream');
        }
        
        fwrite($stream, $input);
        rewind($stream);
        
        $testTable = new SelectableTable(new BufferedOutput(), $stream);
        $reflection = new ReflectionClass($testTable);
        $method = $reflection->getMethod('readKey');
        $method->setAccessible(true);
        
        /** @var string $result */
        $result = $method->invoke($testTable);
        
        fclose($stream);
        
        return $result;
    }
}

test('readKey detects up arrow key - standard format', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    expect($table->testReadKey("\033[A"))->toBe('up');
});

test('readKey detects down arrow key - standard format', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    expect($table->testReadKey("\033[B"))->toBe('down');
});

test('readKey detects up arrow key - alternative format', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    expect($table->testReadKey("\e[A"))->toBe('up');
});

test('readKey detects down arrow key - alternative format', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    expect($table->testReadKey("\e[B"))->toBe('down');
});

test('readKey detects enter key variations', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    expect($table->testReadKey("\n"))->toBe('enter');
    expect($table->testReadKey("\r"))->toBe('enter');
    expect($table->testReadKey("\r\n"))->toBe('enter');
});

test('readKey detects escape key variations', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    expect($table->testReadKey("\e"))->toBe('escape');
    expect($table->testReadKey("\033"))->toBe('escape');
    expect($table->testReadKey("q"))->toBe('escape');
    expect($table->testReadKey("Q"))->toBe('escape');
    expect($table->testReadKey("\x03"))->toBe('escape'); // Ctrl+C
});

test('readKey detects left and right arrows', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    expect($table->testReadKey("\033[C"))->toBe('right');
    expect($table->testReadKey("\033[D"))->toBe('left');
});

test('readKey handles Mac cleaned format', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    // Simulate the cleaned format your Mac produces
    expect($table->testReadKey("[A"))->toBe('up');
    expect($table->testReadKey("[B"))->toBe('down');
});

test('readKey returns empty string for unrecognized input', function () {
    $output = new BufferedOutput();
    $table = new TestableSelectableTable($output);
    
    expect($table->testReadKey("xyz"))->toBe('');
    expect($table->testReadKey("123"))->toBe('');
});

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

test('SelectableTable handles cancelled selection', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    
    $reflection = new ReflectionClass($table);
    $property = $reflection->getProperty('selectedRow');
    $property->setAccessible(true);
    $property->setValue($table, -1);
    
    expect($table->getSelectedRow())->toBeNull();
    expect($table->getSelectedRowIndex())->toBe(-1);
});

test('SelectableTable color customization', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setSelectedColors('red', 'white');
    $table->setSelectedBackgroundColor('green');
    $table->setSelectedForegroundColor('yellow');
    
    $reflection = new ReflectionClass($table);
    $bgProperty = $reflection->getProperty('selectedBackgroundColor');
    $bgProperty->setAccessible(true);
    $fgProperty = $reflection->getProperty('selectedForegroundColor');
    $fgProperty->setAccessible(true);
    
    expect($bgProperty->getValue($table))->toBe('green');
    expect($fgProperty->getValue($table))->toBe('yellow');
});

test('SelectableTable fluent interface', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $result = $table
        ->setHeaders(['ID', 'Name'])
        ->setRows([['1', 'John']])
        ->addRow(['2', 'Jane'])
        ->setSelectedColors('blue', 'white')
        ->setInteractive(false);
    
    expect($result)->toBe($table);
});

test('SelectableTable with empty data', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    expect($table->getSelectedRowIndex())->toBe(0);
    expect($table->getSelectedRow())->toBeNull();
});

test('SelectableTable bounds checking', function () {
    $output = new BufferedOutput();
    $table = new SelectableTable($output);
    
    $table->setRows([['1', 'John'], ['2', 'Jane']]);
    
    $reflection = new ReflectionClass($table);
    $property = $reflection->getProperty('selectedRow');
    $property->setAccessible(true);
    $property->setValue($table, 10); // Out of bounds
    
    $method = $reflection->getMethod('drawFullTable');
    $method->setAccessible(true);
    $method->invoke($table);
    
    expect($property->getValue($table))->toBe(1); // Should be clamped to max valid index
});