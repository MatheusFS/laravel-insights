<?php

return [

    'routes_name' => 'insights.',
    'routes_prefix' => 'insights',

    'user_model' => App\Models\User::class,
    'ignore_models' => [
        Encore\Admin\Auth\Database\Administrator::class
    ],

    'middlewares' => ['web']
];