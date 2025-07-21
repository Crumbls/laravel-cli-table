# Laravel CLI Table

A powerful Laravel package that brings interactive, selectable console tables to your Artisan commands. Navigate through data with arrow keys, customize colors, and build better CLI experiences.

## Why Use This Package?

Building interactive CLI tools shouldn't be complicated. This package extends Symfony's robust Console Table with navigation and selection capabilities, giving you the power to create professional command-line interfaces with just a few lines of code.

## Features

- **Interactive Navigation** - Arrow key navigation through table rows
- **Customizable Colors** - Match your application's theme
- **Symfony Foundation** - Built on Symfony Console Table (all original features included)
- **Laravel Integration** - Works seamlessly with Artisan commands
- **Cross-Platform** - Supports macOS, Linux, and Windows terminals
- **Zero Configuration** - Works out of the box

## Installation

```bash
composer require crumbls/laravel-cli-table
```

## Usage

### Basic Usage

```php
use Crumbls\LaravelCliTable\SelectableTable;

class YourCommand extends Command
{
    public function handle()
    {
        $table = new SelectableTable($this->output);
        
        $table->setHeaders(['ID', 'Name', 'Email']);
        $table->setRows([
            ['1', 'John Doe', 'john@example.com'],
            ['2', 'Jane Smith', 'jane@example.com'],
            ['3', 'Bob Johnson', 'bob@example.com'],
        ]);

        // Interactive mode - returns selected row data
        $selectedRow = $table->selectRow();
        
        if ($selectedRow) {
            $this->info("Selected: " . $selectedRow[1]); // John Doe, Jane Smith, etc.
        }
    }
}
```

### With Callback

```php
$selectedData = $table->selectRow(function($row, $index) {
    return [
        'index' => $index,
        'data' => $row,
        'id' => $row[0]
    ];
});
```

### Non-Interactive Mode

```php
$table->setInteractive(false);
$table->render(); 
```

### Customizing Selection Colors

```php
// Set both colors at once
$table->setSelectedColors('green', 'white');

// Or set them individually
$table->setSelectedBackgroundColor('magenta');
$table->setSelectedForegroundColor('yellow');

// Available colors: black, red, green, yellow, blue, magenta, cyan, white
```

## Controls

- **↑/↓ Arrow Keys**: Navigate up and down
- **Enter**: Select current row
- **Escape**: Exit without selection
- **Ctrl+C**: Force exit

## Testing

```bash
# Run safe tests (non-interactive)
composer test

# Run all tests (may hang on interactive tests)
composer test-all

# Run PHPStan analysis
composer phpstan

# Check code coverage
composer test-coverage
```

## Requirements

- PHP ^8.2
- Laravel ^12.0
- Symfony Console ^7.2

## Advanced Usage

### Building Interactive Menus

```php
class SelectUserCommand extends Command
{
    public function handle()
    {
        $users = User::all(['id', 'name', 'email', 'status']);
        
        $table = new SelectableTable($this->output);
        $table->setHeaders(['ID', 'Name', 'Email', 'Status'])
              ->setRows($users->toArray())
              ->setSelectedColors('blue', 'white');
        
        $selected = $table->selectRow(function($row, $index) {
            return User::find($row[0]);
        });
        
        if ($selected) {
            $this->info("Selected user: {$selected->name}");
        }
    }
}
```

### Adding Action Rows

```php
use Symfony\Component\Console\Helper\TableCell;

$table->addRow(['1', 'John Doe', 'john@example.com', 'Active']);
$table->addRow(['2', 'Jane Smith', 'jane@example.com', 'Active']);
$table->addRow([new TableCell('+ Add New User', ['colspan' => 4])]);
```

### Translations

The package includes translatable strings for instructions and messages. Publish the language files:

```bash
php artisan vendor:publish --tag=cli-table-lang
```

Then customize the translations in `lang/vendor/cli-table/en/table.php`:

```php
return [
    'instructions' => 'Use ↑/↓ arrows to navigate, Enter to select, q/Esc to exit',
    'selected_row' => 'Selected Row: :current/:total',
    'selection_cancelled' => 'Selection cancelled',
    // Add your own translations...
];
```

## Troubleshooting

### Arrow Keys Not Working?
- Ensure your terminal supports ANSI escape sequences
- Try using a modern terminal (Terminal.app, iTerm2, Windows Terminal)
- Some older terminals may not support interactive features

### Colors Not Displaying?
- Your terminal may not support color output
- Try different color combinations: `red`, `green`, `blue`, `yellow`, `magenta`, `cyan`, `white`, `black`

## Support

Need help or found a bug? We're here to help!

- **Report Issues**: [GitHub Issues](https://github.com/crumbls/laravel-cli-table/issues)
- **Get Help**: Join our [Discord Community](https://discord.com/channels/1389657726531145848/1396936001942978591)
- **Documentation**: Check out the examples above and in the `/tests` directory

## Contributing

We welcome contributions! Please feel free to submit pull requests or open issues to help improve this package.

## Commercial Use

We love seeing how this package is used! If you're using Laravel CLI Table in a commercial application, we'd appreciate a postcard from your city:

```
Crumbls
PO Box 15
Lafayette, CO 80026
USA
```

## Requirements

- PHP ^8.2
- Laravel ^12.0
- Symfony Console ^7.2

## License

MIT - feel free to use this package in your personal and commercial projects.