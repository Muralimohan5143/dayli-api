<?php

function printDaysofYear($year)
{

    $year= date("Y");
    for ($i = 1; $i <= 12; $i++) {
        $number = cal_days_in_month(CAL_GREGORIAN, $i, $year);
        $month = $i < 10 ? "0$i" : "$i";
        for ($j = 1; $j <= $number; $j++) {
            $day = $j < 10 ? "0$j" : "$j";
            echo "$year$month$day\r\n";
          }
        }
        //echo "--------\r\n";
    }
printDaysofYear(2004);

?>
