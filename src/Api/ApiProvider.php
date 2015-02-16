<?php
namespace Aws\Api;

use Aws\Exception\UnresolvedApiException;
use GuzzleHttp\Utils;

/**
 * API providers.
 *
 * An API provider is a function that accepts a type, service, and version and
 * returns an array of API data on success or NULL if no API data can be created
 * for the provided arguments.
 *
 * You can wrap your calls to an API provider with the
 * {@see ApiProvider::resolve} method to ensure that API data is created. If the
 * API data is not created, then the resolve() method will throw a
 * {@see Aws\Exception\UnresolvedApiException}.
 *
 *     use Aws\Api\ApiProvider;
 *     $provider = ApiProvider::defaultProvider();
 *     // Returns an array or NULL.
 *     $data = $provider('api', 's3', '2006-03-01');
 *     // Returns an array or throws.
 *     $data = ApiProvider::resolve($provider, 'api', 'elasticfood', '2020-01-01');
 *
 * You can compose multiple providers into a single provider using
 * {@see Aws\Utils::orFn}. This method accepts providers as arguments and
 * returns a new function that will invoke each provider until a non-null value
 * is returned.
 *
 *     $a = ApiProvider::filesystem(sys_get_temp_dir() . '/aws-beta-models');
 *     $b = ApiProvider::manifest();
 *
 *     $c = Aws\Utils::orFn($a, $b);
 *     $data = $c('api', 'betaservice', '2015-08-08'); // $a handles this.
 *     $data = $c('api', 's3', '2006-03-01');          // $b handles this.
 *     $data = $c('api', 'invalid', '2014-12-15');     // Neither handles this.
 */
class ApiProvider
{
    /**
     * Resolves an API provider and ensures a non-null return value.
     *
     * @param callable $provider Provider function to invoke.
     * @param string   $type     Type of data ('api', 'waiter', 'paginator').
     * @param string   $service  Service name.
     * @param string   $version  API version.
     *
     * @return array
     * @throws UnresolvedApiException
     */
    public static function resolve(callable $provider, $type, $service, $version)
    {
        $result = $provider($type, $service, $version);
        if (is_array($result)) {
            return $result;
        }

        throw new UnresolvedApiException($service
            ? "The {$service} service does not have API version: {$version}."
            : "You must specify a valid service name to retrieve its API data."
        );
    }

    /**
     * Retrieves a list of valid versions for the specified service.
     *
     * @param string $service Service name
     *
     * @return array
     */
    public static function getServiceVersions($service)
    {
        $manifest = self::getManifestData();

        if (!isset($manifest[$service])) {
            return [];
        }

        return array_values(array_unique($manifest[$service]));
    }

    /**
     * Default SDK API provider.
     *
     * This provider loads pre-built manifest data from the `data` directory.
     *
     * @return callable
     */
    public static function defaultProvider()
    {
        return self::manifest(self::getManifestData());
    }

    /**
     * Loads API data after resolving the version to the latest, compatible,
     * available version based on the provided manifest data.
     *
     * Manifest data is essentially an associative array of service names to
     * associative arrays of API version aliases.
     *
     * [
     *   ...
     *   'ec2' => [
     *     'latest' => '2014-10-01',
     *     '2014-10-01' => '2014-10-01',
     *     '2014-09-01' => '2014-10-01',
     *     '2014-06-15' => '2014-10-01',
     *     ...
     *   ],
     *   'ecs' => [...],
     *   'elasticache' => [...],
     *   ...
     * ]
     *
     * @param array $manifest The API version manifest data.
     *
     * @return callable
     */
    public static function manifest(array $manifest, $dir = null)
    {
        $dir = self::checkDir($dir);
        return function ($type, $service, $version) use ($manifest, $dir) {
            if (!isset($manifest[$service][$version])) {
                return null;
            }

            $version = $manifest[$service][$version];

            return self::loadApiData($type, $service, $version, $dir);
        };
    }

    /**
     * Loads API data from the specified directory.
     *
     * If "latest" is specified as the version, this provider must glob the
     * directory to find which is the latest available version.
     *
     * @param string $dir Directory containing service models.
     *
     * @return callable
     * @throws \InvalidArgumentException if the provided `$dir` is invalid.
     */
    public static function filesystem($dir = null)
    {
        $dir = self::checkDir($dir);
        return function ($type, $service, $version) use ($dir) {
            static $latestVersions = [];
            if ($version === 'latest') {
                // Determine the latest version for the specified service.
                if (!isset($latestVersions[$service])) {
                    $results = [];
                    $len = strlen($service) + 1;
                    foreach (glob("{$dir}/{$service}-*.api.*") as $f) {
                        $results[] = substr(basename($f), $len, 10);
                    }
                    if (!$results) {
                        return null;
                    }
                    rsort($results);
                    $latestVersions[$service] = $results[0];
                }

                $version = $latestVersions[$service];
            }

            return self::loadApiData($type, $service, $version, $dir);
        };
    }

    private static function loadApiData($type, $service, $version, $dir)
    {
        static $typeMap = [
            'api'       => 'api',
            'paginator' => 'paginators',
            'waiter'    => 'waiters2',
        ];

        if (!isset($typeMap[$type])) {
            throw new \InvalidArgumentException("Unknown type: $type");
        }

        // First check for PHP files, then fall back to JSON.
        $path = "{$dir}/{$service}-{$version}.{$typeMap[$type]}.php";
        if (file_exists($path)) {
            return require $path;
        }

        $path = "{$dir}/{$service}-{$version}.{$typeMap[$type]}.json";
        if (file_exists($path)) {
            return Utils::jsonDecode(file_get_contents($path), true);
        }

        return null;
    }

    private static function checkDir($dir)
    {
        $dir = $dir ? rtrim($dir, '/\\') : __DIR__ . '/../data';
        if (is_dir($dir)) {
            return $dir;
        }

        throw new \InvalidArgumentException("Directory not found: $dir");
    }

    private static function getManifestData()
    {
        static $manifest;
        if (!$manifest) {
            $manifest = require __DIR__ . '/../data/api-version-manifest.php';
        }

        return $manifest;
    }
}
