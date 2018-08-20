<?php
namespace FreePBX\modules\Backup\utests;
use PHPUnit_Framework_TestCase;
use PharData;
use Phar;
/**
 * https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
 * @backupGlobals disabled
 * @coversDefaultClass \FreePBX\modules\Backup\Models\BackupFile
 */

class legacyDetectionTest extends PHPUnit_Framework_TestCase{
    protected static $f;
    protected static $o;
    public static function setUpBeforeClass(){
        self::$f = \FreePBX::create();
        self::$o = self::$f->Backup;
        self::rrmdir('/tmp/unittest/');
        @unlink('/tmp/unittest/current.tar');
        @unlink('/tmp/unittest/current.tar.gz');
        mkdir('/tmp/unittest/',true);
        touch('/tmp/unittest/manifest');
        touch('/tmp/unittest/foo');
        chdir('/tmp/unittest');
        exec('tar -czvf legacy.tgz manifest', $out, $ret);
        $phar = new PharData('/tmp/unittest/current.tar');
        $phar->addEmptyDir('/modulejson');
        $phar->addFile('/tmp/unittest/foo','/modulejson/foo');
        $phar->compress(Phar::GZ);
        unset($phar);
    }
    public function testLegacy(){
        $this->assertTrue(file_exists('/tmp/unittest/legacy.tgz'));
        $this->assertEquals('legacy', self::$o->determineBackupFileType('/tmp/unittest/legacy.tgz'));
        $this->assertFalse(file_exists('/tmp/unittest/legacy.zip'));
    }
    public function testCurrent(){
        $this->assertTrue(file_exists('/tmp/unittest/current.tar.gz'));
        $this->assertEquals('current', self::$o->determineBackupFileType('/tmp/unittest/current.tar.gz'));
        $this->assertFalse(file_exists('/tmp/unittest/current.zip'));
    }
    /** 
     * @attribution http://php.net/manual/en/function.rmdir.php#117354
     */
    static function rrmdir($dir){
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }
}