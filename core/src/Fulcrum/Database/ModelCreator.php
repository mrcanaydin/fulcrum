<?php

declare(strict_types=1);

namespace Fulcrum\Database;

use Fulcrum\Support\Str;
use InvalidArgumentException;

class ModelCreator
{
    public function create(string $path, string $name): string
    {
        $class = $this->className($name);

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $fullPath = rtrim($path, '/') . '/' . $class . '.php';

        if (file_exists($fullPath)) {
            throw new InvalidArgumentException("Model [{$class}] already exists.");
        }

        file_put_contents($fullPath, $this->stub($class));

        return $fullPath;
    }

    public function className(string $name): string
    {
        $name = trim($name);

        if (!preg_match('/^[A-Za-z0-9_\\\\]+$/', $name)) {
            throw new InvalidArgumentException('Model name may only contain letters, numbers, underscores, and namespace separators.');
        }

        $name = str_replace('\\', '/', $name);

        return Str::pascal(basename($name));
    }

    private function stub(string $class): string
    {
        $table = Str::plural(Str::snake($class));

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Models;

use Fulcrum\\Database\\Model;

class {$class} extends Model
{
    protected string \$table = '{$table}';
}
PHP;
    }
}
