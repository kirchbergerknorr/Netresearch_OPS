<?php
$installer = $this;
$installer->startSetup();



$installer->run("
   UPDATE {$this->getTable('core_config_data')}
   SET value = 'Ingenico Payment Services Belfius Direct Net'
   WHERE path = 'payment/ops_belfiusDirectNet/title'
   AND value = 'Ingenico Payment Services BelfiusDirectNet';
 ");

$installer->endSetup();

