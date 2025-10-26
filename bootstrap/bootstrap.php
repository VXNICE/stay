<?php

require_once __DIR__ . '/../index.php';

/*
| This is your application bootstrap file.
| This is where you should load your environment, configuration, database,
| and common functions.
| This is the centralized location for all of your application's bootstrap
| logic.
*/

/**
 * Simple .env loader for vanilla PHP
 * Usage: loadEnv(__DIR__ . '/.env');
 * @throws Exception
 */
function loadEnv(): void
{
    $path = APP_PATH . '/.env';
    if (!file_exists($path)) {
        throw new Exception(".env file not found at {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        # Skip comments
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        # Split key=value
        list($key, $value) = array_map('trim', explode('=', $line, 2));

        # Remove optional quotes
        $value = trim($value, "\"'");

        # Set environment variable
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

/**
 * Retrieves the value of an environment variable by its key. If the variable
 * is not set, the provided default value will be returned.
 *
 * @param string $key The name of the environment variable to retrieve.
 * @param mixed $default The default value to return if the environment variable is not set.
 * @return mixed The value of the environment variable, or the default value if not set.
 */
function env(string $key, $default = null): mixed
{
    return getenv($key) ?: $default;
}

/**
 * Redirects the user to the specified URL and terminates the current script execution.
 *
 * @param string $url The URL to redirect the user to.
 * @return void
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Outputs formatted data and halts script execution.
 *
 * @param mixed $data The data to be formatted and displayed.
 * @return void This method does not return a value as it terminates script execution after output.
 */
function dd(mixed $data): void
{
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    exit;
}

/**
 * Global helper function for app configuration.
 * Supports: config('file.key.subkey')
 * Example:
 *   config('app.name');
 *   config(['app.debug' => false]);
 */
function config(string|array $key = null, $default = null)
{
    static $configs = []; # Cache all loaded configs

    // Lazy-load all config files when first called
    if (empty($configs)) {
        $configDir = CONFIG_PATH;
        foreach (glob($configDir . '/*.php') as $file) {
            $name = basename($file, '.php');
            $configs[$name] = require $file;
        }
    }

    // Set multiple config values
    if (is_array($key)) {
        foreach ($key as $fullKey => $value) {
            $segments = explode('.', $fullKey);
            $file = array_shift($segments);

            if (!isset($configs[$file])) {
                $configs[$file] = [];
            }

            $ref = &$configs[$file];
            foreach ($segments as $segment) {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }
            $ref = $value;
        }
        return true;
    }

    // Return all configs
    if (is_null($key)) {
        return $configs;
    }

    // Access with dot notation
    $segments = explode('.', $key);
    $file = array_shift($segments);

    if (!isset($configs[$file])) {
        return $default;
    }

    $value = $configs[$file];

    foreach ($segments as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
            $value = $value[$segment];
        } else {
            return $default;
        }
    }

    return $value;
}

/**
 * Initializes the application by loading the environment variables.
 *
 * @return void
 */
function bootstrap(): void
{
    try {
        loadEnv();
    } catch (Exception $e) {
        echo $e->getMessage();
        exit;
    }
}

bootstrap();

# Load up your app's configuration array
# You don't want to access your config directly from the
# environment variables, so you should load it from a file.
# SEPARATION OF CONCERNS, NIGGA
$_GLOBAL_CONFIG = require_once CONFIG_PATH . '/config.php';

# Now include your database connection
# This will expose the $pdo object into your entire application
require_once DATABASE_PATH . '/db.php';
