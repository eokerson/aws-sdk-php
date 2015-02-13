<?php
namespace Aws\Test\Api;

use Aws\Api\FilesystemApiProvider;
use Aws\Exception\UnresolvedApiException;

/**
 * @covers Aws\Api\FilesystemApiProvider
 */
class FilesystemApiProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresDirectoryIsValid()
    {
        new FilesystemApiProvider('/path/to/invalid/dir');
    }

    public function testPathAndSuffixSetCorrectly()
    {
        $dir = __DIR__ . '/api_provider_fixtures';
        $p1 = new FilesystemApiProvider($dir . '/');
        $this->assertEquals($dir, $this->readAttribute($p1, 'path'));
    }

    public function testEnsuresValidJson()
    {
        $path = sys_get_temp_dir() . '/invalid-2010-12-05.api.json';
        $manifest = sys_get_temp_dir() . '/api-version-manifest.php';
        file_put_contents($path, 'foo, bar');
        file_put_contents($manifest, '<?php return [];');
        $p = new FilesystemApiProvider(sys_get_temp_dir());
        try {
            $p('api', 'invalid', '2010-12-05');
            $this->fail('Did not throw');
        } catch (\Exception $e) {
            $this->assertInstanceOf(UnresolvedApiException::class, $e);
        } finally {
            unlink($path);
            unlink($manifest);
        }
    }

    public function testCanLoadPhpFiles()
    {
        $p = new FilesystemApiProvider(__DIR__ . '/api_provider_fixtures');
        $this->assertEquals([], $p('api', 'dynamodb', '2010-02-04'));
    }

    public function testReturnsLatestServiceData()
    {
        $p = new FilesystemApiProvider(__DIR__ . '/api_provider_fixtures');
        $this->assertEquals(['foo' => 'bar'], $p('api', 'dynamodb', 'latest'));
    }

    /**
     * @expectedExceptionMessage The dodo service does not have a latest version available.
     */
    public function testThrowsWhenNoLatestVersionIsAvailable()
    {
        $this->setExpectedException(UnresolvedApiException::class);
        $p = new FilesystemApiProvider(__DIR__ . '/api_provider_fixtures');
        $p('api', 'dodo', 'latest');
    }

    public function testReturnsPaginatorConfigsForLatestCompatibleVersion()
    {
        $p = new FilesystemApiProvider(__DIR__ . '/api_provider_fixtures');
        $result = $p('paginator', 'dynamodb', 'latest');
        $this->assertEquals(['abc' => '123'], $result);
        $result = $p('paginator', 'dynamodb', '2011-12-05');
        $this->assertEquals(['abc' => '123'], $result);
    }

    public function testReturnsWaiterConfigsForLatestCompatibleVersion()
    {
        $p = new FilesystemApiProvider(__DIR__ . '/api_provider_fixtures');
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
        $p = new FilesystemApiProvider(__DIR__ . '/api_provider_fixtures');
        $p('foo', 's3', 'latest');
    }

    public function testCanGetServiceVersions()
    {
        $p = new FilesystemApiProvider(__DIR__ . '/api_provider_fixtures');
        $this->assertEquals(['2012-08-10', '2010-02-04'], $p->getServiceVersions('dynamodb'));
        $this->assertEquals([], $p->getServiceVersions('foo'));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot find file
     */
    public function testThrowsWhenLoadingMissingFile()
    {
        $p = new FilesystemApiProvider(__DIR__ . '/api_provider_fixtures');
        $p('api', 'nofile', 'latest');
    }
}
