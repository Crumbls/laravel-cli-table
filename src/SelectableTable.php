<?php

namespace Crumbls\LaravelCliTable;

use FFI;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class SelectableTable extends Table
{
	private const READ_BUFFER_SIZE = 16;
	private int $selectedRow = 0;
	private bool $isInteractive = true;
	private array $originalRows = [];
	private array $originalHeaders = [];
	private OutputInterface $tableOutput;
	protected $inputStream;
	private string $selectedBackgroundColor = 'cyan';
	private string $selectedForegroundColor = 'black';
	private ?int $originalConsoleMode = null;
	private TableStyle $tableStyle;

	public function __construct(OutputInterface $output, $inputStream = null)
	{
		parent::__construct($output);
		$this->tableOutput = $output;
		$this->inputStream = $inputStream ?? STDIN;
		$this->setupStyles();
	}

	private function setupStyles(): void
	{
		$this->tableOutput->getFormatter()->setStyle('selected', new OutputFormatterStyle(
			$this->selectedForegroundColor, 
			$this->selectedBackgroundColor
		));
		$this->tableOutput->getFormatter()->setStyle('normal', new OutputFormatterStyle());
	}

	public function setRows(array $rows): static
	{
		$this->originalRows = $rows;
		$this->selectedRow = 0;
		return $this;
	}
	
	public function setHeaders(array $headers): static
	{
		$this->originalHeaders = $headers;
		return $this;
	}

	public function addRow($row): static
	{
		$this->originalRows[] = $row;
		return $this;
	}
	
	public function setSelectedColors(string $background, string $foreground = 'black'): static
	{
		$this->selectedBackgroundColor = $background;
		$this->selectedForegroundColor = $foreground;
		$this->setupStyles(); // Refresh formatter styles
		return $this;
	}
	
	public function setSelectedBackgroundColor(string $color): static
	{
		$this->selectedBackgroundColor = $color;
		$this->setupStyles(); // Refresh formatter styles
		return $this;
	}
	
	public function setSelectedForegroundColor(string $color): static
	{
		$this->selectedForegroundColor = $color;
		$this->setupStyles(); // Refresh formatter styles
		return $this;
	}
	

	public function getSelectedRowIndex(): int
	{
		return $this->selectedRow;
	}

	public function getSelectedRow(): ?array
	{
		if ($this->selectedRow === -1 || !isset($this->originalRows[$this->selectedRow])) {
			return null;
		}
		return $this->originalRows[$this->selectedRow];
	}

	public function render(): void
	{
		if ($this->isInteractive) {
			$this->drawFullTable();
			$this->handleInput();
		} else {
			$renderTable = new Table($this->tableOutput);
			if (!empty($this->originalHeaders)) {
				$renderTable->setHeaders($this->originalHeaders);
			}
			$renderTable->setRows($this->originalRows);
			$renderTable->render();
		}
	}

	private function drawFullTable(): void
	{
		$this->tableOutput->write(sprintf("\033\143"));

		// Safety check: ensure selectedRow is within bounds
		$maxRow = count($this->originalRows) - 1;
		
		if ($this->selectedRow < 0) {
			$this->selectedRow = 0;
		} elseif ($this->selectedRow > $maxRow) {
			$this->selectedRow = $maxRow;
		}

		$this->tableOutput->writeln(__('cli-table::table.instructions'));

		$this->tableOutput->writeln('');

		$styledRows = [];
		
		foreach ($this->originalRows as $index => $row) {
			if ($index === $this->selectedRow) {
				$styledRow = [];
				foreach ($row as $cell) {
				    if ($cell instanceof TableCell) {
				        // For TableCell objects, preserve colspan and add styling
				        $styledRow[] = new TableCell(
				            $cell->__toString(),
				            [
				                'colspan' => $cell->getColspan(),
				                'style' => new TableCellStyle([
				                    'bg' => $this->selectedBackgroundColor,
				                    'fg' => $this->selectedForegroundColor
				                ])
				            ]
				        );
				    } else {
				        // For regular cells, convert to TableCell with styling for seamless bar
				        $styledRow[] = new TableCell(
				            $cell,
				            [
				                'style' => new TableCellStyle([
				                    'bg' => $this->selectedBackgroundColor,
				                    'fg' => $this->selectedForegroundColor
				                ])
				            ]
				        );
				    }
				}
				$styledRows[] = $styledRow;
			} else {
				$styledRows[] = $row;
			}
		}

		// Create a fresh table instance for rendering
		$renderTable = new Table($this->tableOutput);
		if (!empty($this->originalHeaders)) {
			$renderTable->setHeaders($this->originalHeaders);
		}

		$tableStyle = $this->getTableStyle();

		$renderTable->setStyle($tableStyle);

		$renderTable->setRows($styledRows);
		$renderTable->render();
	}

	private function handleInput(): void
	{
		$this->enableRawMode();
		
		while (true) {
			$key = $this->readKey();
			switch ($key) {
				case 'up': // Up arrow
				    if ($this->selectedRow > 0) {
				        $this->selectedRow--;
				        $this->drawFullTable();
				    }
				    break;
				    
				case 'down': // Down arrow
				    $maxRow = count($this->originalRows) - 1;
				    if ($this->selectedRow < $maxRow) {
				        $this->selectedRow++;
				        $this->drawFullTable();
				    }
				    break;
				case 'enter':
				    $this->disableRawMode();
				    $this->tableOutput->write(sprintf("\033\143"));
				    return;
				case 'escape':
				    $this->disableRawMode();
				    $this->tableOutput->write(sprintf("\033\143"));
				    $this->tableOutput->writeln(__('cli-table::table.selection_cancelled'));
				    $this->selectedRow = -1; // Indicate no selection
				    return;
			}
		}
	}

	private function enableRawMode(): void
	{
		if (PHP_OS_FAMILY === 'Windows') {
			$this->enableWindowsRawMode();
		} elseif (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
			$result = null;
			system('stty -icanon -echo 2>/dev/null', $result);
			if ($result !== 0) {
				// Fallback if stty fails - non-interactive mode
				$this->isInteractive = false;
			}
		}
	}

	private function disableRawMode(): void
	{
		if (PHP_OS_FAMILY === 'Windows') {
			$this->disableWindowsRawMode();
		} elseif (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
			$result = null;
			system('stty icanon echo 2>/dev/null', $result);
		}
	}

	private function enableWindowsRawMode(): void
	{
		if (!extension_loaded('ffi')) {
			$this->isInteractive = false;
			return;
		}

		try {
			/** @var FFI $ffi */
			$ffi = FFI::cdef('
				typedef void* HANDLE;
				typedef unsigned long DWORD;
				typedef int BOOL;
				
				HANDLE GetStdHandle(DWORD nStdHandle);
				BOOL GetConsoleMode(HANDLE hConsoleHandle, DWORD* lpMode);
				BOOL SetConsoleMode(HANDLE hConsoleHandle, DWORD dwMode);
			', 'kernel32.dll');

			/** @var mixed $handle */
			/** @phpstan-ignore-next-line */
			$handle = $ffi->GetStdHandle(-10); // STD_INPUT_HANDLE
			/** @var FFI\CData $mode */
			$mode = FFI::new('DWORD');
			
			/** @var bool $result */
			/** @phpstan-ignore-next-line */
			$result = $ffi->GetConsoleMode($handle, FFI::addr($mode));
			if ($result) {
				/** @var int $currentMode */
				/** @phpstan-ignore-next-line */
				$currentMode = $mode->cdata;
				$this->originalConsoleMode = $currentMode;
				// Disable ENABLE_LINE_INPUT (0x0002) and ENABLE_ECHO_INPUT (0x0004)
				$newMode = $currentMode & ~(0x0002 | 0x0004);
				/** @phpstan-ignore-next-line */
				$ffi->SetConsoleMode($handle, $newMode);
			} else {
				$this->isInteractive = false;
			}
		} catch (\Throwable $e) {
			// Fallback to non-interactive mode if FFI fails
			$this->isInteractive = false;
		}
	}

	private function disableWindowsRawMode(): void
	{
		if (!extension_loaded('ffi') || $this->originalConsoleMode === null) {
			return;
		}

		try {
			/** @var FFI $ffi */
			$ffi = FFI::cdef('
				typedef void* HANDLE;
				typedef unsigned long DWORD;
				typedef int BOOL;
				
				HANDLE GetStdHandle(DWORD nStdHandle);
				BOOL SetConsoleMode(HANDLE hConsoleHandle, DWORD dwMode);
			', 'kernel32.dll');

			/** @var mixed $handle */
			/** @phpstan-ignore-next-line */
			$handle = $ffi->GetStdHandle(-10); // STD_INPUT_HANDLE
			/** @phpstan-ignore-next-line */
			$ffi->SetConsoleMode($handle, $this->originalConsoleMode);
			$this->originalConsoleMode = null;
		} catch (\Throwable $e) {
			// Ignore errors when restoring
		}
	}

	private function readKey(): string
	{
		$c = fread($this->inputStream, self::READ_BUFFER_SIZE);
		
		// Handle escape key
		if ($c === "\e" || $c === "\033") {
			return 'escape';
		}
		
		// Handle enter/return
		if ($c === "\r" || $c === "\n" || $c === "\r\n") {
			return 'enter';
		}
		
		// Handle arrow keys - different formats across platforms
		if (strlen($c) >= 3) {
			// Standard Unix/Linux/Mac format: \033[A, \033[B, etc.
			if ($c[0] === "\033" && $c[1] === '[') {
				switch ($c[2]) {
				    case 'A': return 'up';
				    case 'B': return 'down';
				    case 'C': return 'right';
				    case 'D': return 'left';
				}
			}
			
			// Alternative format (some terminals): \e[A, \e[B, etc.
			if ($c[0] === "\e" && $c[1] === '[') {
				switch ($c[2]) {
				    case 'A': return 'up';
				    case 'B': return 'down';
				    case 'C': return 'right';
				    case 'D': return 'left';
				}
			}
			
			// Windows format: \0x00\0xE0<key>
			if (PHP_OS_FAMILY === 'Windows' && $c[0] === "\0" && $c[1] === "\xE0") {
				switch (ord($c[2])) {
				    case 72: return 'up';    // 0x48
				    case 80: return 'down';  // 0x50
				    case 77: return 'right'; // 0x4D
				    case 75: return 'left';  // 0x4B
				}
			}
		}
		
		// Clean the input for any remaining checks
		$cleaned = preg_replace('/[^[:print:]\n]/u', '', mb_convert_encoding($c, 'UTF-8', 'UTF-8'));
		
		// Handle cleaned arrow sequences (your Mac format)
		switch ($cleaned) {
			case '[A': return 'up';
			case '[B': return 'down';
			case '[C': return 'right';
			case '[D': return 'left';
		}
		
		// Handle single character inputs
		switch ($c) {
			case 'q':
			case 'Q': 
				return 'escape';
			case "\x03": // Ctrl+C
				return 'escape';
		}
		
		return '';
	}

	public function setInteractive(bool $interactive): self
	{
		$this->isInteractive = $interactive;
		return $this;
	}

	public function selectRow(?callable $callback = null): mixed
	{
		$this->render();
		
		if ($callback && $this->getSelectedRow()) {
			return $callback($this->getSelectedRow(), $this->getSelectedRowIndex());
		}
		
		return $this->getSelectedRow();
	}

	public function getTableStyle() : TableStyle {
		if (!isset($this->tableStyle)) {
			// Create a custom table style that matches your terminal design
			$this->tableStyle = new TableStyle();
		}

		return $this->tableStyle;
	}

	public function setTableStyle(TableStyle $tableStyle): self {
		$this->tableStyle = $tableStyle;
		return $this;
	}
}