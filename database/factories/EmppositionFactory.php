<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Empposition;
use Faker\Generator as Faker;

$factory->define(Empposition::class, function (Faker $faker) {
    return [
        'Dep_id' => '1',
        'position' => 'Developer'
    ];
});
