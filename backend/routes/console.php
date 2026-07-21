<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('employees:sync-effective-jobs')->dailyAt('00:05');
