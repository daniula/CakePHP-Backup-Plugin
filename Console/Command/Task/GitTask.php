<?php

class GitTask extends Shell {

  private function exec($cmd) {
    $cmd = is_array($cmd) ? join(' && ', $cmd) : $cmd;

    $result = exec($cmd, $output, $error);
    if ($error) {
      $this->err(join("\n", $output));
    }
    return $result;
  }


  public function push($path, $branch) {
    $cmd = array(
      sprintf('cd %s', $path),
      sprintf('git checkout %s', $branch),
      sprintf('git add .'),
      sprintf('git commit -m"%s"', date(DateTime::W3C)),
      sprintf('git push origin %s', $branch),
    );

    return $this->exec($cmd);
  }

  public function pull($path, $branch) {
    $cmd = array(
      sprintf('cd %s', $path),
      sprintf('git checkout %s', $branch),
      sprintf('git pull origin %s', $branch),
    );

    return $this->exec($cmd);
  }
}