<?php
namespace Aws\Api;

use Aws\Exception\UnresolvedApiException;
use GuzzleHttp\Utils;

/**
 * Provides service descriptions data from a directory structure.
 */
class FilesystemApiProvider
{
    /** @var string */
    private $path;

    /** @var array */
    private $manifest;

    /**
     * @param string $path Path to the service description files on disk.
     *
     * @throws \InvalidArgumentException if the path is not found.
     */
    public function __construct($path)
    {
        $this->path = rtrim($path, '/\\');

        if (!is_dir($path)) {
            throw new \InvalidArgumentException("Path not found: $path");
        }

        $this->manifest = include "{$this->path}/api-version-manifest.php";
    }

    /**
     * Gets description data for the given type, service, and version as a
     * JSON decoded associative array structure.
     *
     * @param string $type    Type of document to retrieve. For example: api,
     *                        waiter, paginator, etc.
     * @param string $service Service to retrieve.
     * @param string $version Version of the document to retrieve.
     *
     * @return array
     * @throws \InvalidArgumentException if the is invalid.
     * @throws UnresolvedApiException if the service/version is not available.
     */
    public function __invoke($type, $service, $version)
    {
        if (!isset($this->manifest[$service][$version])) {
            throw new UnresolvedApiException(
                "The {$service} service does "
                . "not have a {$version} version available."
            );
        }

        $version = $this->manifest[$service][$version];

        switch ($type) {
            case 'api':
                return $this->load($service, $version, 'api');
            case 'paginator':
                return $this->load($service, $version, 'paginators');
            case 'waiter':
                return $this->load($service, $version, 'waiters2');
            default:
                throw new \InvalidArgumentException("Unknown type: $type");
        }
    }

    /**
     * Retrieves a list of valid versions for this service.
     *
     * @param string $service Service name
     *
     * @return array
     */
    public function getServiceVersions($service)
    {
        if (!isset($this->manifest[$service])) {
            return [];
        }

        return array_values(array_unique($this->manifest[$service]));
    }

    /**
     * @return array
     */
    private function load($service, $version, $type)
    {
        // First check for PHP files, then fall back to JSON.
        $path = "{$this->path}/{$service}-{$version}.{$type}.php";
        if (file_exists($path)) {
            return require $path;
        }

        $path = "{$this->path}/{$service}-{$version}.{$type}.json";
        if (file_exists($path)) {
            return Utils::jsonDecode(file_get_contents($path), true);
        }

        throw new UnresolvedApiException("Cannot find file: $path");
    }
}
