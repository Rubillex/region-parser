<?php

namespace Maptiler;

class Parser
{
    const BASE_URL = 'https://api.maptiler.com/geocoding/';
    const COORDS_DELIMETER = ',';
    const END_OF_URL = '.json?language=ru&key=';
    const MAPTILER_KEYS = ['API_KEY_1', 'API_KEY_2'];
    const STEP = 0.02;

    protected $searchSquares = [
        [
            ['lat' => 48.881997, 'lng' => 36.312180],
            ['lat' => 47.875209, 'lng' => 39.058279],
        ],
    ];

    protected $outputData = [];

    protected $filename = '';

    protected $file = null;

    public function __construct()
    {
        print_r(PHP_EOL);
        $this->filename = readline('Output CSV filename: ');
        $this->filename = 'outputFiles/' . $this->filename . '.csv';
        $this->file = fopen($this->filename, 'w');
    }

    protected function getInfoFromMap($lat, $lng)
    {
        $json = file_get_contents(self::BASE_URL . $lng . self::COORDS_DELIMETER . $lat . self::END_OF_URL . array_rand(self::MAPTILER_KEYS));
        return json_decode($json);
    }

    public function getPoints()
    {
        $cityId = 0;

        foreach ($this->searchSquares as $square) {
            $latFirst = min($square[0]['lat'], $square[1]['lat']);
            $latSecond = max($square[0]['lat'], $square[1]['lat']);

            $lngFirst = min($square[0]['lng'], $square[1]['lng']);
            $lngSecond = max($square[0]['lng'], $square[1]['lng']);

            for ($lat = $latFirst; $lat < $latSecond + self::STEP; $lat += self::STEP) {
                for ($lng = $lngFirst; $lng < $lngSecond + self::STEP; $lng += self::STEP) {
                    $this->parseData($this->getInfoFromMap($lat, $lng), $cityId, $lat, $lng);
                    print_r($lat . ' ' . $lng . PHP_EOL);
                    $cityId++;
                    sleep(0.5);
                }
            }
        }

        // уникальность
        $this->outputData = array_map("unserialize", array_unique(array_map("serialize", $this->csvToArray($this->filename))));
        $this->outputData = array_values($this->outputData);

        fclose($this->file);
        $this->file = fopen($this->filename, 'w');

        foreach ($this->outputData as $fields) {
            fputcsv($this->file, $fields);
        }

        fclose($this->file);
    }

    protected function parseData($pointInfo, $cityId, $lat, $lng)
    {
        $pointData = $pointInfo->features[0]->context;

        // Если точка не является адресом внутри населенного пункта
        if ($pointInfo->features[0]->place_type[0] !== 'street') {
            return;
        }

        if (count($pointData) === 5) {
            foreach ($pointData as $context) {
                if ($context->type == 'neighbourhood' || $context->type == 'city') {
                    $this->outputData[$cityId]['cityName'] = $context->text;
                }
            }

            if ($this->outputData[$cityId]['cityName'] != $pointData[count($pointData) - 5]->text) {
                $this->outputData[$cityId]['area'] = $pointData[count($pointData) - 5]->text;
            }
            $this->outputData[$cityId]['region'] = $pointData[count($pointData) - 2]->text;
            // debug
//            $this->outputData[$cityId]['coords'] = $lng . ',' . $lat;
        }

        if (count($pointData) === 4) {
            $cityName = $pointData[count($pointData) - 4]->text;

            foreach ($pointData as $context) {
                if ($context->type == 'neighbourhood' || $context->type == 'city') {
                    $cityName = $context->text;
                }
            }

            $this->outputData[$cityId]['cityName'] = $cityName;
            if ($this->outputData[$cityId]['cityName'] != $pointData[count($pointData) - 4]->text) {
                $this->outputData[$cityId]['area'] = $pointData[count($pointData) - 4]->text;
            }
            $this->outputData[$cityId]['region'] = $pointData[count($pointData) - 2]->text;
            // debug
//            $this->outputData[$cityId]['coords'] = $lng . ',' . $lat;
        }

        fputcsv($this->file, $this->outputData[$cityId]);
    }

    function csvToArray($csvFile){
        $file_to_read = fopen($csvFile, 'r');
        while (!feof($file_to_read) ) {
            $lines[] = fgetcsv($file_to_read, 1000, ',');
        }
        fclose($file_to_read);
        return $lines;
    }
}