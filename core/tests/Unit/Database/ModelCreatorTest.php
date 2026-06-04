<?php

declare(strict_types=1);

use Fulcrum\Database\ModelCreator;
use Fulcrum\GraphQL\ResourceCreator;

function modelCreatorTestPath(string $prefix): string
{
    $path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6));
    mkdir($path, 0777, true);

    return $path;
}

it('creates model files from safe names', function () {
    $path = modelCreatorTestPath('fulcrum-models');
    $file = (new ModelCreator())->create($path, 'post');

    expect($file)->toEndWith('/Post.php')
        ->and(file_get_contents($file))->toContain('class Post extends Model')
        ->and(file_get_contents($file))->toContain("protected string \$table = 'posts';");

    unlink($file);
    rmdir($path);
});

it('creates graphQL resource files with CRUD scaffolding', function () {
    $path = modelCreatorTestPath('fulcrum-resource');
    mkdir($path . '/src', 0777, true);

    $files = (new ResourceCreator())->create($path, 'post', ['title:string', 'published:boolean']);

    expect($files)->toHaveCount(4)
        ->and(file_get_contents($path . '/src/Models/Post.php'))->toContain('class Post extends Model')
        ->and(file_get_contents($path . '/src/GraphQL/PostType.php'))->toContain("public string \$title")
        ->and(file_get_contents($path . '/src/GraphQL/PostType.php'))->toContain("public bool \$published")
        ->and(file_get_contents($path . '/src/GraphQL/PostMutation.php'))->toContain("createPost")
        ->and(file_get_contents($path . '/src/GraphQL/PostMutation.php'))->toContain("deletePost");

    foreach (array_reverse($files) as $file) {
        unlink($file);
    }

    rmdir($path . '/src/GraphQL');
    rmdir($path . '/src/Models');
    rmdir($path . '/src');
    rmdir($path);
});
