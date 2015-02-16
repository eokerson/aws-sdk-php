<?php
namespace Aws\Test\Api;

use Aws\Api\ApiProvider;
use Aws\Exception\UnresolvedApiException;

/**
 * @covers Aws\Api\ApiProvider
 */
class ApiProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testCanResolveProvider()
    {
        $p = function ($a, $b, $c) {return [];};
        $this->assertEquals([], ApiProvider::resolve($p, 't', 's', 'v'));

        $p = function ($a, $b, $c) {return null;};
        $this->setExpectedException(UnresolvedApiException::class);
        ApiProvider::resolve($p, 't', 's', 'v');
    }

    public function testCanGetServiceVersions()
    {
        $this->assertEquals(['2012-08-10'], ApiProvider::getServiceVersions('dynamodb'));
        $this->assertEquals([], ApiProvider::getServiceVersions('foo'));
    }

    public function testCanGetDefaultProvider()
    {
        $p = ApiProvider::defaultProvider();
        $r = new \ReflectionFunction($p);
        $this->assertArrayHasKey('manifest', $r->getStaticVariables());
    }

    public function testManifestProviderReturnsNullForMissingService()
    {
        $p = ApiProvider::manifest([]);
        $this->assertNull($p('api', 'foo', '2015-02-02'));
    }

    public function testManifestProviderCanLoadData()
    {
        $dir = __DIR__ . '/api_provider_fixtures';
        $p = ApiProvider::manifest(include $dir . '/api-version-manifest.php', $dir);
        $data = $p('api', 'dynamodb', 'latest');
        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('foo', $data);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFilesystemProviderEnsuresDirectoryIsValid()
    {
        ApiProvider::filesystem('/path/to/invalid/dir');
    }

    public function testEnsuresValidJson()
    {
        $path = sys_get_temp_dir() . '/invalid-2010-12-05.api.json';
        file_put_contents($path, 'foo, bar');
        $p = ApiProvider::filesystem(sys_get_temp_dir());
        try {
            $p('api', 'invalid', '2010-12-05');
            $this->fail('Did not throw');
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
        } finally {
            unlink($path);
        }
    }

    public function testNullOnMissingFile()
    {
        $p = ApiProvider::filesystem(sys_get_temp_dir());
        $this->assertNull($p('api', 'nofile', '2010-02-04'));
    }

    public function testReturnsLatestServiceData()
    {
        $p = ApiProvider::filesystem(__DIR__ . '/api_provider_fixtures');
        $this->assertEquals(['foo' => 'bar'], $p('api', 'dynamodb', 'latest'));
    }

    public function testReturnsNullWhenNoLatestVersionIsAvailable()
    {
        $p = ApiProvider::filesystem(__DIR__ . '/api_provider_fixtures');
        $this->assertnull($p('api', 'dodo', 'latest'));
    }

    public function testReturnsPaginatorConfigsForLatestCompatibleVersion()
    {
        $dir = __DIR__ . '/api_provider_fixtures';
        $p = ApiProvider::manifest(include $dir . '/api-version-manifest.php', $dir);
        $result = $p('paginator', 'dynamodb', 'latest');
        $this->assertEquals(['abc' => '123'], $result);
        $result = $p('paginator', 'dynamodb', '2011-12-05');
        $this->assertEquals(['abc' => '123'], $result);
    }

    public function testReturnsWaiterConfigsForLatestCompatibleVersion()
    {
        $dir = __DIR__ . '/api_provider_fixtures';
        $p = ApiProvider::manifest(include $dir . '/api-version-manifest.php', $dir);
        $result = $p('waiter', 'dynamodb', 'latest');
        $this->assertEquals(['abc' => '456'], $result);
        $result = $p('waiter', 'dynamodb', '2011-12-05');
        $this->assertEquals(['abc' => '456'], $result);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unknown type: foo
     */
    public function testThrowsOnBadType()
    {
        $p = ApiProvider::defaultProvider();
        $p('foo', 's3', 'latest');
    }
}
