<?php 
namespace FreepPBX\backup\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\backup;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

class BackupGqlApiTest extends ApiBaseTestCase {
   protected static $backup;
        
   /**
   * setUpBeforeClass
   *
   * @return void
   */
  public static function setUpBeforeClass() {
    parent::setUpBeforeClass();
    self::$backup = self::$freepbx->backup;
  }
        
   /**
   * tearDownAfterClass
   *
   * @return void
   */
  public static function tearDownAfterClass() {
    parent::tearDownAfterClass();
  }
  
  /**
   * test_addBackup_required_paramater_not_given_should_return_false
   *
   * @return void
   */
  public function test_addBackup_required_paramater_not_given_should_return_false(){
    $response = $this->request("mutation{
      addBackup(input : {
        name: \"testbackup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"adv_recovery\",\"amd\"]
        notificationEmail: \"test@test.com\"    
      }){
        status message
      }
   }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"Field addBackupInput.storageLocation of required type [String]! was not provided.","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_addBackup_all_good_should_return_true
   *
   * @return void
   */
  public function test_addBackup_all_good_should_return_true(){

     $mockHelper = $this->getMockBuilder(\Freepbx\modules\Backup::class)
       ->disableOriginalConstructor()
       ->setMethods(array('updateBackupSetting','performBackup'))
       ->getMock();

    $mockHelper->method('performBackup')
      ->willReturn('12345');
  
    $mockHelper->method('updateBackupSetting')
      ->willReturn(true);

    self::$freepbx->backup = $mockHelper;  

    $response = $this->request("mutation{
      addBackup(input : {
        name: \"testbackup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"all\"]
        storageLocation: \"{'dropbox_wqeqwe'}\"    
      }){
        status message id
      }
    }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"data":{"addBackup":{"status":true,"message":"Backup has been performed\/schedules","id":"12345"}}}',$json);
      
   $this->assertEquals(200, $response->getStatusCode());
  }
 
  /**
   * test_fetchBackupFiles_all_good_should_return_true
   *
   * @return void
   */
  public function test_fetchBackupFiles_all_good_should_return_true(){

    $mockHelper = $this->getMockBuilder(\Freepbx\modules\Backup::class)
       ->disableOriginalConstructor()
       ->setMethods(array('getAllRemote'))
       ->getMock();

    $mockHelper->method('getAllRemote')
      ->willReturn(array(array("id" => "SSH_8e734b7c-180a-f445-fr4r-2345_12324324345436", "type" => "SSH", "file" => "20214324-212343-15234-15.0.16.51-1234234.tar.gz", "framework" => "15.0.16.51", "timestamp" => "1588606202", "name" => "20214324-212343-15234-15.0.16.51-1234234.tar.gz", "instancename" => "warmspare")));

    self::$freepbx->backup = $mockHelper;  

    $response = $this->request(
    "query{
				fetchAllBackups{
				status message fileDetails{
					id
					type
					file
					framework
					timestamp
					name
					instancename
					}
				}
      }");

   $json = (string)$response->getBody();
   $this->assertEquals('{"data":{"fetchAllBackups":{"status":true,"message":"List of backup files","fileDetails":[{"id":"SSH_8e734b7c-180a-f445-fr4r-2345_12324324345436","type":"SSH","file":"20214324-212343-15234-15.0.16.51-1234234.tar.gz","framework":"15.0.16.51","timestamp":"1588606202","name":"20214324-212343-15234-15.0.16.51-1234234.tar.gz","instancename":"warmspare"}]}}}',$json);
      
   $this->assertEquals(200, $response->getStatusCode());
  }

  public function test_fetchBackupFiles_when_wrong_parameter_sent_should_return_error_and_false()
  {

    $response = $this->request("query{
				fetchAllBackups{
				status message fileDetails{
					id
					type
					file
					framework
					timestamp
					name
					lorem
					}
				}
				}");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Cannot query field \"lorem\" on type \"backup\".","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }

  public function test_fetchBackupFiles_when_backups_not_return_should_return_false()
  {

    $mockHelper = $this->getMockBuilder(\Freepbx\modules\Backup::class)
      ->disableOriginalConstructor()
      ->setMethods(array('getAllRemote'))
      ->getMock();

    $mockHelper->method('getAllRemote')
    ->willReturn(array());

    self::$freepbx->backup = $mockHelper;

    $response = $this->request("query{
				fetchAllBackups{
				status message fileDetails{
					id
					type
					file
					framework
					timestamp
					name
					instancename
					}
				}
				}");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Sorry unable to find the backup files","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_restoreBackup_when_not_sent_filename_should_return_false
   *
   * @return void
   */
  public function test_restoreBackup_when_not_sent_filename_should_return_false(){
    $response = $this->request("mutation{
      restoreBackup(input : {
        
       }){
        status message
       }
    }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"Field restoreBackupInput.name of required type String! was not provided.","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_restoreBackup_when_sent_filename_and_return_false_should_return_false
   *
   * @return void
   */
  public function test_restoreBackup_when_sent_filename_and_return_false_should_return_false(){
    
     $mockHelper = $this->getMockBuilder(Freepbx\framework\amp_conf\htdocs\admin\libraries\BMO\Hooks::class)
       ->disableOriginalConstructor()
       ->setMethods(array('runModuleSystemHook'))
       ->getMock();

      $mockHelper->method('runModuleSystemHook')
      ->willReturn(false);

    self::$freepbx->sysadmin()->setRunHook($mockHelper);  

    $response = $this->request("mutation{
      restoreBackup(input : {
          name: \"testbackup\"
       }){
        status message
       }
    }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"Sorry failed to perform restore","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_restoreBackup_when_sent_filename_and_return_true_should_return_true
   *
   * @return void
   */
  public function test_restoreBackup_when_sent_filename_and_return_true_should_return_true(){
    
    $mockHelper = $this->getMockBuilder(Freepbx\framework\amp_conf\htdocs\admin\libraries\BMO\Hooks::class)
      ->disableOriginalConstructor()
      ->setMethods(array('runModuleSystemHook'))
      ->getMock();

    $mockHelper->method('runModuleSystemHook')
      ->willReturn(true);

    self::$freepbx->sysadmin()->setRunHook($mockHelper);  

    $response = $this->request("mutation{
      restoreBackup(input : {
          name: \"testbackup\"
       }){
        status message
       }
    }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"data":{"restoreBackup":{"status":true,"message":"Restore process has been initiated. Kindly check the fetchApiStatus api with the transaction id."}}}',$json);
      
   $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * test_addBackup_when_enableBackupSchedule_is_true_but_scheduleBackup_not_given_should_return_false
   *
   * @return void
   */
  public function test_addBackup_when_enableBackupSchedule_is_true_but_scheduleBackup_not_given_should_return_false(){
    $response = $this->request("mutation{
      addBackup(input : {
        name: \"testbackup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"adv_recovery\",\"amd\"]
        notificationEmail: \"test@test.com\"   
        storageLocation: [\"Email_234324-234-43241-234-2342345\"]
        enableBackupSchedule : true
      }){
        status message
      }
   }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"You have enabled enableBackupSchedule so please add scheduleBackup","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
  }
  
  public function test_addBackup_when_invalid_module_name_given_should_return_false(){
    $response = $this->request("mutation{
      addBackup(input : {
        name: \"testbackup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"advrecovery\"]
        notificationEmail: \"test@test.com\"   
        storageLocation: [\"Email_234324-234-43241-234-2342345\"]
      }){
        status message
      }
   }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"Sorry Module name advrecovery is invalid","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
  }
  public function test_fetchAllBackupConfigurations_all_good_should_return_true()
  {

    $mockHelper = $this->getMockBuilder(\Freepbx\modules\Backup::class)
    ->disableOriginalConstructor()
    ->setMethods(array('listBackups'))
    ->getMock();

    $mockHelper->method('listBackups')
    ->willReturn(array(array("id" => "12324324345436", "name" => "Testing Backup", "description" => "Testing Backup Description")));

    self::$freepbx->backup = $mockHelper;

    $response = $this->request(
      "query{
				fetchAllBackupConfigurations{
          status message fileDetails{
            id
            name
            description
          }
        }
      }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"data":{"fetchAllBackupConfigurations":{"status":true,"message":"List of backup configurations","fileDetails":[{"id":"12324324345436","name":"Testing Backup","description":"Testing Backup Description"}]}}}', $json);

    $this->assertEquals(200, $response->getStatusCode());
  }

  public function test_fetchAllBackupConfigurations_when_wrong_parameter_sent_should_return_error_and_false()
  {

    $response = $this->request("query{
      fetchAllBackupConfigurations{
        status message fileDetails{
            id
            name
            description
            lorem 
          }
        }
      }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Cannot query field \"lorem\" on type \"backup\".","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }

  public function test_fetchAllBackupConfigurations_when_backups_not_return_should_return_false()
  {

    $mockHelper = $this->getMockBuilder(\Freepbx\modules\Backup::class)
      ->disableOriginalConstructor()
      ->setMethods(array('listBackups'))
      ->getMock();

    $mockHelper->method('listBackups')
    ->willReturn(array());

    self::$freepbx->backup = $mockHelper;

    $response = $this->request("query{
      fetchAllBackupConfigurations{
				status message fileDetails{
					id
					name
					description
					}
				}
      }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Sorry unable to find the backup configurations","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * test_updateBackup_all_good_should_return_true
   *
   * @return void
   */
  public function test_updateBackup_all_good_should_return_true()
  {
    $mockHelper = $this->getMockBuilder(\Freepbx\modules\Backup::class)
      ->disableOriginalConstructor()
      ->setMethods(array('updateBackupSetting', 'performBackup', 'getBackup'))
      ->getMock();

    $mockHelper->method('performBackup')
    ->willReturn('12345');

    $mockHelper->method('updateBackupSetting')
    ->willReturn(true);

    $mockHelper->method('getBackup')
    ->willReturn(true);

    self::$freepbx->backup = $mockHelper;

    $response = $this->request("mutation{
      updateBackup(input : {
        id:\"12345\",
        name: \"testbackupupdated\"
        description: \"testing backup to add a backup updated\"
        backupModules: [\"amd\"]
        notificationEmail: \"test@test.com\"    
      }){
        status message
      }
   }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"data":{"updateBackup":{"status":true,"message":"Backup has been updated"}}}', $json);

    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * test_updateBackup_required_paramater_not_given_should_return_false
   *
   * @return void
   */
  public function test_updateBackup_required_paramater_not_given_should_return_false()
  {
    $response = $this->request("mutation{
      updateBackup(input : {
        name: \"testbackup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"amd\"]
        notificationEmail: \"test@test.com\"    
      }){
        status message
      }
   }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Field updateBackupInput.id of required type String! was not provided.","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * test_updateBackup_when_invalid_id_is_sent_should_return_false
   *
   * @return void
   */
  public function test_updateBackup_when_invalid_id_is_sent_should_return_false()
  {
    $response = $this->request("mutation{
      updateBackup(input : {
        id:\"1111\"
        name: \"testbackup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"amd\"]
        notificationEmail: \"test@test.com\"    
      }){
        status message
      }
   }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Backup does not found","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * test_updateBackup_when_invalid_backup_modules_are_sent_should_return_false
   *
   * @return void
   */
  public function test_updateBackup_when_invalid_backup_modules_are_sent_should_return_false()
  {
    $mockHelper = $this->getMockBuilder(\Freepbx\modules\Backup::class)
      ->disableOriginalConstructor()
      ->setMethods(array('updateBackupSetting', 'performBackup', 'getBackup'))
      ->getMock();

    $mockHelper->method('performBackup')
    ->willReturn('12345');

    $mockHelper->method('updateBackupSetting')
    ->willReturn(true);

    $mockHelper->method('getBackup')
    ->willReturn(true);

    self::$freepbx->backup = $mockHelper;

    $response = $this->request("mutation{
      updateBackup(input : {
        id:\"12345\"
        name: \"testbackup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"lorem\",\"amd\"]
        notificationEmail: \"test@test.com\"    
      }){
        status message
      }
   }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Sorry module name lorem is invalid","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * test_deleteBackup_all_good_should_return_true
   *
   * @return void
   */
  public function test_deleteBackup_all_good_should_return_true()
  {
    $mockHelper = $this->getMockBuilder(\Freepbx\modules\Backup::class)
      ->disableOriginalConstructor()
      ->setMethods(array('updateBackupSetting', 'performBackup', 'getBackup', 'deleteBackup'))
      ->getMock();

    $mockHelper->method('performBackup')
    ->willReturn('12345');

    $mockHelper->method('updateBackupSetting')
    ->willReturn(true);

    $mockHelper->method('getBackup')
    ->willReturn(true);

    $mockHelper->method('deleteBackup')
    ->willReturn(true);

    self::$freepbx->backup = $mockHelper;

    $response = $this->request("query{
      deleteBackup(backupId: \"12345\") {
        status
        message
      }
   }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"data":{"deleteBackup":{"status":true,"message":"Backup deleted successfully"}}}', $json);

    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * test_deleteBackup_invalid_backup_id_should_return_false
   *
   * @return void
   */
  public function test_deleteBackup_invalid_backup_id_should_return_false()
  {
    $response = $this->request("query{
      deleteBackup(backupId: \"12345\") {
        status
        message
      }
   }");

    $json = (string)$response->getBody();

    $this->assertEquals('{"errors":[{"message":"Backup does not exists","status":false}]}', $json);

    $this->assertEquals(400, $response->getStatusCode());
  }

  public function test_addBackup_name_contains_space_should_return_false(){
    $response = $this->request("mutation{
      addBackup(input : {
        name: \"test backup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"adv_recovery\",\"amd\"]
        notificationEmail: \"test@test.com\"   
        storageLocation: \"{'FTP_12124'}\"    
      }){
        status message
      }
   }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"Name contains whitespaces\/special characters use - instead","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
  }

  public function test_updateBackup_name_contains_space_should_return_false(){
    $response = $this->request("mutation{
       updateBackup(input : {
        id:\"12345\"
        name: \"test backup\"
        description: \"testing backup to add a backup\"
        backupModules: [\"adv_recovery\",\"amd\"]
        notificationEmail: \"test@test.com\"    
      }){
        status message
      }
   }");
      
   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"Name contains whitespaces\/special characters use - instead","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
  }
  
}