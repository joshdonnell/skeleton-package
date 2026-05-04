#!/usr/bin/env php
<?php

declare(strict_types=1);

function supportsAnsi(): bool
{
    if (getenv('NO_COLOR') !== false) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        return (function_exists('sapi_windows_vt100_support')
            && sapi_windows_vt100_support(STDOUT))
            || getenv('ANSICON') !== false
            || getenv('ConEmuANSI') === 'ON'
            || str_starts_with((string) getenv('TERM'), 'xterm');
    }

    return stream_isatty(STDOUT);
}

function ansi(string $text, string $code): string
{
    if (! supportsAnsi()) {
        return $text;
    }

    return "\033[{$code}m{$text}\033[0m";
}

function bold(string $text): string
{
    return ansi($text, '1');
}

function dim(string $text): string
{
    return ansi($text, '2');
}

function green(string $text): string
{
    return ansi($text, '32');
}

function yellow(string $text): string
{
    return ansi($text, '33');
}

function writeln(string $line): void
{
    echo $line.PHP_EOL;
}

function ask(string $question, string $default = ''): string
{
    $prompt = bold($question);

    if ($default) {
        $prompt .= ' '.dim("({$default})");
    }

    $answer = readline('  '.$prompt.': ');

    if (! $answer) {
        return $default;
    }

    return $answer;
}

function confirm(string $question, bool $default = false): bool
{
    $answer = ask($question.' '.($default ? 'Y/n' : 'y/N'));

    if (! $answer) {
        return $default;
    }

    return strtolower($answer) === 'y';
}

function run(string $command): string
{
    return trim((string) shell_exec($command));
}

function slugify(string $subject): string
{
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $subject), '-'));
}

function titleCase(string $subject): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $subject)));
}

function titleSnake(string $subject, string $replace = '_'): string
{
    return str_replace(['-', '_'], $replace, $subject);
}

function replaceInFile(string $file, array $replacements): void
{
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

function safeUnlink(string $filename): void
{
    if (file_exists($filename) && is_file($filename)) {
        unlink($filename);
    }
}

function normalizePath(string $path): string
{
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function getFilesWithPlaceholders(): array
{
    $directory = new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directory);

    $skipDirs = ['.git', 'vendor', 'node_modules'];
    $scriptBasename = basename(__FILE__);
    $placeholders = [
        ':author_name',
        ':author_username',
        ':author_email',
        'author@example.com',
        ':vendor_name',
        ':vendor_slug',
        'vendor-name',
        'package-skeleton',
        'vendor-name/package-skeleton',
        'VendorName',
        ':package_name',
        ':package_slug',
        ':package_description',
        'Skeleton',
        'skeleton',
    ];

    $files = [];

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $relativePath = str_replace(__DIR__.DIRECTORY_SEPARATOR, '', $path);

        foreach ($skipDirs as $skipDir) {
            if (str_starts_with($relativePath, $skipDir.DIRECTORY_SEPARATOR)) {
                continue 2;
            }
        }

        if ($file->getBasename() === $scriptBasename) {
            continue;
        }

        $contents = file_get_contents($path);
        foreach ($placeholders as $placeholder) {
            if (str_contains($contents, $placeholder)) {
                $files[] = $path;
                break;
            }
        }
    }

    return $files;
}

function removePrefix(string $prefix, string $content): string
{
    if (str_starts_with($content, $prefix)) {
        return substr($content, strlen($prefix));
    }

    return $content;
}

function removeReadmeSections(string $file): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        preg_replace('/<!--delete-->.*<!--\/delete-->/s', '', $contents) ?: $contents
    );
}

writeln('');
writeln(bold('  Package Skeleton Setup'));
writeln('');
writeln('  This script will configure your new Laravel package.');
writeln('  Just answer a few questions and we will handle the rest.');
writeln('');

writeln(bold('  Author'));
writeln(dim('  Used for composer.json and LICENSE.'));
writeln('');

$gitName = run('git config user.name');
$authorName = ask('Author name', $gitName);

$gitEmail = run('git config user.email');
$authorEmail = ask('Author email', $gitEmail);
$authorUsername = ask('Author username (GitHub)', '');

writeln('');
writeln(bold('  Vendor'));
writeln(dim('  Your Packagist vendor, e.g. "spatie" in spatie/laravel-ray.'));
writeln('');

$vendorName = ask('Vendor name', $authorName);
$vendorSlug = slugify(ask('Vendor slug', slugify($vendorName)));
$vendorNamespace = titleCase($vendorSlug);
$vendorNamespace = ask('Vendor namespace', $vendorNamespace);

if ($authorUsername === '') {
    $authorUsername = $vendorSlug;
}

writeln('');
writeln(bold('  Package'));
writeln('');

$currentDirectory = getcwd();
$folderName = basename($currentDirectory);

$packageName = ask('Package name', $folderName);
$packageSlug = slugify($packageName);
$packageSlugWithoutPrefix = removePrefix('laravel-', $packageSlug);

$className = titleCase($packageName);
$className = ask('Class name', $className);
$description = ask('Package description', "This is my package {$packageSlug}");

writeln('');
writeln(bold('  Summary'));
writeln('');
writeln("  Author      {$authorName} ({$authorUsername}, {$authorEmail})");
writeln("  Vendor      {$vendorName} ({$vendorSlug})");
writeln("  Package     {$packageSlug}");
writeln("  Description {$description}");
writeln("  Namespace   {$vendorNamespace}\\{$className}");
writeln("  Class       {$className}");
writeln('');

if (! confirm('Modify files?', true)) {
    exit(1);
}

writeln('');

$files = getFilesWithPlaceholders();

foreach ($files as $file) {
    replaceInFile($file, [
        ':author_name' => $authorName,
        ':author_username' => $authorUsername,
        ':author_email' => $authorEmail,
        'author@example.com' => $authorEmail,
        ':vendor_name' => $vendorName,
        ':vendor_slug' => $vendorSlug,
        'vendor-name' => $vendorSlug,
        'package-skeleton' => $packageSlug,
        'vendor-name/package-skeleton' => "{$vendorSlug}/{$packageSlug}",
        'VendorName' => $vendorNamespace,
        ':package_name' => $packageName,
        ':package_slug' => $packageSlug,
        ':package_description' => $description,
        'Skeleton' => $className,
        'skeleton' => $packageSlug,
    ]);

    match (true) {
        str_contains($file, normalizePath('src/Skeleton.php')) => rename($file, normalizePath('./src/'.$className.'.php')),
        str_contains($file, normalizePath('src/SkeletonServiceProvider.php')) => rename($file, normalizePath('./src/'.$className.'ServiceProvider.php')),
        str_contains($file, normalizePath('src/Facades/Skeleton.php')) => rename($file, normalizePath('./src/Facades/'.$className.'.php')),
        str_contains($file, normalizePath('src/Commands/SkeletonCommand.php')) => rename($file, normalizePath('./src/Commands/'.$className.'Command.php')),
        str_contains($file, normalizePath('config/skeleton.php')) => rename($file, normalizePath('./config/'.$packageSlugWithoutPrefix.'.php')),
        str_contains($file, 'README.md') => removeReadmeSections($file),
        default => null,
    };
}

writeln(green('  ✓ Updated '.count($files).' files'));

confirm('Execute `composer install` and run tests?', true) && run('composer install && composer test');

writeln('');
confirm('Delete this configure script?', true) && safeUnlink(__FILE__);

writeln('');
writeln(green(bold('  ✨ Done! Happy building!')));
writeln('');
