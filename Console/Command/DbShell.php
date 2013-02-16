<?php

class DbShell extends AppShell {
  public $tasks = array('Backup.Db', 'Backup.Git');

  public function getOptionParser() {
    $parser = parent::getOptionParser();
    $parser->addOption('branch', array(
      'help' => 'Define which branch should be used to push new database dump.',
      'short' => 'b',
      'default' => 'master',
    ));
    $parser->addOption('force', array(
      'help' => 'Force dumping databases.',
      'short' => 'f',
      'default' => false,
      'boolean' => true,
    ));
    return $parser;
  }

  public function main() {
    $path = $this->Db->getTmpPath('dump');

    $changes = $this->Db->dumpAll($path, $this->params['force']);

    if ($changes) {
      $this->Git->push($path, $this->params['branch']);
    } elseif (is_null($changes)) {
      $this->out('No changes in database.');
    } else {
      $this->out('Operation failed.');
    }
  }
}