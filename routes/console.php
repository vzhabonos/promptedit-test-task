<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('pixelforge:reconcile')->everyMinute()->withoutOverlapping();
