<?php

arch('models')
    ->expect('App\Models')
    ->toHaveMethod('casts')
    ->ignoring('App\Models\User');

arch('controllers')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('form requests')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('models should use HasFactory')
    ->expect('App\Models')
    ->toUse('Illuminate\Database\Eloquent\Factories\HasFactory')
    ->ignoring([
        'App\Models\Elimination',
        'App\Models\FinaleChoice',
        'App\Models\FinalResult',
        'App\Models\HintUsage',
        'App\Models\JackpotTransaction',
        'App\Models\PlayerConnection',
        'App\Models\ProjectionAccess',
        'App\Models\Round6PlayerJackpot',
        'App\Models\Round6TurnOrder',
        'App\Models\RoundRanking',
        'App\Models\RoundSkip',
        'App\Models\SessionEvent',
    ]);

arch('no env() usage outside config')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('controllers should not use DB facade directly')
    ->expect('Illuminate\Support\Facades\DB')
    ->toOnlyBeUsedIn([
        'App\Http\Controllers\Admin\GameController',
        'App\Http\Controllers\Admin\SessionController',
        'App\Http\Controllers\Player\GameController',
        'App\Http\Controllers\Player\PreselectionController',
        'App\Http\Controllers\Player\RegistrationController',
    ]);
