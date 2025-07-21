<?php

namespace Crumbls\LaravelCliTable;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class SelectableTable extends Table
{
    private int $selectedRow = 0;
    private bool $isInteractive = true;
    private array $originalRows = [];
    private array $originalHeaders = [];
    private OutputInterface $tableOutput;
    protected $inputStream;
    private string $selectedBackgroundColor = 'cyan';
    private string $selectedForegroundColor = 'black';

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

        $this->tableOutput->writeln('Use ↑/↓ arrows to navigate, Enter to select, q/Esc to exit');
        $this->tableOutput->writeln('Selected Row: ' . ($this->selectedRow + 1) . '/' . count($this->originalRows));
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
                    system('clear');
                    return;
                case 'escape':
                    $this->disableRawMode();
                    system('clear');
                    $this->tableOutput->writeln('Selection cancelled');
                    $this->selectedRow = -1; // Indicate no selection
                    return;
            }
        }
    }

    private function enableRawMode(): void
    {
        if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
            system('stty -icanon -echo');
        }
    }

    private function disableRawMode(): void
    {
        if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
            system('stty icanon echo');
        }
    }

    protected function readKey(): string
    {
        $c = fread($this->inputStream, 16);
        
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
//        $this->renderInteractive();
        
        if ($callback && $this->getSelectedRow()) {
            return $callback($this->getSelectedRow(), $this->getSelectedRowIndex());
        }
        
        return $this->getSelectedRow();
    }
}