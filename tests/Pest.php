<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the application. Architecture and unit tests stay
| framework-free unless they extend the case themselves.
|
*/

pest()->extend(TestCase::class)->in('Feature');
