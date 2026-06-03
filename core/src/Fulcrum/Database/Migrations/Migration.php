<?php

declare(strict_types=1);

namespace Fulcrum\Database\Migrations;

use Fulcrum\Database\ConnectionInterface;

interface Migration
{
    public function up(ConnectionInterface $db): void;

    public function down(ConnectionInterface $db): void;
}
