<?php

declare(strict_types=1);

namespace Fulcrum\Database\Seeders;

use Fulcrum\Support\Str;
use InvalidArgumentException;

class SeederCreator
{
    public function create(string $path, string $name): string
    {
        $class = $this->className($name);

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $fullPath = rtrim($path, '/') . '/' . $class . '.php';

        if (file_exists($fullPath)) {
            throw new InvalidArgumentException("Seeder [{$class}] already exists.");
        }

        file_put_contents($fullPath, $this->stub($class));

        return $fullPath;
    }

    private function className(string $name): string
    {
        $name = trim($name);

        if (!preg_match('/^[A-Za-z0-9_\\\\]+$/', $name)) {
            throw new InvalidArgumentException('Seeder name may only contain letters, numbers, underscores, and namespace separators.');
        }

        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $class = Str::pascal($name);

        return str_ends_with($class, 'Seeder') ? $class : $class . 'Seeder';
    }

    private function stub(string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Database\\Seeders;

use Fulcrum\\Database\\Seeders\\Seeder;

class {$class} implements Seeder
{
    public function run(): void
    {
        //
    }
}
PHP;
    }
}
