<?php

date_default_timezone_set('UTC');

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

    const STEP_LENGTH = 0.762;
    const TYPE_PODCAST = 1;
    const TYPE_YOUTUBE = 2;

    public function __construct($dataDirectory, $destinationDirectory) {
        $this->dataDirectory = $dataDirectory;
        $this->destinationDirectory = realpath($destinationDirectory);
        $this->postDirectory = realpath($destinationDirectory . '/content/post');
        $this->staticDirectory = realpath($destinationDirectory . '/data');
        $this->photosDirectory = realpath($destinationDirectory . '/static/photos');
        $this->goodReadsBooks = json_decode(file_get_contents($this->dataDirectory . '/goodreads/books.json'), true);
        $this->imdbMovies = $this->loadImdbMovies($this->dataDirectory . '/imdb/ratings.csv');
        $this->podcasts = $this->processPodcasts(
            json_decode(file_get_contents($this->dataDirectory . '/podcast/podcast.json'), true)
        );
    }

    public function loadImdbMovies($fileName) {
        // Adapted from https://stackoverflow.com/a/41942299/17734
        $rows   = array_map('str_getcsv', file($fileName));
        $header = array_shift($rows);
        $result = [];
        foreach($rows as $row) {
            $item = array_combine($header, $row);
            $date = date('Y-m-d', strtotime($item['created']));
            if (!isset($result[$date])) {
                $result[$date] = [];
            }
            $result[$date][] = [
                'id' => $item['Const'],
                'title' => $item['Title'],
                'year' => $item['Year'],
                'url' => $item['URL'],
            ];
        }
        return $result;
    }

    public function run($fromDate, $toDate) {
        $dataFile = $this->staticDirectory . '/data.json';
        $allData = json_decode(file_get_contents($dataFile), true);
        $allData = $allData['data'];

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod(new DateTime($fromDate), $interval, new DateTime($toDate));

        foreach ($period as $dt) {
            $date = $dt->format('Y-m-d');
            $filename = "{$this->postDirectory}/{$date}.md";
            $data = [
                'date' => $date,
                'type' => 'post',
                'title' => 'Report for ' . $dt->format('l jS \of F Y'),
                'slug' => $dt->format('Y/m/d'),
                'categories' => [
                    'Daily report',
                ],
                'images' => $this->getPhotos($dt),
                'health' => $this->getHealth($dt),
                'nutrition' => $this->getNutrition($dt),
                'exercise' => $this->getExcercise($dt),
                'media' => $this->getMedia($dt),
            ];
            $allData[$date] = [
                'health' => $data['health'],
                'nutrition' => $data['nutrition'],
                'exercise' => $data['exercise'],
                'media' => $data['media'],
            ];
            $body = $this->getBody($dt);
            $story = $this->generateStory($data);
            $payload = json_encode($data, JSON_PRETTY_PRINT);
            if ($body) {
                $payload .= PHP_EOL . PHP_EOL . $body;
            }
            $payload .= PHP_EOL . PHP_EOL . $story;
            file_put_contents($filename, $payload);
            print($filename . PHP_EOL);
        }
        ksort($allData);
        file_put_contents($dataFile, json_encode(['data' => $allData], JSON_PRETTY_PRINT));
    }

    private function getBody($dt) {
        $filename = "{$this->dataDirectory}vimwiki/diary/{$dt->format('Y-m-d')}.wiki";
        if (file_exists($filename)) {
            return file_get_contents($filename);
        }
        return '';
    }

    private function generateStory($data) {
        $text = [];
        $km = round($data['exercise']['steps'] * self::STEP_LENGTH / 1000, 2);
        $text[] = "Today I am <strong>{$data['health']['age']} days</strong> old and my weight is <strong>{$data['health']['weight']} kg</strong>.";
        $text[] = "During the day, I consumed <strong>{$data['nutrition']['calories']} kcal</strong> coming from <strong>{$data['nutrition']['fat']} g</strong> fat, <strong>{$data['nutrition']['carbohydrates']} g</strong> carbohydrates and <strong>{$data['nutrition']['protein']} g</strong> protein.";
        $text[] = "Managed to do <strong>{$data['exercise']['pushups']} push-ups</strong>, <strong>{$data['exercise']['crunches']} crunches</strong> and walked <strong>{$data['exercise']['steps']} steps</strong> during the day which is approximately <strong>{$km} km</strong>.";
        return implode (' ', $text);
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
        $command = "/home/aquilax/gvm/pkgsets/go1.7.1/global/bin/merki -f {$this->dataDirectory}health.log filter born | awk '{print $1}'";
        $date = new DateTime($this->exec($command));
        $diif = $date->diff($dt);
        return $diif->days;
    }

    private function getWeight($dt) {
        $command = "/home/aquilax/gvm/pkgsets/go1.7.1/global/bin/merki -f {$this->dataDirectory}health.log filter -d -a weight | grep {$dt->format('Y-m-d')} | awk '{print $3}'";
        return floatval($this->exec($command));
    }

    private function getHeight($dt) {
        $command = "/home/aquilax/gvm/pkgsets/go1.7.1/global/bin/merki -f {$this->dataDirectory}health.log filter -d -a height | tail -n 1 | awk '{print $3}'";
        return floatval($this->exec($command));
    }

    private function getDailyFromHranoprovod($dt, $measure) {
        $command = "/home/aquilax/gvm/pkgsets/go1.7.1/global/bin/hranoprovod-cli -d {$this->dataDirectory}food.yaml -l {$this->dataDirectory}log.yaml reg --no-color -b {$dt->format('Y/m/d')} -e {$dt->format('Y/m/d')} -s {$measure} --csv | awk -F \";\" '{print $3}'";
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
        $command = "/home/aquilax/gvm/pkgsets/go1.7.1/global/bin/merki -f {$this->dataDirectory}health.log filter -d -s {$excercise} | grep {$dt->format('Y-m-d')} | awk '{print $3}'";
        return intval($this->exec($command));
    }


    public function getExcercise($dt) {
        return [
            'pushups' => $this->getExcerciseTotal($dt, 'pushup'),
            'crunches' => $this->getExcerciseTotal($dt, 'crunch'),
            'steps' => $this->getDailyFromHranoprovod($dt, 'стъпки'),
        ];
    }

    private function getBooks($dt) {
        $date = $dt->format('Y-m-d');
        if (isset($this->goodReadsBooks[$date])) {
            return $this->goodReadsBooks[$date];
        }
        return [];
    }

    private function getMedia($dt) {
        return [
            'books' => $this->getBooks($dt),
            'podcast' => $this->getPodcast($dt, self::TYPE_PODCAST),
            'youtube' => $this->getPodcast($dt, self::TYPE_YOUTUBE),
            'movies' => $this->getMovies($dt),
            'photos' => $this->getPhotos($dt),
        ];
    }

    private function getMovies($dt) {
        $date = $dt->format('Y-m-d');
        if (isset($this->imdbMovies[$date])) {
            return $this->imdbMovies[$date];
        }
        return [];
    }


    private function getPodcast($dt, $type) {
        $date = $dt->format('Y-m-d');
        if (!isset($this->podcasts[$date])) {
            return [];
        }
        return array_filter($this->podcasts[$date], function($item) use ($type) {
            return $item['type'] === $type;
        });
    }

    private function processPodcasts($rawPodcasts) {
        $result = [];
        foreach($rawPodcasts as $cast) {
            $date = date('Y-m-d', $cast['consumed']);
            if (!isset($result[$date])) {
                $result[$date] = [];
            }
            $image = $cast['image'] ? $cast['image'] : $cast['channel_image'];
            $result[$date][] = [
                'id' => $cast['id'],
                'url' => "http://cast.writtn.com/episode/{$cast['id']}/quantified",
                'image' => $image,
                'channel_title' => $cast['channel_title'],
                'type' => intval($cast['channel_type']),
                'title' => $cast['title'],
                'duration' => $cast['duration']
            ];
        }
        return $result;
    }

    private function getPhotos($dt) {
        $date = $dt->format('Y-m-d');
        $directory = $this->photosDirectory . '/' .$date;
        $photos = [];
        if (file_exists($directory)) {
            $files = array_diff(scandir($directory), array('..', '.'));
            foreach ($files as $filename) {
                $photos[] = "/photos/{$date}/{$filename}";
            }
        }
        return $photos;
    }
}

$dt = new DataGenerator($dataDirectory, $destinationDirectory);
$dt->run($fromDate, $toDate);
