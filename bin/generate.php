<?php

$fromDate = 'yesterday';
$toDate = 'today';

if (isset($argv[1])) {
    $fromDate = $argv[1];
}
if (isset($argv[2])) {
    $toDate = $argv[2];
}

$dataDirectory = $argv[3];
$destinationDirectory = $argv[4];

class DataGenerator {

    public function __construct($dataDirectory, $destinationDirectory) {
        $this->dataDirectory = $dataDirectory;
        $this->destinationDirectory = $destinationDirectory;
    }

    public function run($fromDate, $toDate) {
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod(new DateTime($fromDate), $interval, new DateTime($toDate));

        foreach ($period as $dt) {
            $date = $dt->format('Y-m-d');
            $filename = "{$date}.md";
            $data = [
                'date' => $date,
                'type' => 'post',
                'title' => 'Report for ' . $dt->format('l jS \of F Y'),
                'slug' => $dt->format('Y/m/d'),
                'categories' => [
                    'Daily report',
                ],
                'health' => $this->getHealth($dt),
                'nutrition' => $this->getNutrition($dt),
                'exercise' => $this->getExcercise($dt),
            ];
            $body = $this->getBody($dt);
            $payload = json_encode($data, JSON_PRETTY_PRINT);
            if ($body) {
                $payload .= PHP_EOL . PHP_EOL .$body;
            }
            file_put_contents($this->destinationDirectory . $filename, $payload);
            print($this->destinationDirectory . $filename . PHP_EOL);
        }
    }

    private function getBody($dt) {
        $filename = "{$this->dataDirectory}vimwiki/diary/{$dt->format('Y-m-d')}.wiki";
        if (file_exists($filename)) {
            return file_get_contents($filename);
        }
        return '';
    }

    private function exec($command) {
        $output = array();
        exec($command, $output);
        return implode(PHP_EOL, $output);
    }

    private function getHealth($dt) {
        return [
            'weight' => $this->getWeight($dt),
            'height' => $this->getHeight($dt),
            'age' => $this->getAge($dt),
        ];
    }

    private function getAge($dt) {
        $command = "merki -f {$this->dataDirectory}health.log filter born | awk '{print $1}'";
        $date = new DateTime($this->exec($command));
        $diif = $date->diff($dt);
        return $diif->days;
    }

    private function getWeight($dt) {
        $command = "merki -f {$this->dataDirectory}health.log filter -d -a weight | grep {$dt->format('Y-m-d')} | awk '{print $3}'";
        return floatval($this->exec($command));
    }

    private function getHeight($dt) {
        $command = "merki -f {$this->dataDirectory}health.log filter -d -a height | tail -n 1 | awk '{print $3}'";
        return floatval($this->exec($command));
    }

    private function getDailyFromHranoprovod($dt, $measure) {
        $command = "hranoprovod-cli -d {$this->dataDirectory}food.yaml -l {$this->dataDirectory}log.yaml reg --no-color -b {$dt->format('Y/m/d')} -e {$dt->format('Y/m/d')} -s {$measure} --csv | awk -F \";\" '{print $3}'";
        return floatval($this->exec($command));
    }

    public function getNutrition($dt) {
        return [
            'calories' => $this->getDailyFromHranoprovod($dt, 'калории'),
            'fat' => $this->getDailyFromHranoprovod($dt,'мазнини'),
            'carbohydrates' => $this->getDailyFromHranoprovod($dt,'въглехидрати'),
            'protein' => $this->getDailyFromHranoprovod($dt, 'белтъчини'),
        ];
    }

    private function getExcerciseTotal($dt, $excercise) {
        $command = "merki -f {$this->dataDirectory}health.log filter -d -s {$excercise} | grep {$dt->format('Y-m-d')} | awk '{print $3}'";
        return intval($this->exec($command));
    }


    public function getExcercise($dt) {
        return [
            'pushups' => $this->getExcerciseTotal($dt, 'pushup'),
            'crunches' => $this->getExcerciseTotal($dt, 'crunch'),
            'steps' => $this->getExcerciseTotal($dt, 'steps'),
        ];
    }
}

$dt = new DataGenerator($dataDirectory, $destinationDirectory);
$dt->run($fromDate, $toDate);