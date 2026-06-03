<?php

declare(strict_types=1);

namespace Fulcrum\Database\Migrations;

use Fulcrum\Support\Str;
use InvalidArgumentException;

class MigrationCreator
{
    public function create(string $path, string $name): string
    {
        $name = trim($name);

        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Migration name may only contain letters, numbers, and underscores.');
        }

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $file = gmdate('Y_m_d_His') . '_' . Str::snake($name) . '.php';
        $fullPath = rtrim($path, '/') . '/' . $file;

        file_put_contents($fullPath, $this->stub());

        return $fullPath;
    }

    private function stub(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        //
    }

    public function down(ConnectionInterface $db): void
    {
        //
    }
};
PHP;
    }
}
