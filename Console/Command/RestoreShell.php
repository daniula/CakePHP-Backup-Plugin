<?php

class RestoreShell extends AppShell {
  public $tasks = array('Backup.Db', 'Backup.Git');

  public function getOptionParser() {
    $parser = parent::getOptionParser();
    $parser->addOption('branch', array(
      'help' => 'Define which branch should be used to pull database records.',
      'short' => 'b',
      'default' => 'master',
    ));
    $parser->addOption('quick', array(
      'help' => 'Skip dumping current state of database.',
      'short' => 'q',
      'default' => false,
      'boolean' => true,
    ));
    $parser->addOption('table', array(
      'help' => 'Restore only selected table.',
      'short' => 't',
    ));
    return $parser;
  }

  public function main() {

    if (!$this->params['quick']) {
      $dumpPath = $this->Db->getTmpPath('dump');

      if (empty($this->params['table'])) {
        $this->Db->dumpAll($dumpPath);
      } else {
        $this->Db->dump($this->params['table'], $dumpPath);
      }
    }

    $path = $this->Db->getTmpPath('pull');

    $this->Git->pull($path, $this->params['branch']);

    if (empty($this->params['table'])) {
      $result = $this->Db->restoreAll($path);
    } else {
      $result = $this->Db->restore($this->params['table'], $path);
    }

    if ($result) {
      $this->out('Operation finished succesfully.');
    } else {
      $this->out('Operation failed.');
    }
  }
}