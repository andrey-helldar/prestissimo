<?php
namespace Hirak\Prestissimo;

use Composer\IO;

class HttpGetRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $io = new IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );

        self::assertSame('https', $req->scheme);
        self::assertSame('packagist.org', $req->origin);
        self::assertSame('packagist.org', $req->host);
        self::assertSame('/packages.json', $req->path);
        self::assertSame(array(), $req->query);

        $req = new HttpGetRequest(
            'example.com',
            'http://user:pass@example.com:8080/something/path?a=b&c=d',
            $io
        );
        self::assertSame(8080, $req->port);
        self::assertSame('user', $req->username);
        self::assertSame('pass', $req->password);

        self::assertEquals(array('a'=>'b', 'c'=>'d'), $req->query);
        self::assertEquals(
            array('username'=>'user', 'password'=>'pass'),
            $io->getAuthentication('example.com')
        );
    }

    public function testHttpProxy()
    {
        $_SERVER['http_proxy'] = 'example.com';
        $io = new IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'http://packagist.org/packages.json',
            $io
        );

        self::assertArrayHasKey(CURLOPT_PROXY, $req->curlOpts);

        unset($_SERVER['http_proxy']);
        $_SERVER['HTTP_PROXY'] = 'example.com';
        $req = new HttpGetRequest(
            'packagist.org',
            'http://packagist.org/packages.json',
            $io
        );
        self::assertArrayHasKey(CURLOPT_PROXY, $req->curlOpts);

        $_SERVER['no_proxy'] = 'packagist.org';
        $req = new HttpGetRequest(
            'packagist.org',
            'http://packagist.org/packages.json',
            $io
        );
        self::assertArrayNotHasKey(CURLOPT_PROXY, $req->curlOpts);

        $_SERVER['no_proxy'] = 'example.com';
        $req = new HttpGetRequest(
            'packagist.org',
            'http://packagist.org/packages.json',
            $io
        );
        self::assertArrayHasKey(CURLOPT_PROXY, $req->curlOpts);
    }

    public function testHttpsProxy()
    {
        $_SERVER['https_proxy'] = 'example.com';
        $io = new IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );
        self::assertArrayHasKey(CURLOPT_PROXY, $req->curlOpts);

        unset($_SERVER['https_proxy']);
        $_SERVER['HTTPS_PROXY'] = 'example.com';

        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );
        self::assertArrayHasKey(CURLOPT_PROXY, $req->curlOpts);
    }

    public function testRestoreAuth()
    {
        $io = new IO\NullIO;
        $io->setAuthentication('example.com', 'user', 'pass');
        $req = new HttpGetRequest(
            'example.com',
            'http://example.com/foo.txt',
            $io
        );

        self::assertSame('user', $req->username);
        self::assertSame('pass', $req->password);
    }

    public function testGetURL()
    {
        $io = new \Composer\IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );

        self::assertSame('https://packagist.org/packages.json', $req->getURL());

        $req->host = 'packagist.jp';
        self::assertSame('https://packagist.jp/packages.json', $req->getURL());

        $req->scheme = 'http';
        $req->port = 8080;
        self::assertSame('http://packagist.jp:8080/packages.json', $req->getURL());

        $req->query += array(
            'a' => 'b',
            'c' => 'd'
        );
        $req->scheme = '';
        self::assertSame('packagist.jp:8080/packages.json?a=b&c=d', $req->getURL());
    }

    public function testGetCurlOpts()
    {
        $io = new \Composer\IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );

        $req->curlOpts[CURLOPT_TIMEOUT] = 10;

        $expects = array(
            CURLOPT_URL => 'https://packagist.org/packages.json',
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_HTTPHEADER => array(),
            CURLOPT_VERBOSE => false,
        );
        $curlOpts = $req->getCurlOpts();
        unset($curlOpts[CURLOPT_USERAGENT]);
        self::assertEquals($expects, $curlOpts);

        $req->username = 'ninja';
        $req->password = 'aieee';
        $expects[CURLOPT_HTTPHEADER][] = 'Authorization: Basic ' . base64_encode('ninja:aieee');
        $curlOpts = $req->getCurlOpts();
        unset($curlOpts[CURLOPT_USERAGENT]);
        self::assertEquals($expects, $curlOpts);
    }
}
