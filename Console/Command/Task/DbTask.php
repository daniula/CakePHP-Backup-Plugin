<?php

App::uses('ConnectionManager', 'Model');

class DbTask extends Shell {
  private $changes = array();
  private $controller;

  private function getParams($select = null) {
    $db = ConnectionManager::getDataSource('default');

    $login = ConnectionManager::$config->default['login'];
    $password = ConnectionManager::$config->default['password'];
    $database = ConnectionManager::$config->default['database'];
    $options = '--skip-opt --no-create-info';

    if (is_null($select)) {
      return compact('db', 'login', 'password', 'database', 'options');
    } else {
      return $$select;
    }

  }

  public function restoreAll($path, $truncate = true) {
    $result = true;

    $tables = $this->getParams('db')->listSources();

    foreach ($tables as $table) {
      if (!$this->restore($table, $path, $truncate)) {
        return false;
      }
    }

    return $result;
  }

  public function restore($table, $path, $truncate = true) {
    $modelName = Inflector::singularize(ucfirst($table));
    $this->loadModel($modelName);

    if ($truncate) {
      $query = sprintf('TRUNCATE %s;', $table);
      $this->out($query);
      $this->{$modelName}->query($query);
    }

    $f = new File($path.$table.'.sql');
    $sql = explode("\n", $f->read());

    $successCount = $failCount = 0;

    $beforeCount = $this->{$modelName}->find('count');
    $this->out(sprintf('%s has %d records', $table, $beforeCount));

    $sqlCount = 0;
    foreach ($sql as $i => $insert) {
      if (preg_match('/^INSERT INTO/', $insert)) {
        $sqlCount++;
        $this->{$modelName}->query($insert);
      }
    }

    $afterCount = $this->{$modelName}->find('count');
    $this->out(sprintf('Restored %d after %d quries in %s table.', ($afterCount - $beforeCount), $sqlCount, $table));
    return ( ($afterCount - $beforeCount) == $sqlCount );
  }

  public function dumpAll($path, $force = false) {
    $result = null;

    $tables = $this->getParams('db')->listSources();
    foreach ($tables as $table) {
      $modelName = Inflector::singularize(ucfirst($table));
      $this->loadModel($modelName);

      if ($force || $this->hasChanges( $this->{$modelName} )) {
        $result = true;
        if (!$this->dump($table, $path)) {
          return false;
        }
      }
    }

    if (!$force) {
      $this->saveChanges();
    }

    return $result;
  }

  public function dump($table, $path) {
    extract($this->getParams());

    $cmd = sprintf('mysqldump -u %s -p%s %s %s %s > %s.sql', $login, $password, $options, $database, $table, $path.$table);
    $this->out($cmd);
    exec($cmd, $output, $error);
    if ($error) {
      $this->err($output);
      return false;
    }
    return true;
  }

  private function hasChanges($model) {
    $schema = $model->schema();
    $result = false;

    if (!empty($schema['updated'])) {
      $fields = array('MAX(updated) AS lastUpdate');
      $lastUpdate = strtotime(Set::extract($model->find('first', compact('fields')), '0.lastUpdate'));
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

    if (!isset($this->changes[$modelName]) || $this->changes[$modelName] < $value) {
      $this->changes[$modelName] = $value;
      $result = true;
    }

    return $result;
  }

  private function saveChanges() {
    Cache::write('Backup.Db', $this->changes);
  }

  public function getTmpPath($dir = null) {
    return dirname(dirname(dirname(dirname(__FILE__)))).DS.'tmp'.DS.(is_null($dir) ? '' : $dir.DS);
  }

  public function loadModel($modelClass = null, $id = null) {
    if (is_null($this->controller)) {
      App::uses('AppController', 'Controller');
      $this->controller = new AppController();
    }

    $this->controller->loadModel($modelClass, $id);
    $this->{$modelClass} = $this->controller->{$modelClass};
  }
}