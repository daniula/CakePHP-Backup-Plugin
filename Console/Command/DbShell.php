<?php

App::uses('ConnectionManager', 'Model');

define('DUMP_PATH', dirname(dirname(dirname(__FILE__))).DS.'tmp'.DS );

class DbShell extends AppShell {
  private $changes = array();

  public function getOptionParser() {
    $parser = parent::getOptionParser();
    $parser->addOption('branch', array(
      'help' => 'Define which branch should be used to push new database dump.',
      'short' => 'b',
      'default' => 'master',
    ));
    return $parser;
  }

  public function main() {
    $db = ConnectionManager::getDataSource('default');

    $login = ConnectionManager::$config->default['login'];
    $password = ConnectionManager::$config->default['password'];
    $database = ConnectionManager::$config->default['database'];
    $options = '--skip-opt --no-create-info';

    $tables = $db->listSources();
    foreach ($tables as $table) {
      $modelName = Inflector::singularize(ucfirst($table));
      $this->loadModel($modelName);

      if ($this->hasChanges( $this->{$modelName} )) {
        $cmd = sprintf('mysqldump -u %s -p%s %s %s %s > %s.sql', $login, $password, $options, $database, $table, DUMP_PATH.$table);
        $this->out($cmd);
        exec($cmd);
      }
    }
    $this->saveChanges();

    $cmd = array(
      sprintf('cd %s', DUMP_PATH),
      sprintf('git checkout %s', $this->params['branch']),
      sprintf('git add .'),
      sprintf('git commit -m"%s"', date(DateTime::W3C)),
      sprintf('git push origin %s', $this->params['branch']),
    );

    $this->out('');

    exec(join(' && ', $cmd), $output, $error);
    if ($error) {
      $this->err(join("\n", $output));
    }
  }

  private function hasChanges($model) {
    $schema = $model->schema();

    if (!empty($schema['updated'])) {
      $fields = array('MAX(updated) AS lastUpdate');
      $lastUpdate = Set::extract($model->find('first', compact('fields')), '0.lastUpdate');
      $result = $this->checkChanges($lastUpdate, $model->alias);
    } else {
      $count = $model->find('count');
      $result = $this->checkChanges($count, $model->alias);
    }

    return $result;
  }

  private function checkChanges($value, $modelName) {
    $result = false;

    if ( empty($this->changes) && ($cache = Cache::read('Backup.Db'))) {
      $this->changes = $cache;
    }

    if (empty($this->changes[$modelName]) || $this->changes[$modelName] < $value) {
      $this->changes[$modelName] = $value;
      $result = true;
    }

    return $result;
  }

  private function saveChanges() {
    Cache::write('Backup.Db', $this->changes);
  }
}