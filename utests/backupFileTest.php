<?php
namespace FreePBX\modules\Backup\utests;
use PHPUnit_Framework_TestCase;
use FreePBX\modules\Backup\Models\BackupSplFileInfo;
use splitbrain\PHPArchive\Tar;
include __DIR__.'/../vendor/autoload.php';
include __DIR__.'/../Models/BackupSplFileInfo.php';
/**
 * https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
 * @backupGlobals disabled
 * @coversDefaultClass \FreePBX\modules\Backup\Models\BackupSplFileInfo
 */

class fooAddTest extends PHPUnit_Framework_TestCase{
    public static function setUpBeforeClass(){
        //base64 encoded json
        $metadata = 'ew0KICAgICJtb2R1bGVzIjogWw0KICAgICAgICB7DQogICAgICAgICAgICAibW9kdWxlIjogImFubm91bmNlbWVudCIsDQogICAgICAgICAgICAidmVyc2lvbiI6ICIxNS4wLjEiDQogICAgICAgIH0sDQogICAgICAgIHsNCiAgICAgICAgICAgICJtb2R1bGUiOiAiYmxhY2tsaXN0IiwNCiAgICAgICAgICAgICJ2ZXJzaW9uIjogIjE1LjAuMSINCiAgICAgICAgfSwNCiAgICAgICAgew0KICAgICAgICAgICAgIm1vZHVsZSI6ICJjYWxsZXJpZCIsDQogICAgICAgICAgICAidmVyc2lvbiI6ICIxNS4wLjEiDQogICAgICAgIH0sDQogICAgICAgIHsNCiAgICAgICAgICAgICJtb2R1bGUiOiAiY2FsbGZvcndhcmQiLA0KICAgICAgICAgICAgInZlcnNpb24iOiAiMTUuMC4xIg0KICAgICAgICB9LA0KICAgICAgICB7DQogICAgICAgICAgICAibW9kdWxlIjogImNhbGx3YWl0aW5nIiwNCiAgICAgICAgICAgICJ2ZXJzaW9uIjogIjE1LjAuMSINCiAgICAgICAgfSwNCiAgICAgICAgew0KICAgICAgICAgICAgIm1vZHVsZSI6ICJmaWxlc3RvcmUiLA0KICAgICAgICAgICAgInZlcnNpb24iOiAiMTUuMC4xYWxwaGExIg0KICAgICAgICB9LA0KICAgICAgICB7DQogICAgICAgICAgICAibW9kdWxlIjogInBtMiIsDQogICAgICAgICAgICAidmVyc2lvbiI6ICIxNS4wLjEiDQogICAgICAgIH0sDQogICAgICAgIHsNCiAgICAgICAgICAgICJtb2R1bGUiOiAicmVjb3JkaW5ncyIsDQogICAgICAgICAgICAidmVyc2lvbiI6ICIxNS4wLjEiDQogICAgICAgIH0NCiAgICBdLA0KICAgICJza2lwcGVkIjogW10sDQogICAgImRhdGUiOiAxNTI1MjE1MTU2LA0KICAgICJiYWNrdXBJbmZvIjogew0KICAgICAgICAiYmFja3VwX25hbWUiOiAiVGVzdCBCYWNrdXAiLA0KICAgICAgICAiYmFja3VwX2Rlc2NyaXB0aW9uIjogIllPTE8iLA0KICAgICAgICAiYmFja3VwX2l0ZW1zIjogIlt7XCJtb2R1bGVuYW1lXCI6XCJBbm5vdW5jZW1lbnRcIixcInNlbGVjdGVkXCI6dHJ1ZX0se1wibW9kdWxlbmFtZVwiOlwiQmxhY2tsaXN0XCIsXCJzZWxlY3RlZFwiOnRydWV9LHtcIm1vZHVsZW5hbWVcIjpcIkNhbGxlcmlkXCIsXCJzZWxlY3RlZFwiOnRydWV9LHtcIm1vZHVsZW5hbWVcIjpcIkNhbGxmb3J3YXJkXCIsXCJzZWxlY3RlZFwiOnRydWV9LHtcIm1vZHVsZW5hbWVcIjpcIkNhbGx3YWl0aW5nXCIsXCJzZWxlY3RlZFwiOnRydWV9LHtcIm1vZHVsZW5hbWVcIjpcIkRibWFuYWdlclwiLFwic2VsZWN0ZWRcIjp0cnVlfSx7XCJtb2R1bGVuYW1lXCI6XCJGaWxlc3RvcmVcIixcInNlbGVjdGVkXCI6dHJ1ZX0se1wibW9kdWxlbmFtZVwiOlwiUG0yXCIsXCJzZWxlY3RlZFwiOnRydWV9LHtcIm1vZHVsZW5hbWVcIjpcIlJlY29yZGluZ3NcIixcInNlbGVjdGVkXCI6dHJ1ZX1dIiwNCiAgICAgICAgImJhY2t1cF9zdG9yYWdlIjogWw0KICAgICAgICAgICAgIkRyb3Bib3hfNzQ3MDk4NDAtMDVmMS00M2RlLWJlZWEtMjQ5OTQzN2UxYzZjIg0KICAgICAgICBdLA0KICAgICAgICAiYmFja3VwX3NjaGVkdWxlIjogIjU4IDIzICogKiAzIiwNCiAgICAgICAgInNjaGVkdWxlX2VuYWJsZWQiOiAieWVzIiwNCiAgICAgICAgIm1haW50YWdlIjogIjMwIiwNCiAgICAgICAgIm1haW50cnVucyI6ICIzIiwNCiAgICAgICAgImJhY2t1cF9lbWFpbCI6ICJqZmluc3Ryb21AZ21haWwuY29tIiwNCiAgICAgICAgImJhY2t1cF9lbWFpbHR5cGUiOiAiYm90aCIsDQogICAgICAgICJpbW1vcnRhbCI6ICIiLA0KICAgICAgICAid2FybXNwYXJlX3R5cGUiOiAicHJpbWFyeSIsDQogICAgICAgICJ3YXJtc3BhcmVfdXNlciI6ICJ3YXJtc3BhcmVfdXNlciIsDQogICAgICAgICJ3YXJtc3BhcmVfcmVtb3RlIjogIiIsDQogICAgICAgICJ3YXJtc3BhcmVlbmFibGVzIjogIm9uIiwNCiAgICAgICAgInB1YmxpY2tleSI6ICIiDQogICAgfSwNCiAgICAicHJvY2Vzc29yZGVyIjogew0KICAgICAgICAicmVjb3JkaW5ncyI6ICIxNS4wLjEiLA0KICAgICAgICAicG0yIjogIjE1LjAuMSIsDQogICAgICAgICJmaWxlc3RvcmUiOiAiMTUuMC4xYWxwaGExIiwNCiAgICAgICAgImRibWFuYWdlciI6ICIxNS4wLjEiLA0KICAgICAgICAiY2FsbHdhaXRpbmciOiAiMTUuMC4xIiwNCiAgICAgICAgImNhbGxmb3J3YXJkIjogIjE1LjAuMSIsDQogICAgICAgICJjYWxsZXJpZCI6ICIxNS4wLjEiLA0KICAgICAgICAiYmxhY2tsaXN0IjogIjE1LjAuMSIsDQogICAgICAgICJhbm5vdW5jZW1lbnQiOiAiMTUuMC4xIg0KICAgIH0NCn0 =';
        @unlink('/tmp/20180501-155236-1525215156-15.0.1alpha2-2097601499.tar.gz');
        @unlink('/tmp/20180501-155236-1525215156-15.tar.gz');
        $tar = new Tar();
	$tar->create('/tmp/20180501-155236-1525215156-15.0.1alpha2-2097601499.tar.gz');
	$tar->addData('metadata.json', base64_decode($metadata));
	$tar->close();
	unset($tar);

        touch('/tmp/20180501-155236-1525215156-15.0.1alpha2-2097601499.tar.gz.sha256sum');
        touch('/tmp/somethingelse.tar.gz');
    }
    /**
     * @covers ::backupData
     */
    public function testBackupData(){
        $file = new BackupSplFileInfo('/tmp/20180501-155236-1525215156-15.0.1alpha2-2097601499.tar.gz');
        $parsed = $file->backupData();
        $this->assertEquals('20180501', $parsed['datestring']);
        $this->assertEquals('155236', $parsed['timestring']);
        $this->assertEquals('1525215156', $parsed['timestamp']);
        $this->assertEquals('15.0.1alpha2', $parsed['framework']);
        $this->assertFalse($parsed['isCheckSum']);
    }
    public function testBackupChecksum(){
        $file = new BackupSplFileInfo('/tmp/20180501-155236-1525215156-15.0.1alpha2-2097601499.tar.gz');
        $parsed = $file->backupData();
        $this->assertFalse($parsed['isCheckSum']);
        $file = new BackupSplFileInfo('/tmp/20180501-155236-1525215156-15.0.1alpha2-2097601499.tar.gz.sha256sum');
        $parsed = $file->backupData();
        $this->assertTrue($parsed['isCheckSum']);
    }
    public function testBackupDataBadFile(){
        $file = new BackupSplFileInfo('/tmp/somethingelse.tar.gz');
        $parsed = $file->backupData();
        $this->assertFalse($parsed);
    }
    public function testMetadata(){
        $file = new BackupSplFileInfo('/tmp/20180501-155236-1525215156-15.0.1alpha2-2097601499.tar.gz');
        $data = $file->getMetadata();
        $this->assertEquals($data['backupInfo']['backup_description'],'YOLO');
    }
}
