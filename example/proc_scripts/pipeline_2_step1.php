<?php

use aventri\Multiprocessing\Example\Steps\Pipeline2\AlphaVantage;
use aventri\Multiprocessing\Task;

include realpath(__DIR__ . "/../../vendor/") . "/autoload.php";

(new class extends Task
{
    private $url = "https://www.alphavantage.co/query?function=TIME_SERIES_DAILY_ADJUSTED&symbol=%s&outputsize=full&apikey=%s";
    private $api_key = "DJ5RATXFWLK0W4MS";

    /** @noinspection PhpComposerExtensionStubsInspection */
    public function consume($symbol)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, sprintf($this->url, $symbol, $this->api_key));
        $res = curl_exec($ch);
        $item = json_decode($res, true);
        $timeSeriesData = [];
        $dateTimeZone = new DateTimeZone($item["Meta Data"]["5. Time Zone"]);
        foreach($item["Time Series (Daily)"] as $date => $data) {
            $phpDate = DateTime::createFromFormat("Y-m-d", $date, $dateTimeZone);
            $timeSeriesData[] = [
                $phpDate, $data
            ];
        }
        $timeSeriesData = array_reverse($timeSeriesData);
        $alphaVantage = new AlphaVantage();
        $alphaVantage->metaData = $item["Meta Data"];
        $alphaVantage->timeSeries = $timeSeriesData;
        $this->write($alphaVantage);
    }
})->listen();


