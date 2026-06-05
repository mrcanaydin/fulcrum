<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\Auth;

use Fulcrum\Database\Migrations\Migration;

it('ships an executable personal access token migration', function () {
    $migration = require __DIR__ . '/../../../src/Fulcrum/Auth/Migrations/create_personal_access_tokens_table.php';

    expect($migration)->toBeInstanceOf(Migration::class);
});

it('ships an executable roles and permissions migration', function () {
    $migration = require __DIR__ . '/../../../src/Fulcrum/Auth/Migrations/create_roles_and_permissions_tables.php';

    expect($migration)->toBeInstanceOf(Migration::class);
});
