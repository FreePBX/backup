<?php
namespace FreePBX\modules\Backup\utests;
use PHPUnit_Framework_TestCase;
use splitbrain\PHPArchive\Tar;
/**
 * https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
 * @backupGlobals disabled
 * @coversDefaultClass \FreePBX\modules\Backup\Models\BackupSplFileInfo
 */

class legacyDetectionTest extends PHPUnit_Framework_TestCase{
    protected static $f;
    protected static $o;
    public static function setUpBeforeClass(){
        self::$f = \FreePBX::create();
        self::$o = self::$f->Backup;
        self::rrmdir('/tmp/unittest/');
        @unlink('/tmp/unittest/current.tar.gz');
        mkdir('/tmp/unittest/',true);
        mkdir('/tmp/unittest/modulejson',true);
        touch('/tmp/unittest/modulejson/foo');
        touch('/tmp/unittest/manifest');
        chdir('/tmp/unittest');
        exec('tar -czvf legacy.tgz manifest', $out, $ret);
	$tar = new Tar();
	$tar->create('/tmp/unittest/current.tar.gz');
        $tar->addFile('/tmp/unittest/modulejson','/modulejson');
        $tar->addFile('/tmp/unittest/modulejson/foo','/modulejson/foo');
	$tar->close();

        unset($tar);
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
