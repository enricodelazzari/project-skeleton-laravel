#!/usr/bin/env php
<?php

function replace_in_file(string $file, array $replacements): void {
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        str_replace(
            array_keys($replacements),
            array_values($replacements),
            $contents
        )
    );
}

function edit_composer_json(Closure $callback): void
{
    $data = json_decode(
        file_get_contents('composer.json'),
        true
    );

    $data = $callback($data);

    file_put_contents(
        'composer.json',
        json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
    );
}

function ask(string $question, string $default = ''): string {
    $answer = readline($question . ($default ? " ({$default})" : null) . ': ');

    if (! $answer) {
        return $default;
    }

    return $answer;
}

function confirm(string $question, bool $default = false): bool {
    $answer = ask($question . ' (' . ($default ? 'Y/n' : 'y/N') . ')');

    if (! $answer) {
        return $default;
    }

    return strtolower($answer) === 'y';
}

function writeln(string $line): void {
    echo $line . PHP_EOL;
}

function run(string $command): string {
    return trim(shell_exec($command));
}

function dev_clean_all() {
    if (confirm('Clear all?', false)) {
        run('git clean -fd');
        run('git clean -fd');
        exit(1);
    }
}

function vendor_publish(string $provider, ?string $tag = null)
{
    $tag = $tag ? "--tag=\"{$tag}\"" : '';
    run("php artisan vendor:publish --provider=\"{$provider}\" {$tag}");
}

function composer_require($package)
{
    run("composer require {$package}");
}

if (! confirm('Install Laravel?', true)) {
    exit(1);
}

run('rm README.md LICENSE.md');

writeln('------ LARAVEL: INSTALLING ------');
run('composer create-project laravel/laravel laravel --no-interaction --no-cache --quiet');
run('mv laravel/{.,}* . 2>/dev/null');
run('rmdir laravel');
writeln('------ LARAVEL: INSTALLED ------');
writeln('');

writeln('------ LARAVEL TAIL: INSTALLING ------');
composer_require('spatie/laravel-tail  --no-interaction --no-cache --quiet');
vendor_publish('Spatie\Tail\TailServiceProvider');
writeln('------ LARAVEL TAIL: INSTALLED ------');
writeln('');

writeln('------ LARAVEL RAY: INSTALLING ------');
composer_require('spatie/laravel-ray --dev --no-interaction --no-cache --quiet');
run('php artisan ray:publish-config');
writeln('------ LARAVEL RAY: INSTALLED ------');
writeln('');

composer_require('friendsofphp/php-cs-fixer --dev --no-interaction --no-cache --quiet');
composer_require('vimeo/psalm --dev --no-interaction --no-cache --quiet');
writeln('');

writeln('------ LARAVEL: CONFIGURING ------');
run('echo ".php-cs-fixer.cache" >> .gitignore');

$search = <<<'PHP'
$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);
PHP;

$replace = <<<'PHP'
$app = (new App\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
))->useAppPath('src/App');
PHP;

replace_in_file('bootstrap/app.php', [
    $search => $replace,
    'App\Http\Kernel::class' => 'App\HttpKernel::class',
    'App\Console\Kernel::class' => 'App\ConsoleKernel::class',
]);

run('mv app/Providers/* src/App/Providers');
run('mv app/Http/Controllers/Controller.php src/App/Controller.php');
replace_in_file('src/App/Controller.php', [
    'namespace App\Http\Controllers;' => 'namespace App;',
]);

run('mv app/Http/Middleware/* src/Support/Middleware');
foreach (array_diff(scandir('src/Support/Middleware'), ['.', '..']) as $file) {
    replace_in_file("src/Support/Middleware/{$file}", [
        'namespace App\Http\Middleware;' => 'namespace Support\Middleware;',
    ]);
}

run('mv app/Exceptions/Handler.php src/App/Exceptions/Handler.php');
run('mv app/Http/Kernel.php src/App/HttpKernel.php');
replace_in_file('src/App/HttpKernel.php', [
    'namespace App\Http;' => 'namespace App;',
    ' as HttpKernel' => '',
    'class Kernel extends HttpKernel' => 'class HttpKernel extends Kernel',
    'App\Http\Middleware' => 'Support\Middleware',
]);
run('mv app/Console/Kernel.php src/App/ConsoleKernel.php');
replace_in_file('src/App/ConsoleKernel.php', [
    'namespace App\Console;' => 'namespace App;',
    ' as ConsoleKernel' => '',
    'class Kernel extends ConsoleKernel' => 'class ConsoleKernel extends Kernel',
]);

run('mv app/Models/User.php src/Domain/Users/Models/User.php');
replace_in_file('src/Domain/Users/Models/User.php', [
    'namespace App\Models;' => 'namespace Domain\Users\Models;',
]);

edit_composer_json(function ($data) {
    $data['autoload']['psr-4'] = [
        'App\\' => 'src/App/',
        'Domain\\' => 'src/Domain/',
        'Support\\' => 'src/Support/',
        'Database\\Factories\\'=> 'database/factories/',
        'Database\\Seeders\\'=> 'database/seeders/',
    ];

    $data['autoload']['files'] = [
        'src/Support/helpers.php',
    ];

    $data['scripts'] = array_merge($data['scripts'], [
        'format' => 'vendor/bin/php-cs-fixer fix --allow-risky=yes',
        'psalm' => 'vendor/bin/psalm',
    ]);

    return $data;
});

run('rm -Rf app');
run('composer format --quiet');
run('composer dumpautoload --no-interaction --quiet');
writeln('------ LARAVEL: CONFIGURED ------');
writeln('');

writeln(
    run('php artisan inspire')
);
writeln('');

// dev_clean_all();

confirm('Let this script delete itself?', true) && unlink(__FILE__);