<?php

return [

    'routes_name' => 'insights.',
    'routes_prefix' => 'insights',

    'user_model' => App\Models\User::class,

    'middlewares' => ['auth', 'web']
];