<?php

use aventri\Multiprocessing\Example\Steps\Pipeline2\AlphaVantage;
use aventri\Multiprocessing\StreamEventCommand;
use CpChart\Chart\Stock;
use CpChart\Data;
use CpChart\Image;

include realpath(__DIR__ . "/../../vendor/") . "/autoload.php";

(new class extends StreamEventCommand
{
    /**
     * @param AlphaVantage $alphaVantage
     */
    public function consume($alphaVantage)
    {
        $timeSeries = array_slice($alphaVantage->timeSeries, -5);
        $open = [];
        $close = [];
        $high = [];
        $low = [];
        $time = [];
        foreach($timeSeries as $timeValue) {
            $open[] = $timeValue[1]["1. open"];
            $close[] = $timeValue[1]["4. close"];
            $high[] = $timeValue[1]["2. high"];
            $low[] = $timeValue[1]["3. low"];
            $time[] = $timeValue[0]->format("Y-m-d");
        }

        /* Create and populate the Data object */
        $data = new Data();
        $data->addPoints($open, "Open");
        $data->addPoints($close, "Close");
        $data->addPoints($high, "Min");
        $data->addPoints($low, "Max");
        $data->setAxisDisplay(0, AXIS_FORMAT_CURRENCY, "$");
        $data->addPoints($time, "Time");
        $data->setAbscissa("Time");

        /* Create the Image object */
        $image = new Image(700, 230, $data);

        /* Draw the background */
        $settings = ["R" => 170, "G" => 183, "B" => 87, "Dash" => 1, "DashR" => 190, "DashG" => 203, "DashB" => 107];
        $image->drawFilledRectangle(0, 0, 700, 230, $settings);

        /* Overlay with a gradient */
        $settings = ["StartR" => 219, "StartG" => 231, "StartB" => 139, "EndR" => 1, "EndG" => 138, "EndB" => 68, "Alpha" => 50];
        $image->drawGradientArea(0, 0, 700, 230, DIRECTION_VERTICAL, $settings);

        /* Draw the border */
        $image->drawRectangle(0, 0, 699, 229, ["R" => 0, "G" => 0, "B" => 0]);

        /* Write the title */
        $image->setFontProperties(["FontName" => "Forgotte.ttf", "FontSize" => 11]);
        $image->drawText(60, 45, "Stock price", ["FontSize" => 28, "Align" => TEXT_ALIGN_BOTTOMLEFT]);

        /* Draw the 1st scale */
        $image->setGraphArea(60, 60, 450, 190);
        $image->drawFilledRectangle(60, 60, 450, 190, [
            "R" => 255,
            "G" => 255,
            "B" => 255,
            "Surrounding" => -200,
            "Alpha" => 10
        ]);
        $image->drawScale(["DrawSubTicks" => true, "CycleBackground" => true]);

        /* Draw the 1st stock chart */
        $mystockChart = new Stock($image, $data);
        $image->setShadow(true, ["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 30]);
        $mystockChart->drawStockChart();

        /* Reset the display mode because of the graph small size */
        $data->setAxisDisplay(0, AXIS_FORMAT_DEFAULT);

        /* Draw the 2nd scale */
        $image->setShadow(false);
        $image->setGraphArea(500, 60, 670, 190);
        $image->drawFilledRectangle(500, 60, 670, 190, [
            "R" => 255,
            "G" => 255,
            "B" => 255,
            "Surrounding" => -200,
            "Alpha" => 10
        ]);
        $image->drawScale(["Pos" => SCALE_POS_TOPBOTTOM, "DrawSubTicks" => true]);

        /* Draw the 2nd stock chart */
        $mystockChart = new Stock($image, $data);
        $image->setShadow(true, ["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 30]);
        $mystockChart->drawStockChart();

        $filename = realpath(__DIR__) . "/{$alphaVantage->metaData["2. Symbol"]}.png";
        /* Render the picture (choose the best way) */
        $image->autoOutput($filename);
        $this->write($filename);
    }
})->listen();




